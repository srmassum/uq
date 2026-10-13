<?php
/**
 * Translations — the UI strings, in every language the site might speak.
 *
 * The English file that ships in inc/lang/en.php is the source list: this screen
 * shows each key with its English text beside a box for the language being
 * worked on, and stores what is typed in the translations table. inc/i18n.php
 * merges those rows over the language file, so anything saved here is live on
 * the next page load — no file upload, no deploy.
 *
 * What that means for a language the site does not speak yet (Hindi, Urdu,
 * Bengali): the work can be done now and it is not wasted. The moment the
 * language is switched on in inc/config.php, every translated key appears and
 * every untranslated one falls back to English — which is the correct way for a
 * half-finished translation to behave.
 *
 * What it does NOT cover: content. Place names and descriptions come from the
 * _en/_ar column pairs through pick(), which reads two columns and only two.
 * Rows stored here against a place are a head start for later, not something the
 * site can display today — and the coverage table below says so rather than
 * quietly counting them as done.
 */

declare(strict_types=1);

require_once __DIR__ . '/inc/panel.php';

require_admin('settings.manage');
admin_require_csrf();

/**
 * Every language this screen offers, and whether the site can show it yet.
 *
 * "Live" means the code is in SUPPORTED_LANGS, so it appears in the public
 * language switcher and current_lang() will return it.
 */
function tr_langs(): array
{
    $out = [];
    foreach (SUPPORTED_LANGS as $code) {
        $out[$code] = ['label' => tr_lang_label($code), 'live' => true];
    }
    foreach (['hi' => 'हिन्दी (Hindi)', 'ur' => 'اردو (Urdu)', 'bn' => 'বাংলা (Bengali)'] as $code => $label) {
        if (!isset($out[$code])) {
            $out[$code] = ['label' => $label, 'live' => false];
        }
    }
    return $out;
}

function tr_lang_label(string $code): string
{
    return ['en' => 'English', 'ar' => 'العربية (Arabic)', 'hi' => 'हिन्दी (Hindi)',
            'ur' => 'اردو (Urdu)', 'bn' => 'বাংলা (Bengali)'][$code] ?? $code;
}

/** The English source strings — the list of keys that exist to translate. */
function tr_source(): array
{
    static $en = null;
    if ($en === null) {
        $file = __DIR__ . '/../inc/lang/en.php';
        $en = is_file($file) ? (array) require $file : [];
    }
    return $en;
}

// ------------------------------------------------------------------ actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $lang = post_str('lang');
    $langs = tr_langs();

    if (!isset($langs[$lang]) || $lang === 'en') {
        admin_back(admin_url('translations.php'), 'That is not a language this screen can write to.', 'bad');
    }

    $source = tr_source();
    $keys   = $_POST['k'] ?? [];
    $vals   = $_POST['v'] ?? [];

    if (!is_array($keys) || !is_array($vals)) {
        admin_back(admin_url('translations.php', ['lang' => $lang]), 'That form did not arrive intact.', 'bad');
    }

    $saved   = 0;
    $cleared = 0;
    $skipped = 0;

    foreach ($keys as $i => $key) {
        if (!is_string($key) || !is_string($vals[$i] ?? null)) {
            $skipped++;
            continue;
        }
        // The key must be one this site actually uses. A key that is not in the
        // English file is a typo at best and an attempt to write arbitrary rows
        // at worst, so it is refused rather than stored.
        $key = (string) $key;
        if (!array_key_exists($key, $source)) {
            $skipped++;
            continue;
        }

        $value = trim($vals[$i]);

        // Longer than any UI string has business being. The column is TEXT, so
        // this is about not letting the panel be used as free storage.
        if (mb_strlen($value) > 2000) {
            $skipped++;
            continue;
        }

        try {
            if ($value === '') {
                // Empty means "no translation" — remove the row so the language
                // file (or English) shows through again.
                $n = q('DELETE FROM translations
                         WHERE entity_type = ? AND entity_id = 0 AND field = ? AND lang = ?',
                       ['ui', $key, $lang])->rowCount();
                $cleared += $n;
            } else {
                q('INSERT INTO translations (entity_type, entity_id, field, lang, value)
                   VALUES (?, 0, ?, ?, ?)
                   ON DUPLICATE KEY UPDATE value = VALUES(value)',
                  ['ui', $key, $lang, $value]);
                $saved++;
            }
        } catch (Throwable $e) {
            error_log('translations save: ' . $e->getMessage());
            $skipped++;
        }
    }

    log_activity('translations', 'save', 'ui', null, null,
        ['lang' => $lang, 'saved' => $saved, 'cleared' => $cleared, 'skipped' => $skipped]);

    $msg = $saved . ' string' . ($saved === 1 ? '' : 's') . ' saved';
    if ($cleared > 0) { $msg .= ', ' . $cleared . ' cleared'; }
    if ($skipped > 0) { $msg .= ', ' . $skipped . ' skipped'; }
    $msg .= '.';

    if (!$langs[$lang]['live']) {
        $msg .= ' ' . tr_lang_label($lang) . ' is not switched on yet, so nobody sees these until '
              . 'it is added to SUPPORTED_LANGS.';
    }

    admin_back(admin_url('translations.php', ['lang' => $lang, 'q' => get_str('q')]), $msg, 'ok');
}

// -------------------------------------------------------------------- reads
$langs = tr_langs();
$lang  = get_str('lang', 'ar');
if (!isset($langs[$lang]) || $lang === 'en') {
    $lang = isset($langs['ar']) ? 'ar' : (string) array_key_first($langs);
}

$source = tr_source();
$total  = count($source);

$filter = get_str('q');
$page   = max(1, get_int('p', 1));
$per    = 30;

// The keys on this page, in the file's own order — the file is grouped by area,
// which is a far better reading order than alphabetical.
$matched = [];
foreach ($source as $k => $en) {
    if ($filter === ''
        || stripos((string) $k, $filter) !== false
        || stripos((string) $en, $filter) !== false) {
        $matched[] = (string) $k;
    }
}
$count   = count($matched);
$pages   = (int) max(1, ceil($count / $per));
$page    = min($page, $pages);
$onPage  = array_slice($matched, ($page - 1) * $per, $per);

$stored  = [];
$doneUi  = 0;
$byType  = [];
$readError = '';
try {
    foreach (qAll("SELECT field, value FROM translations
                    WHERE entity_type = 'ui' AND entity_id = 0 AND lang = ?", [$lang]) as $r) {
        if ((string) $r['value'] !== '') {
            $stored[(string) $r['field']] = (string) $r['value'];
            $doneUi++;
        }
    }
    // Content rows, per type. Counted honestly: these are stored but not shown.
    foreach (qAll("SELECT entity_type, COUNT(*) n FROM translations
                    WHERE lang = ? AND entity_type <> 'ui'
                    GROUP BY entity_type ORDER BY n DESC", [$lang]) as $r) {
        $byType[(string) $r['entity_type']] = (int) $r['n'];
    }
} catch (Throwable $e) {
    error_log('translations.php: ' . $e->getMessage());
    $readError = $e->getMessage();
}

$pct = $total === 0 ? 0 : (int) round(100 * $doneUi / $total);

admin_head(['title' => 'Translations', 'nav' => 'i18n']);

ad_title(
    'Translations',
    'The interface strings — buttons, headings, the words around the content. Saved changes are live on the next page load.',
    [['href' => admin_url('settings.php'), 'label' => 'Site settings', 'kind' => 'ghost']]
);

if ($readError !== '') {
    ad_note('Could not read the translations table: ' . e($readError) . '. Has <code>sql/schema.sql</code> been imported?', 'bad');
}

// ------------------------------------------------------------------ language tabs
echo '<p>';
foreach ($langs as $code => $info) {
    if ($code === 'en') {
        // Not a tab: English is the source list, edited in the language file
        // rather than here, so it is shown as a label rather than as a link.
        echo '<span class="ad-btn ghost" style="margin-inline-end:6px" title="English is the source; it lives in inc/lang/en.php.">'
           . e($info['label']) . ' <span class="ad-chip mute">source</span></span>';
        continue;
    }
    $on = $code === $lang;
    echo '<a class="ad-btn ' . ($on ? 'primary' : 'ghost') . '" style="margin-inline-end:6px" href="'
       . e(admin_url('translations.php', ['lang' => $code])) . '">'
       . e($info['label'])
       . ($info['live'] ? '' : ' <span class="ad-chip warn">off</span>')
       . '</a>';
}
echo '</p>';

$info = $langs[$lang];
?>

<div class="ad-panel">
  <h2><?= e($info['label']) ?></h2>
  <div class="ad-stats" style="margin-block-end:14px">
    <div class="ad-stat<?= $pct === 100 ? ' good' : '' ?>"><b><?= $pct ?>%</b><span>Translated</span></div>
    <div class="ad-stat"><b><?= number_format($doneUi) ?></b><span>Strings written</span></div>
    <div class="ad-stat"><b><?= number_format($total) ?></b><span>Strings in total</span></div>
    <div class="ad-stat<?= $info['live'] ? ' good' : ' warn' ?>">
      <b><?= $info['live'] ? 'Live' : 'Off' ?></b><span>Shown to visitors</span>
    </div>
  </div>

  <?php if ($doneUi > 0 && $doneUi < $total): ?>
    <p class="ad-help" style="margin-block-start:0">
      Untranslated strings fall back to English, so the site reads correctly at any percentage —
      it just reads partly in English.
    </p>
  <?php endif; ?>
</div>

<?php if (!$info['live']): ?>
<div class="ad-panel">
  <h2>Switching <?= e($info['label']) ?> on</h2>
  <p style="margin-block-start:0">
    Everything written here is stored and safe, but no visitor sees it yet. Turning the language on
    is one line in <code>inc/config.php</code>:
  </p>
  <pre class="mono" style="padding:12px;border:1px solid var(--ad-line);border-radius:9px;background:var(--ad-bg);font-size:12.5px;overflow:auto">const SUPPORTED_LANGS = ['en', 'ar'<?= $lang === 'ur' ? ", 'ur'" : ", '" . e($lang) . "'" ?>];<?php echo $lang === 'ur' ? "\n\n// Urdu reads right to left.\nconst RTL_LANGS = ['ar', 'ur'];" : ''; ?></pre>
  <ul class="ad-list">
    <li>
      <span>Language switcher</span>
      <span class="ad-muted">adds a chip to the header on every page — the labels live in <code>layout.php</code></span>
    </li>
    <li>
      <span>Fonts</span>
      <span class="ad-muted"><?= $lang === 'hi' ? 'Devanagari needs Noto Sans Devanagari'
            : ($lang === 'bn' ? 'Bengali needs Noto Sans Bengali'
            : ($lang === 'ur' ? 'Noto Kufi Arabic already covers Urdu script'
            : 'check the font covers the script')) ?> — the Google Fonts link is in <code>inc/layout.php</code></span>
    </li>
    <?php if ($lang === 'ur'): ?>
      <li>
        <span>Direction</span>
        <span class="ad-muted">adding <code>ur</code> to <code>RTL_LANGS</code> flips the whole layout; the CSS uses logical properties throughout, so nothing needs overriding</span>
      </li>
    <?php endif; ?>
  </ul>
</div>
<?php endif; ?>

<div class="ad-panel">
  <h2>Interface strings</h2>

  <form class="ad-filters" method="get" action="<?= e(admin_url('translations.php')) ?>">
    <input type="hidden" name="lang" value="<?= e($lang) ?>">
    <label>
      <span>Find</span>
      <input type="search" name="q" value="<?= e($filter) ?>" placeholder="key or English text">
    </label>
    <button class="ad-btn" type="submit">Search</button>
    <?php if ($filter !== ''): ?>
      <a class="ad-btn ghost" href="<?= e(admin_url('translations.php', ['lang' => $lang])) ?>">Clear</a>
    <?php endif; ?>
    <span class="ad-muted"><?= number_format($count) ?> of <?= number_format($total) ?> strings</span>
  </form>

  <?php if ($onPage === []): ?>
    <?php ad_empty('No string matches that.'); ?>
  <?php else: ?>
    <form class="ad-form" method="post" action="<?= e(admin_url('translations.php', ['lang' => $lang, 'q' => $filter, 'p' => $page])) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="lang" value="<?= e($lang) ?>">

      <div class="ad-tablewrap">
        <table class="ad-table">
          <thead>
            <tr>
              <th style="inline-size:200px">Key</th>
              <th style="inline-size:34%">English</th>
              <th><?= e($info['label']) ?></th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($onPage as $i => $key): ?>
            <?php
            $en     = (string) ($source[$key] ?? '');
            $val    = $stored[$key] ?? '';
            $tid    = 'tr_' . $page . '_' . $i;
            // A translation with no placeholders where English has them, or the
            // other way round, is the most common way a translated page breaks —
            // :n and :total quietly become literal text. Flagged, not blocked:
            // some languages legitimately reorder or drop a count.
            $enPh   = preg_match_all('/:[a-z_]+/i', $en, $m1);
            $valPh  = preg_match_all('/:[a-z_]+/i', $val, $m2);
            $phWarn = $val !== '' && $enPh !== $valPh;
            ?>
            <tr>
              <td>
                <span class="mono" style="font-size:12.5px"><?= e($key) ?></span>
                <?php if ($phWarn): ?>
                  <span class="ad-sub"><?= ad_chip('placeholders differ', 'warn') ?></span>
                <?php endif; ?>
              </td>
              <td>
                <label class="ad-sr" for="<?= e($tid) ?>_src">English</label>
                <span id="<?= e($tid) ?>_src" dir="auto"><?= e($en) ?></span>
                <?php if ($phWarn): ?>
                  <span class="ad-sub mono"><?= e(implode(' ', $m1[0] ?: ['(none)'])) ?></span>
                <?php endif; ?>
              </td>
              <td>
                <label class="ad-sr" for="<?= e($tid) ?>"><?= e($info['label']) ?></label>
                <input type="hidden" name="k[]" value="<?= e($key) ?>">
                <input type="text" id="<?= e($tid) ?>" name="v[]" value="<?= e($val) ?>"
                       dir="auto" style="inline-size:100%;padding:8px 11px;border-radius:9px;border:1px solid var(--ad-line-2);background:var(--ad-bg);color:var(--ad-ink);font:inherit;font-size:14px">
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <div class="ad-form-act">
        <button class="ad-btn primary" type="submit">Save these <?= count($onPage) ?> strings</button>
        <span class="ad-muted">Clearing a box removes the translation, so English shows through again.</span>
      </div>
    </form>

    <?php
    // This form posts only the rows on screen, so the pager has to carry the
    // filter with it — otherwise page two would look empty and confuse.
    ad_pager($count, $per, $page, ['lang' => $lang, 'q' => $filter]);
    ?>
  <?php endif; ?>
</div>

<div class="ad-panel">
  <h2>Content, which is a separate job</h2>
  <p style="margin-block-start:0">
    Names, summaries and descriptions are not in this screen. They live in the database as
    <code>_en</code>/<code>_ar</code> column pairs and are read by <code>pick()</code> in
    <code>inc/i18n.php</code> — which serves exactly those two languages. Translating a place into a
    third language needs <code>pick()</code> to learn how to consult this table, per call site.
  </p>
  <?php if ($byType === []): ?>
    <?php ad_empty('No content translations are stored for ' . $info['label'] . ' yet.'); ?>
  <?php else: ?>
    <ul class="ad-list">
      <?php foreach ($byType as $type => $n): ?>
        <li>
          <span><?= e($type) ?></span>
          <span class="ad-muted"><?= number_format($n) ?> row<?= $n === 1 ? '' : 's' ?> stored — not shown on the site yet</span>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</div>

<?php
admin_foot();
