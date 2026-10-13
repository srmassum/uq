<?php
/**
 * One place, in full.
 *
 * Two rules shape this page. First, a fact with no value behind it is not
 * printed — no empty "Best time" row, and no "Details checked" badge on a place
 * nobody has verified, because an absent field is honest and a blank one looks
 * like a zero. Second, the page always offers a way onward: back to the filtered
 * list it came from, onto the map with this place marked, and sideways to
 * whatever else is in the same area.
 */

declare(strict_types=1);

require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/places.php';
require __DIR__ . '/inc/layout.php';

/** The filter set the visitor arrived through, so "back" returns to their list. */
$fromRaw = isset($_GET['from']) && is_string($_GET['from']) ? $_GET['from'] : '';
$from    = [];
if ($fromRaw !== '' && strlen($fromRaw) <= 240) {
    parse_str($fromRaw, $parsed);
    if (is_array($parsed)) {
        $from = $parsed;
    }
}
// Whitelisted on the way back out, so a hand-edited `from` can only ever
// produce a filter set — never a redirect and never a value that reaches SQL.
$backFilters = places_read_filters($from);
$backSort    = places_sort_key($from);
$backQs      = places_query($backFilters, $backSort);
$backUrl     = APP_BASE . '/places.php' . ($backQs === '' ? '' : '?' . $backQs);

$slug  = isset($_GET['slug']) && is_string($_GET['slug']) ? trim($_GET['slug']) : '';
$place = place_by_slug($slug);

if ($place === null) {
    http_response_code(404);
    page_head(['nav' => 'places', 'title' => t('err.404'), 'noindex' => true]);
    ?>
    <section class="wrap narrow">
      <div class="panel">
        <h1><?= e(t('err.404')) ?></h1>
        <p><a class="btn" href="<?= e($backUrl) ?>"><?= e(t('place.back')) ?></a></p>
      </div>
    </section>
    <?php
    page_foot();
    exit;
}

$related = places_related($place, 3);
$tags    = places_tags($place);

$name  = pick($place, 'name');
$loc   = $place['loc'];
$mun   = $place['mun'];
$where = t('place.in_mun', [':mun' => pick($mun, 'name')])
       . ($loc !== null ? ' · ' . t('place.near', [':loc' => pick($loc, 'name')]) : '');

// The map marks localities, not places, so that is what this link marks. It is
// the funnel from the guide back into the map, which is the point of the guide.
$markUrl = $loc !== null
    ? APP_BASE . '/index.php?mark=' . rawurlencode((string) $loc['slug'])
    : APP_BASE . '/index.php';

// Only rows with something in them. Order is what a visitor wants first.
$facts = [];
if ($loc !== null)  { $facts[] = [t('place.area'), pick($loc, 'name')]; }
$facts[]            = [t('place.municipality'), pick($mun, 'name')];
$facts[]            = [t('place.fee'), t('fee.' . (string) $place['fee_level'])];

$dur = places_duration($place['duration_mins'] === null ? null : (int) $place['duration_mins']);
if ($dur !== '')    { $facts[] = [t('place.duration'), $dur]; }

// The column is best_time_en/_ar, so the base here must be best_time —
// pick() appends the suffix itself and 'best' would silently find nothing.
$best = pick($place, 'best_time');
if ($best !== '')   { $facts[] = [t('place.best_time'), $best]; }

$season = (string) ($place['season'] ?? '');
if ($season !== '') { $facts[] = [t('place.season'), t('season.' . $season)]; }

// Flags are shown only when they are true. Absence means "nobody recorded it",
// which is why the page never prints a "no" — a false here would be a claim.
if (!empty($place['family_friendly'])) { $facts[] = [t('place.family'), t('place.yes')]; }
if (!empty($place['metro_access']))    { $facts[] = [t('place.metro'), t('place.yes')]; }
if (!empty($place['indoor']))          { $facts[] = [t('place.indoor'), t('place.yes')]; }

page_head([
    'nav'   => 'places',
    'title' => $name . ' · ' . pick($mun, 'name'),
    'desc'  => pick($place, 'summary'),
    // Stated rather than derived: this page's address is its slug, and spelling
    // it out here means a stray parameter on an incoming link — a campaign tag,
    // a tracking string — cannot become a second canonical for the same place.
    'canonical' => 'place.php?slug=' . rawurlencode((string) $place['slug']),
]);
?>

<div class="wrap">
  <div class="place-hero">
    <a class="back" href="<?= e($backUrl) ?>">← <?= e(t('place.back')) ?></a>
    <h1><?= e($name) ?></h1>
    <p class="lede"><?= e(pick($place, 'summary')) ?></p>

    <div class="chip-row" style="margin-block-start:14px">
      <?php foreach ($tags as $tag): ?>
        <?php if ($tag['kind'] === 'cat' && $tag['color'] !== ''): ?>
          <span class="pill" style="border-color:transparent;background:color-mix(in srgb, <?= e($tag['color']) ?> 14%, transparent);color:<?= e($tag['color']) ?>">
            <?= e($tag['icon']) ?> <?= e($tag['text']) ?>
          </span>
        <?php elseif ($tag['kind'] === 'fee' && !empty($tag['free'])): ?>
          <span class="pill ok"><?= e($tag['text']) ?></span>
        <?php else: ?>
          <span class="pill"><?= e($tag['text']) ?></span>
        <?php endif; ?>
      <?php endforeach; ?>
      <span class="pill"><?= e($where) ?></span>
    </div>
  </div>

  <div class="place-body">

    <div>
      <?php $about = pick($place, 'description'); ?>
      <?php if ($about !== ''): ?>
        <div class="prose">
          <h2><?= e(t('place.about')) ?></h2>
          <?php // Authored as plain text with blank lines between paragraphs.
                // Escaped first, split second — nothing in here is ever HTML. ?>
          <?php foreach (preg_split('/\n\s*\n/', trim($about)) ?: [] as $para): ?>
            <?php if (trim($para) !== ''): ?><p><?= e(trim($para)) ?></p><?php endif; ?>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <?php $tips = pick($place, 'tips'); ?>
      <?php if ($tips !== ''): ?>
        <div class="tips">
          <h3><?= e(t('place.tips')) ?></h3>
          <?php foreach (preg_split('/\n\s*\n/', trim($tips)) ?: [] as $para): ?>
            <?php if (trim($para) !== ''): ?><p><?= e(trim($para)) ?></p><?php endif; ?>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <div class="place-actions">
        <a class="btn primary" href="<?= e($markUrl) ?>"><?= e(t('place.open_map')) ?></a>

        <?php // The wishlist toggle. Both the label and the filled/hollow heart are
              // rewritten by trip.js to match the stored state, so the markup here
              // is only the starting shape — and the visible text is why this one
              // needs no aria-label of its own. ?>
        <button class="btn trip-btn" type="button"
                data-trip="<?= e((string) $place['slug']) ?>"
                data-trip-name="<?= e($name) ?>"
                aria-pressed="false">
          <span class="trip-ico" aria-hidden="true">♡</span>
          <span class="trip-txt"><?= e(t('trip.add')) ?></span>
        </button>

        <?php if (!empty($place['map_url'])): ?>
          <a class="btn" href="<?= e((string) $place['map_url']) ?>"
             target="_blank" rel="noopener noreferrer"><?= e(t('place.map_link')) ?></a>
        <?php endif; ?>
        <?php if (!empty($place['official_url'])): ?>
          <a class="btn ghost" href="<?= e((string) $place['official_url']) ?>"
             target="_blank" rel="noopener noreferrer"><?= e(t('place.official')) ?></a>
        <?php endif; ?>
      </div>
      <?php if ($loc !== null): ?>
        <p class="map-hint"><?= e(t('place.open_map_hint')) ?></p>
      <?php endif; ?>
    </div>

    <aside>
      <div class="panel">
        <dl class="facts">
          <?php foreach ($facts as [$label, $value]): ?>
            <?php if ((string) $value === '') { continue; } ?>
            <div class="fact">
              <dt><?= e($label) ?></dt>
              <dd><?= e((string) $value) ?></dd>
            </div>
          <?php endforeach; ?>
        </dl>

        <?php if (!empty($place['verified_at'])): ?>
          <p class="map-hint"><?= e(t('place.verified', [':date' => (string) $place['verified_at']])) ?></p>
        <?php endif; ?>

        <?php if (!empty($place['source_note'])): ?>
          <p class="map-hint"><?= e(t('place.source')) ?>: <?= e((string) $place['source_note']) ?></p>
        <?php endif; ?>
      </div>
    </aside>
  </div>

  <?php if ($related): ?>
    <section class="related-band">
      <h2><?= e(t('place.related')) ?></h2>
      <div class="place-grid">
        <?php foreach ($related as $r): ?>
          <?php
          $rColor = (string) ($r['cat']['color'] ?? '');
          $rIcon  = (string) ($r['cat']['icon'] ?? '');
          ?>
          <a class="place-card" href="<?= e(APP_BASE . '/place.php?' . http_build_query([
                'slug' => (string) $r['slug'],
                'from' => $backQs,
             ])) ?>">
            <div class="thumb"<?= $rColor !== '' ? ' style="--c:' . e($rColor) . '"' : '' ?>>
              <span aria-hidden="true"><?= e($rIcon !== '' ? $rIcon : '📍') ?></span>
            </div>
            <div class="body">
              <h3><?= e(pick($r, 'name')) ?></h3>
              <p class="sm"><?= e(pick($r, 'summary')) ?></p>
              <div class="meta">
                <span class="pill"><?= e(t('fee.' . (string) $r['fee_level'])) ?></span>
                <span class="pill"><?= e(pick($r['mun'], 'name')) ?></span>
              </div>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endif; ?>
</div>

<?php
page_foot();
