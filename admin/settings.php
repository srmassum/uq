<?php
/**
 * Settings — the handful of values an editor can change without a deploy.
 *
 * The list is not free-form. `setting_defaults()` in inc/settings.php is the
 * contract: every key offered here exists there with a shipped default, and each
 * one is read somewhere in the public code. A row in the settings table whose key
 * nothing looks up is a switch that silently does nothing — which is why the
 * "Not wired up yet" panel at the bottom names the few declared keys no page
 * reads yet, instead of offering them as controls.
 *
 * Each group is its own form and its own POST, so saving the footer cannot
 * blank the site name. The handler only ever writes the keys belonging to the
 * group that was actually submitted.
 */

declare(strict_types=1);

require_once __DIR__ . '/inc/panel.php';

require_admin('settings.manage');
admin_require_csrf();

/**
 * The form, as data.
 *
 * @return array<int, array{title: string, sub: string, fields: array<int, array<string, mixed>>}>
 */
function settings_spec(): array
{
    return [
        [
            'title' => 'Site',
            'sub'   => 'What the site calls itself, and what language a first-time visitor lands in.',
            'fields' => [
                ['k' => 'site.name', 't' => 'Site name', 'maxlen' => 60, 'required' => true,
                 'help' => 'Used in the browser tab, in the header, and as the name attached to every shared card.'],
                ['k' => 'site.tagline', 't' => 'Tagline', 'maxlen' => 120,
                 'help' => 'The line under the name on the home page. Keep the question mark — the question is what makes people answer it.'],
                ['k' => 'site.default_lang', 't' => 'First-visit language', 'type' => 'select',
                 'options' => ['en' => 'English', 'ar' => 'العربية (right to left)'],
                 'help' => 'Only the starting point. The language switch overrides it, and the choice is remembered in a cookie.'],
            ],
        ],
        [
            'title' => 'Footer and contact',
            'sub'   => 'The small print, and how somebody reaches a human.',
            'fields' => [
                ['k' => 'footer.disclaimer', 't' => 'Disclaimer', 'type' => 'textarea', 'rows' => 2, 'maxlen' => 300,
                 'help' => 'Shown at the bottom of every page. Worth keeping honest — this content does go stale.'],
                ['k' => 'contact.email', 't' => 'Contact email', 'type' => 'email', 'maxlen' => 160,
                 'help' => 'Shown in the footer as a mailto link. Leave blank to show nothing.'],
                ['k' => 'social.instagram', 't' => 'Instagram URL', 'maxlen' => 255, 'ph' => 'https://instagram.com/…'],
                ['k' => 'social.x', 't' => 'X URL', 'maxlen' => 255, 'ph' => 'https://x.com/…'],
                ['k' => 'social.tiktok', 't' => 'TikTok URL', 'maxlen' => 255, 'ph' => 'https://tiktok.com/@…'],
            ],
        ],
        [
            'title' => 'Search engines and sharing',
            'sub'   => 'What a search result or a pasted link says before anyone clicks it.',
            'fields' => [
                ['k' => 'seo.default_title', 't' => 'Default page title', 'maxlen' => 120,
                 'help' => 'Used on pages that do not set their own title. Around 60 characters is where Google starts cutting it off.'],
                ['k' => 'seo.default_desc', 't' => 'Default description', 'type' => 'textarea', 'rows' => 2, 'maxlen' => 300,
                 'help' => 'The grey paragraph under a search result. About 150 characters fit.'],
            ],
        ],
        [
            'title' => 'Closing the doors',
            'sub'   => 'For the minutes while a database import or a file change is happening.',
            'fields' => [
                ['k' => 'maintenance', 't' => 'Maintenance mode', 'type' => 'bool', 'on' => 'Close the public site'],
                ['k' => 'maintenance_note', 't' => 'What to tell visitors', 'type' => 'textarea', 'rows' => 3, 'maxlen' => 400,
                 'help' => 'Shown on the holding page. Say what is happening and roughly how long — or leave it blank and the default sentence runs.'],
            ],
        ],
    ];
}

/** The group whose title was posted, or null if it does not name one. */
function settings_group(string $title): ?array
{
    foreach (settings_spec() as $g) {
        if ($g['title'] === $title) {
            return $g;
        }
    }
    return null;
}

/** How a posted value is checked before it is stored. Empty is always allowed. */
function settings_validate(string $key, string $value): ?string
{
    if ($value === '') {
        return null;
    }

    return match ($key) {
        'site.default_lang' => in_array($value, SUPPORTED_LANGS, true)
            ? null
            : 'That is not one of the languages this site ships with (' . implode(', ', SUPPORTED_LANGS) . ').',

        'contact.email' => filter_var($value, FILTER_VALIDATE_EMAIL)
            ? null : 'That does not look like an email address.',

        'social.instagram', 'social.x', 'social.tiktok' => preg_match('#^https?://#i', $value)
            ? null : 'Needs to be a full URL, starting with https://',

        default => null,
    };
}

/**
 * Write one setting, reporting whether it actually changed.
 *
 * A value of '' is meaningful rather than a deletion: setting() falls back to
 * the shipped default when a stored value is empty, so clearing a box restores
 * the original copy instead of leaving a blank space in the header.
 */
function settings_write(string $key, string $value): int
{
    try {
        $before = (string) qVal('SELECT v FROM settings WHERE k = ?', [$key]);
        setting_set($key, $value);
        if ($before !== $value) {
            log_activity('settings', 'update', 'setting', null,
                ['k' => $key, 'v' => $before], ['k' => $key, 'v' => $value]);
            return 1;
        }
    } catch (Throwable $e) {
        error_log("settings_write($key): " . $e->getMessage());
    }
    return 0;
}

// ------------------------------------------------------------------ actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $group = settings_group(post_str('_group'));
    if ($group === null) {
        admin_back(admin_url('settings.php'),
            'That form did not say which group it belonged to, so nothing was saved.', 'bad');
    }

    $errors  = [];
    $saved   = 0;
    $nowOn   = null;   // the maintenance switch as posted, not as last cached

    foreach ($group['fields'] as $f) {
        $key   = (string) $f['k'];
        $type  = (string) ($f['type'] ?? 'text');
        $raw   = $_POST[$key] ?? null;

        $value = $type === 'bool'
            ? ($raw === '1' ? '1' : '0')
            : (is_string($raw) ? trim($raw) : '');

        if ($key === 'maintenance') {
            $nowOn = $value === '1';
        }

        if (mb_strlen($value) > 2000) {
            $errors[$key] = 'That is too long for a setting.';
            continue;
        }
        $err = settings_validate($key, $value);
        if ($err !== null) {
            $errors[$key] = $err;
            continue;
        }

        $saved += settings_write($key, $value);
    }

    if ($errors !== []) {
        $_SESSION['ad_set_errors'] = $errors;
        admin_back(admin_url('settings.php'),
            count($errors) . ' value(s) were not saved. The rest of that group went through.', 'bad');
    }

    // The maintenance switch gets a louder confirmation than the rest: it either
    // just took the public site down or just brought it back, and a checkbox is
    // an easy thing to catch with a scroll wheel.
    //
    // The state is read from the posted value rather than setting_on(), because
    // settings_all() caches its rows for the request and would still be holding
    // the value from before this write.
    if ($nowOn === true) {
        admin_back(admin_url('settings.php'),
            'Saved. The public site is now closed — this panel keeps working.', 'warn');
    }
    if ($nowOn === false) {
        admin_back(admin_url('settings.php'), 'Saved. The public site is open again.', 'ok');
    }

    admin_back(admin_url('settings.php'), $saved === 0 ? 'Nothing had changed.' : 'Saved.', 'ok');
}

// -------------------------------------------------------------------- reads
$errors    = $_SESSION['ad_set_errors'] ?? [];
unset($_SESSION['ad_set_errors']);

$current   = [];
$readError = '';
try {
    foreach (qAll('SELECT k, v FROM settings') as $r) {
        $current[(string) $r['k']] = (string) ($r['v'] ?? '');
    }
} catch (Throwable $e) {
    error_log('settings.php: ' . $e->getMessage());
    $readError = $e->getMessage();
}

/** The stored value, else the shipped default, else empty. */
function settings_show(array $current, array $field): string
{
    $k = (string) $field['k'];
    if (($current[$k] ?? '') !== '') {
        return (string) $current[$k];
    }
    $defaults = setting_defaults();
    if (array_key_exists($k, $defaults)) {
        return (string) $defaults[$k];
    }
    return ($field['type'] ?? 'text') === 'bool' ? '0' : '';
}

/** Every key this screen offers. */
function settings_keys(): array
{
    $keys = [];
    foreach (settings_spec() as $group) {
        foreach ($group['fields'] as $f) {
            $keys[] = (string) $f['k'];
        }
    }
    return $keys;
}

admin_head(['title' => 'Settings', 'nav' => 'settings']);

ad_title('Settings', 'The values an editor can change without touching a file on the server.');

if ($readError !== '') {
    ad_note('Could not read the settings table: ' . e($readError) . '. Has <code>sql/schema.sql</code> been imported?', 'bad');
}

if (maintenance_mode()) {
    ad_note(
        '<b>The public site is closed right now.</b> Every visitor gets a holding page with a 503 status. '
        . 'This panel keeps working, and so do saved card images — so a link already shared on Facebook '
        . 'still shows its preview.',
        'warn'
    );
}

foreach (settings_spec() as $group) {
    ad_panel_open();
    ?>
    <h2><?= e($group['title']) ?></h2>
    <p class="ad-help" style="margin-block-start:0"><?= e($group['sub']) ?></p>

    <form class="ad-form" method="post" action="<?= e(admin_url('settings.php')) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="_group" value="<?= e($group['title']) ?>">
      <div class="ad-grid">
        <?php foreach ($group['fields'] as $f): ?>
          <?php
          ad_field($f, settings_show($current, $f));
          if (isset($errors[$f['k']])) {
              echo '<span class="ad-err">' . e($errors[$f['k']]) . '</span>';
          }
          ?>
        <?php endforeach; ?>
      </div>
      <div class="ad-form-act">
        <button class="ad-btn primary" type="submit">Save <?= e(strtolower($group['title'])) ?></button>
      </div>
    </form>
    <?php
    ad_panel_close();
}

// What the code does not read yet — named rather than hidden, because a control
// that saves a value nothing uses is a lie about how the site works.
$unwired = array_values(array_diff(array_keys(setting_defaults()), settings_keys()));

if ($unwired !== []) {
    ad_panel_open();
    ?>
    <h2>Declared but not read yet</h2>
    <p class="ad-help" style="margin-block-start:0">
      <code>inc/settings.php</code> lists these keys and gives each a default, but no page reads them
      yet, so they are not offered as controls. They are shown here so the gap is visible rather than
      guessed at.
    </p>
    <ul class="ad-list">
      <?php foreach ($unwired as $k): ?>
        <li>
          <span class="mono" style="font-size:13px"><?= e($k) ?></span>
          <span class="ad-muted">default: <?= e((string) (setting_defaults()[$k] ?? '')) ?></span>
        </li>
      <?php endforeach; ?>
    </ul>
    <?php
    ad_panel_close();
}

ad_panel_open();
?>
<h2>Numbers that are deliberately not settings</h2>
<p class="ad-help" style="margin-block-start:0">
  Some values look like settings and are kept out of this screen on purpose. The map save rate limit,
  the snapshot size cap, the image limits, the idle timeout and the database credentials all live in
  <code>inc/config.php</code>, where a change goes through version control and a review. Behind a web
  form, one mistyped digit in a browser could take the site down with no record of who typed it.
</p>
<dl class="ad-kv">
  <dt>Map saves per hour, per address</dt><dd><?= (int) MAP_SAVES_PER_HOUR ?></dd>
  <dt>Biggest saved card</dt><dd><?= (int) round(MAX_SNAPSHOT_BYTES / 1024) ?> KB</dd>
  <dt>Biggest image before resizing</dt><dd><?= e(number_format(MAX_UPLOAD_BYTES / 1048576, 1)) ?> MB</dd>
  <dt>Longest stored image edge</dt><dd><?= (int) MAX_IMAGE_EDGE ?> px</dd>
  <dt>Admin idle timeout</dt><dd><?= (int) ADMIN_IDLE_MINUTES ?> minutes</dd>
  <dt>Failed sign-ins before a lockout</dt>
  <dd><?= (int) ADMIN_LOGIN_MAX_FAILS ?> in <?= (int) ADMIN_LOGIN_LOCK_MINUTES ?> minutes</dd>
</dl>
<?php
ad_panel_close();

admin_foot();
