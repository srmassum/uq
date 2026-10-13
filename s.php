<?php
/**
 * The public share page. One short URL: s.php?c=CODE
 *
 * Everything about this page exists to make a pasted link look worth clicking:
 * the person's own rendered map is the og:image, the title names them and their
 * score, and the only real call to action is "make your own". That loop is the
 * growth mechanism, so the OG tags are the most important markup here — the
 * visible page is almost a bonus.
 */

declare(strict_types=1);

require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/mapdata.php';
require __DIR__ . '/inc/layout.php';

require_map_data();

$code = strtoupper(trim((string) ($_GET['c'] ?? '')));

$row = null;
if (preg_match('/^[' . CODE_ALPHABET . ']{' . SHORT_CODE_LEN . '}$/', $code)) {
    $row = qOne(
        'SELECT id, code, display_name, theme, personality, level_slug, mun_count, loc_count, views
           FROM saved_maps
          WHERE code = ? AND status = \'active\'',
        [$code]
    );
}

// ---------------------------------------------------------------------------
// Not found
// ---------------------------------------------------------------------------

if ($row === null) {
    http_response_code(404);
    page_head(['title' => t('sm.not_found'), 'noindex' => true]);
    ?>
    <section class="wrap narrow shared">
      <div class="panel">
        <h1><?= e(t('sm.not_found')) ?></h1>
        <p class="lede" style="margin-block:14px 22px"><?= e(t('sm.cta_sub')) ?></p>
        <a class="btn primary" href="<?= e(APP_BASE) ?>/index.php"><?= e(t('sm.cta_btn')) ?></a>
      </div>
    </section>
    <?php
    page_foot();
    exit;
}

// ---------------------------------------------------------------------------
// Count the view
//
// Best-effort: a failed counter must never cost the visitor the page, so both
// writes are allowed to throw and be swallowed.
// ---------------------------------------------------------------------------

try {
    q('UPDATE saved_maps SET views = views + 1 WHERE id = ?', [(int) $row['id']]);
    q(
        'INSERT INTO card_events (saved_map_id, event, format, theme, referrer, ip_hash)
         VALUES (?, \'view\', ?, ?, ?, ?)',
        [
            (int) $row['id'],
            'page',
            (string) $row['theme'],
            mb_substr((string) ($_SERVER['HTTP_REFERER'] ?? ''), 0, 255),
            ip_hash(),
        ]
    );
} catch (Throwable $e) {
    error_log('s.php view counter: ' . $e->getMessage());
}

// ---------------------------------------------------------------------------
// Render
// ---------------------------------------------------------------------------

$total   = total_markable();
$name    = trim((string) ($row['display_name'] ?? ''));
$done    = (int) $row['loc_count'];
$image   = site_url('img.php?c=' . $row['code']);
$pageUrl = site_url('s.php?c=' . $row['code']);

// Rounded, not floored: 47.6% is nearer 48 than 47, and the number is a
// headline rather than a measurement. Built once — the page prints it three
// times (description, byline, alt text) and they must never disagree.
$pct      = $total > 0 ? (int) round($done / $total * 100) : 0;
$progress = t('sm.progress', [
    'done'  => num($done),
    'total' => num($total),
    'pct'   => num($pct),
]);

$title = $name !== ''
    ? t('sm.title', ['name' => $name])
    : t('sm.title_anon');

$desc = $progress;

page_head([
    'title'    => $title,
    'desc'     => $desc,
    'og_image' => $image,
    'og_url'   => $pageUrl,
    // Not for search, deliberately. This page exists to be pasted into a
    // conversation, and the preview it draws there comes from the og tags above
    // — which the crawlers that draw previews read whether or not a page is
    // indexable. What indexing these would add is an unbounded number of pages
    // saying "somebody has visited 23 of 48 places", each competing with the
    // guide pages that are the ones actually worth finding.
    'noindex'  => true,
]);

$themeRow = share_themes()[$row['theme']] ?? null;
?>

<section class="wrap shared">

  <?php if ($name !== ''): ?>
    <p class="muted" style="margin-block-end:4px"><?= e($progress) ?></p>
    <h1 class="who"><?= e($name) ?></h1>
  <?php else: ?>
    <h1 class="who"><?= e($title) ?></h1>
  <?php endif; ?>

  <div class="card-holder"<?= $themeRow ? ' style="background:' . e($themeRow['bg']) . '"' : '' ?>>
    <img src="<?= e($image) ?>" width="1080" height="1080"
         alt="<?= e($progress) ?>">
  </div>

  <div class="cta-band">
    <h2><?= e(t('sm.cta')) ?></h2>
    <p><?= e(t('sm.cta_sub')) ?></p>
    <div class="hero-actions" style="margin:0">
      <a class="btn primary" href="<?= e(APP_BASE) ?>/index.php"><?= e(t('sm.cta_btn')) ?></a>
      <a class="btn" href="<?= e(APP_BASE) ?>/index.php?c=<?= e($row['code']) ?>">
        <?= e(t('sm.start_from')) ?>
      </a>
    </div>
  </div>

  <?php if ((int) $row['views'] > 0): ?>
    <p class="map-hint" style="margin-block-start:18px">
      <?= e(t('sm.views', ['n' => num((int) $row['views'])])) ?>
    </p>
  <?php endif; ?>

</section>

<?php
page_foot();
