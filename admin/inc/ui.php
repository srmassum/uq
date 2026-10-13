<?php
/**
 * Admin panel chrome and the small widget set every screen is built from.
 *
 * The panel is English-only on purpose. It has one audience — the two or three
 * people who run the site — and a translation layer for screens nobody else
 * sees would be work that never pays for itself. The public site is the part
 * that ships in Arabic.
 *
 * Everything here echoes escaped output. Where a widget takes a callback (a
 * table cell formatter), that callback is responsible for escaping whatever it
 * prints — the formatters below all use e() or num().
 */

declare(strict_types=1);

// ---------------------------------------------------------------------------
// Navigation
// ---------------------------------------------------------------------------

/**
 * The six groups from the roadmap, each item carrying the capability it needs.
 * Items are filtered by admin_can(), so an editor's sidebar simply lacks the
 * screens they would be refused at.
 */
function admin_nav(): array
{
    $c = admin_badge_counts();

    return [
        ['label' => 'Dashboard', 'items' => [
            ['k' => 'dash',   't' => 'Overview',      'h' => 'index.php',   'cap' => null],
            ['k' => 'health', 't' => 'Needs a look',  'h' => 'index.php#todo', 'cap' => null,
             'n' => $c['todo'], 'warn' => true],
        ]],
        ['label' => 'Content', 'items' => [
            ['k' => 'places',  't' => 'Places',        'h' => 'places.php',       'cap' => null, 'n' => $c['places']],
            ['k' => 'muns',    't' => 'Municipalities','h' => 'municipalities.php','cap' => null],
            ['k' => 'cats',    't' => 'Categories',    'h' => 'categories.php',   'cap' => null],
            ['k' => 'tags',    't' => 'Tags',          'h' => 'tags.php',         'cap' => null],
            ['k' => 'itin',    't' => 'Itineraries',   'h' => 'itineraries.php',  'cap' => null],
            ['k' => 'guide',   't' => 'Visitor guide', 'h' => 'guide.php',        'cap' => null],
        ]],
        ['label' => 'Media', 'items' => [
            ['k' => 'media',   't' => 'Images',        'h' => 'media.php',        'cap' => null, 'n' => $c['media']],
            // place_images.place_id is NOT NULL, so an image always belongs to a
            // place. The only images nothing on the site shows are the ones
            // whose place has been moved to the bin.
            ['k' => 'unused',  't' => 'On binned places', 'h' => 'media.php?used=0', 'cap' => null],
        ]],
        ['label' => 'Engagement', 'items' => [
            ['k' => 'badges',  't' => 'Badges',        'h' => 'badges.php',       'cap' => null],
            ['k' => 'levels',  't' => 'Levels',        'h' => 'levels.php',       'cap' => null],
            ['k' => 'themes',  't' => 'Card themes',   'h' => 'themes.php',       'cap' => null],
            ['k' => 'persona', 't' => 'Personalities', 'h' => 'personalities.php','cap' => null],
            ['k' => 'colls',   't' => 'Collections',   'h' => 'collections.php',  'cap' => null],
        ]],
        ['label' => 'Users & analytics', 'items' => [
            ['k' => 'analytics', 't' => 'Analytics',   'h' => 'analytics.php',    'cap' => null],
            ['k' => 'feedback',  't' => 'Feedback',    'h' => 'feedback.php',     'cap' => null,
             'n' => $c['feedback'], 'warn' => true],
            ['k' => 'users',     't' => 'Admin users', 'h' => 'users.php',        'cap' => 'users.manage'],
        ]],
        ['label' => 'System', 'items' => [
            ['k' => 'settings', 't' => 'Settings',     'h' => 'settings.php',     'cap' => 'settings.manage'],
            ['k' => 'i18n',     't' => 'Translations', 'h' => 'translations.php', 'cap' => 'settings.manage'],
            ['k' => 'activity', 't' => 'Activity log', 'h' => 'activity.php',     'cap' => null],
            ['k' => 'security', 't' => 'Security',     'h' => 'security.php',     'cap' => 'users.manage'],
            ['k' => 'backup',   't' => 'Backup',       'h' => 'backup.php',       'cap' => 'settings.manage'],
        ]],
    ];
}

/**
 * The counts that appear as pills in the sidebar.
 *
 * Each one is wrapped on its own: a missing table should cost you one pill, not
 * the whole navigation. Queried once per request and cached in a static.
 */
function admin_badge_counts(): array
{
    static $c = null;
    if ($c !== null) {
        return $c;
    }

    $count = static function (string $sql): int {
        try {
            return (int) qVal($sql);
        } catch (Throwable $e) {
            return 0;
        }
    };

    $c = [
        'places'   => $count('SELECT COUNT(*) FROM places WHERE deleted_at IS NULL'),
        'media'    => $count('SELECT COUNT(*) FROM place_images'),
        'feedback' => $count("SELECT COUNT(*) FROM feedback WHERE status = 'new'"),
        'todo'     => $count('SELECT COUNT(*) FROM v_stale_places')
                    + $count('SELECT COUNT(*) FROM v_missing_images')
                    + $count("SELECT COUNT(*) FROM feedback WHERE status = 'new'"),
    ];
    return $c;
}

// ---------------------------------------------------------------------------
// Page chrome
// ---------------------------------------------------------------------------

function admin_asset(string $path): string
{
    return asset('assets/' . ltrim($path, '/'));
}

/**
 * @param array{title?: string, nav?: string, sub?: string} $opts
 */
function admin_head(array $opts = []): void
{
    $title = $opts['title'] ?? 'Admin';
    $nav   = $opts['nav'] ?? '';
    $me    = admin_user();
    ?>
<!doctype html>
<html lang="en" dir="ltr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> · <?= e(SITE_NAME) ?> admin</title>
<meta name="robots" content="noindex, nofollow">
<link rel="stylesheet" href="<?= e(admin_asset('css/admin.css')) ?>">
</head>
<body class="ad">

<a class="ad-skip" href="#ad-main">Skip to content</a>

<aside class="ad-side" id="ad-side">
  <div class="ad-side-top">
    <a class="ad-brand" href="<?= e(APP_BASE) ?>/admin/index.php">
      <span class="ad-brand-mark" aria-hidden="true">Q</span>
      <span>
        <b><?= e(SITE_NAME) ?></b>
        <i>Admin</i>
      </span>
    </a>
    <button class="ad-side-toggle" type="button" data-ad-side aria-label="Toggle menu">☰</button>
  </div>

  <nav class="ad-nav" aria-label="Admin sections">
    <?php foreach (admin_nav() as $group): ?>
      <?php
      $items = array_filter($group['items'], static fn(array $i): bool => $i['cap'] === null || admin_can($i['cap']));
      if ($items === []) { continue; }
      ?>
      <p class="ad-nav-label"><?= e($group['label']) ?></p>
      <ul>
        <?php foreach ($items as $item): ?>
          <li>
            <a href="<?= e(APP_BASE . '/admin/' . $item['h']) ?>"
               class="<?= $nav === $item['k'] ? 'on' : '' ?>"
               <?= $nav === $item['k'] ? 'aria-current="page"' : '' ?>>
              <span><?= e($item['t']) ?></span>
              <?php if (!empty($item['n'])): ?>
                <em class="ad-pill<?= !empty($item['warn']) ? ' warn' : '' ?>"><?= e((string) $item['n']) ?></em>
              <?php endif; ?>
            </a>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endforeach; ?>
  </nav>

  <div class="ad-side-foot">
    <a href="<?= e(APP_BASE) ?>/index.php" target="_blank" rel="noopener">View site ↗</a>
  </div>
</aside>

<div class="ad-body">
  <header class="ad-top">
    <div class="ad-top-who">
      <b><?= e(admin_name()) ?></b>
      <span class="ad-role"><?= e(admin_role() === 'admin' ? 'Admin' : 'Editor') ?></span>
    </div>
    <div class="ad-top-act">
      <?php if (admin_is_admin()): ?>
        <a class="ad-btn ghost" href="<?= e(APP_BASE) ?>/admin/users.php?id=<?= (int) admin_id() ?>">My account</a>
      <?php endif; ?>
      <form method="post" action="<?= e(APP_BASE) ?>/admin/logout.php" class="ad-inline">
        <?= csrf_field() ?>
        <button class="ad-btn ghost" type="submit">Sign out</button>
      </form>
    </div>
  </header>

  <main class="ad-main" id="ad-main">
    <?php
    $flash = admin_take_flash();
    if ($flash !== null):
        ?>
        <div class="ad-flash <?= e($flash['kind']) ?>" role="status">
          <?= e($flash['msg']) ?>
          <button type="button" class="ad-flash-x" aria-label="Dismiss" data-ad-dismiss>×</button>
        </div>
        <?php
    endif;

    if ($me === null) {
        // admin_head() is only reachable after require_admin(), so this is a
        // belt-and-braces guard rather than a state we expect to reach.
        echo '<p class="ad-lede">Not signed in.</p>';
    }
}

function admin_foot(array $opts = []): void
{
    ?>
  </main>

  <footer class="ad-foot">
    <span><?= e(SITE_NAME) ?> admin</span>
    <span>·</span>
    <span>Signed in as <?= e(admin_name()) ?></span>
  </footer>
</div>

<script src="<?= e(admin_asset('js/admin.js')) ?>" defer></script>
<?= $opts['scripts'] ?? '' ?>
</body>
</html>
    <?php
}

// ---------------------------------------------------------------------------
// Flash messages
// ---------------------------------------------------------------------------

/**
 * Queue a message for the next page. Stored in the session rather than passed
 * in the query string, so a refresh cannot re-show it and a link cannot fake it.
 */
function admin_flash(string $msg, string $kind = 'ok'): void
{
    $_SESSION['ad_flash'] = ['msg' => $msg, 'kind' => $kind];
}

function admin_take_flash(): ?array
{
    $f = $_SESSION['ad_flash'] ?? null;
    unset($_SESSION['ad_flash']);
    return is_array($f) ? $f : null;
}

function admin_back(string $to, string $msg = '', string $kind = 'ok'): never
{
    if ($msg !== '') {
        admin_flash($msg, $kind);
    }
    header('Location: ' . $to);
    exit;
}

/** A panel URL with its query string already encoded. */
function admin_url(string $page, array $q = []): string
{
    $q = array_filter($q, static fn($v): bool => $v !== null && $v !== '');
    return APP_BASE . '/admin/' . ltrim($page, '/') . ($q === [] ? '' : '?' . http_build_query($q));
}

// ---------------------------------------------------------------------------
// Request helpers
// ---------------------------------------------------------------------------

/** A trimmed string from POST, with arrays rejected rather than cast. */
function post_str(string $key, string $default = ''): string
{
    $v = $_POST[$key] ?? null;
    return is_string($v) ? trim($v) : $default;
}

function post_int(string $key, int $default = 0): int
{
    $v = $_POST[$key] ?? null;
    return is_string($v) && is_numeric($v) ? (int) $v : $default;
}

function post_bool(string $key): int
{
    return ($_POST[$key] ?? '') === '1' ? 1 : 0;
}

function get_str(string $key, string $default = ''): string
{
    $v = $_GET[$key] ?? null;
    return is_string($v) ? trim($v) : $default;
}

function get_int(string $key, int $default = 0): int
{
    $v = $_GET[$key] ?? null;
    return is_string($v) && is_numeric($v) ? (int) $v : $default;
}

/**
 * Every POST that changes something must come through here first.
 * Rejects on a bad token rather than silently continuing.
 */
function admin_require_csrf(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return;
    }
    $token = $_POST['csrf'] ?? null;
    if (!is_string($token) || !csrf_ok($token)) {
        http_response_code(419);
        admin_head(['title' => 'Session expired']);
        ?>
        <div class="ad-panel narrow">
          <h1>That form had expired</h1>
          <p class="ad-lede">
            The security token did not match. This happens if the page was open a
            long time, or if the session was reset. Nothing was saved.
          </p>
          <p><a class="ad-btn" href="<?= e($_SERVER['HTTP_REFERER'] ?? admin_url('index.php')) ?>">Go back and try again</a></p>
        </div>
        <?php
        admin_foot();
        exit;
    }
}

// ---------------------------------------------------------------------------
// Page furniture
// ---------------------------------------------------------------------------

/** @param array<int, array{href: string, label: string, kind?: string}> $actions */
function ad_title(string $title, string $sub = '', array $actions = []): void
{
    ?>
    <div class="ad-head">
      <div>
        <h1><?= e($title) ?></h1>
        <?php if ($sub !== ''): ?><p class="ad-lede"><?= e($sub) ?></p><?php endif; ?>
      </div>
      <?php if ($actions !== []): ?>
        <div class="ad-head-act">
          <?php foreach ($actions as $a): ?>
            <a class="ad-btn <?= e($a['kind'] ?? '') ?>" href="<?= e($a['href']) ?>"><?= e($a['label']) ?></a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
    <?php
}

function ad_panel_open(string $class = ''): void
{
    echo '<div class="ad-panel ' . e($class) . '">';
}

function ad_panel_close(): void
{
    echo '</div>';
}

/** An explanatory or warning strip. Kinds: info, warn, bad, ok. */
function ad_note(string $text, string $kind = 'info'): void
{
    echo '<p class="ad-note ' . e($kind) . '">' . $text . '</p>';
}

function ad_empty(string $text, string $action = ''): void
{
    echo '<p class="ad-empty">' . e($text) . ($action !== '' ? ' ' . $action : '') . '</p>';
}

/**
 * A data table.
 *
 * @param array<int, array{k: string, t: string, w?: string, fmt?: callable, num?: bool}> $cols
 * @param array<int, array<string, mixed>> $rows
 * @param array{bulk?: bool, id?: string, row_link?: callable, empty?: string} $opts
 */
function ad_table(array $cols, array $rows, array $opts = []): void
{
    if ($rows === []) {
        ad_empty($opts['empty'] ?? 'Nothing here yet.');
        return;
    }

    $bulk = !empty($opts['bulk']);
    $idKey = $opts['id'] ?? 'id';
    $link  = $opts['row_link'] ?? null;

    echo '<div class="ad-tablewrap"><table class="ad-table"><thead><tr>';
    if ($bulk) {
        echo '<th class="ad-check"><input type="checkbox" data-ad-all aria-label="Select all"></th>';
    }
    foreach ($cols as $c) {
        $w = !empty($c['w']) ? ' style="inline-size:' . e($c['w']) . '"' : '';
        $cls = !empty($c['num']) ? ' class="num"' : '';
        echo '<th' . $w . $cls . '>' . e($c['t']) . '</th>';
    }
    echo '</tr></thead><tbody>';

    foreach ($rows as $r) {
        $rid = (int) ($r[$idKey] ?? 0);
        echo '<tr>';
        if ($bulk) {
            echo '<td class="ad-check"><input type="checkbox" name="ids[]" value="' . $rid . '" aria-label="Select row"></td>';
        }
        foreach ($cols as $c) {
            $cls = !empty($c['num']) ? ' class="num"' : '';
            echo '<td' . $cls . '>';
            if (isset($c['fmt']) && is_callable($c['fmt'])) {
                echo $c['fmt']($r);
            } elseif ($link !== null && $c === $cols[0]) {
                // The first cell links to the row's editor unless a formatter
                // has taken it over — a table where the name is not clickable
                // is a table people fight with.
                echo '<a class="ad-rowlink" href="' . e($link($r)) . '">' . e((string) ($r[$c['k']] ?? '')) . '</a>';
            } else {
                echo e((string) ($r[$c['k']] ?? ''));
            }
            echo '</td>';
        }
        echo '</tr>';
    }

    echo '</tbody></table></div>';
}

/**
 * The bulk-action bar. Lives inside the same form as the table, which is what
 * makes the checkboxes and the action reach the server together.
 *
 * @param array<string, string> $actions value => label
 */
function ad_bulk_bar(array $actions, string $note = ''): void
{
    ?>
    <div class="ad-bulk">
      <label class="ad-sr" for="ad-bulk-act">Bulk action</label>
      <select name="bulk" id="ad-bulk-act">
        <option value="">Choose an action…</option>
        <?php foreach ($actions as $v => $label): ?>
          <option value="<?= e($v) ?>"><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
      <button class="ad-btn" type="submit" data-ad-confirm="Apply this to every selected row?">Apply</button>
      <?php if ($note !== ''): ?><span class="ad-muted"><?= e($note) ?></span><?php endif; ?>
    </div>
    <?php
}

/** Page links that keep the current filters. */
function ad_pager(int $total, int $per, int $page, array $q = []): void
{
    $pages = (int) max(1, ceil($total / max(1, $per)));
    if ($pages < 2) {
        return;
    }
    $page = max(1, min($page, $pages));

    $url = static function (int $p) use ($q): string {
        return '?' . http_build_query($q + ['p' => $p]);
    };

    // A window of pages around the current one, so a hundred-page list does not
    // print a hundred links.
    $from = max(1, $page - 3);
    $to   = min($pages, $page + 3);

    echo '<nav class="ad-pager" aria-label="Pagination">';
    if ($page > 1) {
        echo '<a href="' . e($url($page - 1)) . '" rel="prev">‹ Prev</a>';
    }
    if ($from > 1) {
        echo '<a href="' . e($url(1)) . '">1</a><span class="ad-gap">…</span>';
    }
    for ($p = $from; $p <= $to; $p++) {
        echo $p === $page
            ? '<span class="on" aria-current="page">' . $p . '</span>'
            : '<a href="' . e($url($p)) . '">' . $p . '</a>';
    }
    if ($to < $pages) {
        echo '<span class="ad-gap">…</span><a href="' . e($url($pages)) . '">' . $pages . '</a>';
    }
    if ($page < $pages) {
        echo '<a href="' . e($url($page + 1)) . '" rel="next">Next ›</a>';
    }
    echo '</nav>';
}

/** A compact search + filter bar that GETs back to the same page. */
function ad_filters(string $action, array $fields, array $current, string $submit = 'Filter'): void
{
    ?>
    <form class="ad-filters" method="get" action="<?= e($action) ?>">
      <?php foreach ($fields as $f): ?>
        <label>
          <span><?= e($f['t']) ?></span>
          <?php if (($f['type'] ?? 'text') === 'select'): ?>
            <select name="<?= e($f['k']) ?>">
              <?php foreach ($f['options'] as $v => $label): ?>
                <option value="<?= e((string) $v) ?>"
                  <?= (string) ($current[$f['k']] ?? '') === (string) $v ? 'selected' : '' ?>>
                  <?= e($label) ?>
                </option>
              <?php endforeach; ?>
            </select>
          <?php else: ?>
            <input type="search" name="<?= e($f['k']) ?>"
                   value="<?= e((string) ($current[$f['k']] ?? '')) ?>"
                   placeholder="<?= e($f['ph'] ?? '') ?>">
          <?php endif; ?>
        </label>
      <?php endforeach; ?>
      <button class="ad-btn" type="submit"><?= e($submit) ?></button>
      <a class="ad-btn ghost" href="<?= e(strtok($_SERVER['REQUEST_URI'] ?? '', '?')) ?>">Reset</a>
    </form>
    <?php
}

// ---------------------------------------------------------------------------
// Form fields
// ---------------------------------------------------------------------------

/**
 * One labelled control.
 *
 * @param array{k: string, t: string, type?: string, help?: string, options?: array,
 *              rows?: int, required?: bool, ph?: string, min?: int, max?: int,
 *              step?: string, mono?: bool, wide?: bool} $spec
 */
function ad_field(array $spec, mixed $value): void
{
    $k    = $spec['k'];
    $type = $spec['type'] ?? 'text';
    $id   = 'f_' . preg_replace('/[^a-z0-9_]/i', '_', $k);
    $wide = !empty($spec['wide']) || in_array($type, ['textarea', 'json', 'editor'], true);

    echo '<div class="ad-field' . ($wide ? ' wide' : '') . '">';
    echo '<label for="' . e($id) . '">' . e($spec['t']);
    if (!empty($spec['required'])) {
        echo ' <span class="ad-req" title="Required">*</span>';
    }
    echo '</label>';

    switch ($type) {
        case 'textarea':
        case 'editor':
            echo '<textarea id="' . e($id) . '" name="' . e($k) . '" rows="' . (int) ($spec['rows'] ?? 4) . '"'
               . (!empty($spec['mono']) ? ' class="mono"' : '')
               . ' placeholder="' . e($spec['ph'] ?? '') . '">' . e((string) $value) . '</textarea>';
            break;

        case 'select':
            echo '<select id="' . e($id) . '" name="' . e($k) . '">';
            foreach (($spec['options'] ?? []) as $v => $label) {
                $sel = (string) $value === (string) $v ? ' selected' : '';
                echo '<option value="' . e((string) $v) . '"' . $sel . '>' . e((string) $label) . '</option>';
            }
            echo '</select>';
            break;

        case 'checkbox':
            echo '<label class="ad-switch"><input type="checkbox" id="' . e($id) . '" name="' . e($k) . '" value="1"'
               . ((int) $value === 1 ? ' checked' : '') . '> <span>' . e($spec['on'] ?? 'Yes') . '</span></label>';
            break;

        case 'color':
            echo '<span class="ad-colorwrap">'
               . '<input type="color" id="' . e($id) . '" name="' . e($k) . '" value="' . e((string) $value) . '">'
               . '<input type="text" class="mono" name="' . e($k) . '_text" value="' . e((string) $value) . '">'
               . '</span>';
            break;

        case 'image':
            echo '<span class="ad-imgpick">'
               . '<input type="text" class="mono" id="' . e($id) . '" name="' . e($k) . '" value="' . e((string) $value) . '" placeholder="uploads/…">'
               . '<button class="ad-btn ghost" type="button" data-ad-pick="' . e($k) . '">Choose</button>'
               . '</span>';
            if ((string) $value !== '') {
                echo '<img class="ad-thumb" src="' . e(APP_BASE . '/' . ltrim((string) $value, '/')) . '" alt="">';
            }
            break;

        case 'file':
            // A multi-file input needs name="files[]" so PHP hands back parallel
            // arrays instead of silently keeping only the last file.
            $multi = !empty($spec['multi']);
            echo '<input type="file" id="' . e($id) . '" name="' . e($k) . ($multi ? '[]' : '') . '"'
               . ($multi ? ' multiple' : '')
               . ' accept="' . e($spec['accept'] ?? 'image/*') . '">';
            break;

        case 'json':
            echo '<textarea id="' . e($id) . '" name="' . e($k) . '" rows="' . (int) ($spec['rows'] ?? 5) . '" class="mono" spellcheck="false">'
               . e((string) $value) . '</textarea>';
            break;

        case 'number':
            echo '<input type="number" id="' . e($id) . '" name="' . e($k) . '" value="' . e((string) $value) . '"'
               . (isset($spec['min']) ? ' min="' . (int) $spec['min'] . '"' : '')
               . (isset($spec['max']) ? ' max="' . (int) $spec['max'] . '"' : '')
               . (isset($spec['step']) ? ' step="' . e($spec['step']) . '"' : '')
               . '>';
            break;

        case 'date':
            // MySQL hands back a full timestamp; the control wants YYYY-MM-DD.
            $d = $value === null || $value === '' ? '' : substr((string) $value, 0, 10);
            echo '<input type="date" id="' . e($id) . '" name="' . e($k) . '" value="' . e($d) . '">';
            break;

        default:
            $t = in_array($type, ['email', 'url', 'password', 'search'], true) ? $type : 'text';
            echo '<input type="' . e($t) . '" id="' . e($id) . '" name="' . e($k) . '" value="' . e((string) $value) . '"'
               . (!empty($spec['ph']) ? ' placeholder="' . e($spec['ph']) . '"' : '')
               . (!empty($spec['mono']) ? ' class="mono"' : '')
               . (!empty($spec['maxlen']) ? ' maxlength="' . (int) $spec['maxlen'] . '"' : '')
               . '>';
    }

    if (!empty($spec['help'])) {
        echo '<span class="ad-help">' . $spec['help'] . '</span>';
    }
    echo '</div>';
}

/**
 * Open a record form. Every admin form is a POST with a CSRF token.
 *
 * @param bool $multipart Set this for any form with a file input. Without it
 *                        the browser sends only the filename and $_FILES stays
 *                        empty, which looks exactly like "no file was chosen".
 */
function ad_form_open(string $action, string $title, string $sub = '', bool $multipart = false): void
{
    ?>
    <form class="ad-form" method="post" action="<?= e($action) ?>"<?= $multipart ? ' enctype="multipart/form-data"' : '' ?>>
      <?= csrf_field() ?>
      <?php if ($title !== ''): ?>
        <h2 class="ad-form-title"><?= e($title) ?></h2>
      <?php endif; ?>
      <?php if ($sub !== ''): ?>
        <p class="ad-help"><?= e($sub) ?></p>
      <?php endif; ?>
      <div class="ad-grid">
    <?php
}

/**
 * @param string $danger    Label for the destructive button, or '' for none.
 * @param string $dangerMsg The confirm text. Defaults to the soft-delete
 *                          wording, because that is the common case; a table
 *                          with no bin passes its own, blunter sentence.
 */
function ad_form_close(string $save = 'Save', string $back = '', string $danger = '', string $dangerMsg = ''): void
{
    if ($danger !== '' && $dangerMsg === '') {
        $dangerMsg = 'Move this to the bin? You can restore it afterwards.';
    }
    ?>
      </div>
      <div class="ad-form-act">
        <button class="ad-btn primary" type="submit"><?= e($save) ?></button>
        <?php if ($back !== ''): ?>
          <a class="ad-btn ghost" href="<?= e($back) ?>">Cancel</a>
        <?php endif; ?>
        <?php if ($danger !== ''): ?>
          <span class="ad-spacer"></span>
          <button class="ad-btn danger" type="submit" name="do" value="delete"
                  formnovalidate data-ad-confirm="<?= e($dangerMsg) ?>"><?= e($danger) ?></button>
        <?php endif; ?>
      </div>
    </form>
    <?php
}

/** A status chip. */
function ad_chip(string $label, string $kind = ''): string
{
    return '<span class="ad-chip ' . e($kind) . '">' . e($label) . '</span>';
}

/** The status chip for a row with a status enum. */
function ad_status_chip(string $status): string
{
    $kind = ['published' => 'ok', 'draft' => 'warn', 'archived' => 'mute'][$status] ?? 'mute';
    return ad_chip($status, $kind);
}

/** "3 days ago" / "on 12 Mar 2026" — for the staleness columns. */
function ad_when(?string $ts, bool $relative = true): string
{
    if ($ts === null || $ts === '' || str_starts_with((string) $ts, '0000')) {
        return '—';
    }
    $t = strtotime((string) $ts);
    if ($t === false) {
        return '—';
    }
    if (!$relative) {
        return date('j M Y', $t);
    }
    $d = (int) floor((time() - $t) / 86400);
    if ($d <= 0) return 'today';
    if ($d === 1) return 'yesterday';
    if ($d < 30) return $d . ' days ago';
    if ($d < 365) return (int) floor($d / 30) . ' months ago';
    return (int) floor($d / 365) . 'y ago';
}
