<?php
/**
 * One ready-made plan, stop by stop.
 *
 * Three things this page refuses to do, all for the same reason — a plan is a
 * promise about how a day will go, and every one of them would make it a
 * slightly false one:
 *
 *   - It never invents a time. A stop whose place has no recorded duration is
 *     counted as unrecorded rather than as zero, and the total says so.
 *   - It never drops a stop. A stop whose place has been unpublished keeps its
 *     place in the route, as text, because the day it describes still runs that
 *     way.
 *   - It never shows a "details checked" badge for a plan nobody has checked,
 *     which is every plan until an editor puts a date on it.
 *
 * The whole route can be pushed into "My trip" in one press, which is the join
 * between the two halves of স্তর ৩ — somebody reads a plan, and the plan becomes
 * the list they take with them.
 */

declare(strict_types=1);

require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/places.php';        // places_duration()
require __DIR__ . '/inc/itineraries.php';
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
// Whitelisted on the way back out, so a hand-edited `from` can only ever produce
// a filter set — never a redirect and never a value that reaches SQL.
$backUrl = itin_url(itin_read_filters($from));

$slug = isset($_GET['slug']) && is_string($_GET['slug']) ? trim($_GET['slug']) : '';
$itin = itinerary_by_slug($slug);

if ($itin === null) {
    http_response_code(404);
    page_head(['nav' => 'plans', 'title' => t('err.404'), 'noindex' => true]);
    ?>
    <section class="wrap narrow">
      <div class="panel">
        <h1><?= e(t('err.404')) ?></h1>
        <p><a class="btn" href="<?= e($backUrl) ?>"><?= e(t('plan.back')) ?></a></p>
      </div>
    </section>
    <?php
    page_foot();
    exit;
}

$stops  = itinerary_stops((int) $itin['id']);
$days   = itinerary_days($stops);
$totals = itinerary_totals($stops);

$title  = pick($itin, 'title');
$intro  = pick($itin, 'intro');
$length = itin_length_label($itin);

// What the two client-side actions need: the live stops, in route order, and the
// localities they sit in. Localities rather than places, because the map marks
// localities — the same link a place page builds.
$planSlugs = [];
$locSlugs  = [];
foreach ($stops as $s) {
    if (!$s['live']) {
        continue;
    }
    $planSlugs[] = $s['slug'];
    if ($s['loc'] !== '') {
        $locSlugs[$s['loc']] = true;
    }
}
$locSlugs = array_keys($locSlugs);

$markUrl = APP_BASE . '/index.php'
    . ($locSlugs === [] ? '' : '?mark=' . rawurlencode(implode(',', $locSlugs)));

// Only what there is. Order is what a visitor decides on first.
$facts = [
    [t('plan.f_length'), $length],
    [t('plan.f_for'),    itin_audience_label($itin)],
    [t('plan.f_budget'), t('fee.' . (string) $itin['budget_level'])],
];
// Every line of the route, hand-written ones included, because that is the count
// the reader can see numbered down the page and check.
if ($stops !== []) {
    $facts[] = [t('plan.f_stops'), num(count($stops))];
}
$visit = places_duration($totals['mins']);
if ($visit !== '') {
    $facts[] = [t('plan.f_visiting'), $visit];
}

page_head([
    'nav'   => 'plans',
    'title' => $title . ' · ' . $length,
    'desc'  => $intro !== '' ? mb_substr($intro, 0, 180) : t('plans.sub'),
    // Stated rather than derived, for the same reason place.php states its own:
    // a campaign tag on an incoming link must not become a second canonical for
    // the same plan.
    'canonical' => 'itinerary.php?slug=' . rawurlencode((string) $itin['slug']),
]);
?>

<div class="wrap">
  <div class="place-hero">
    <a class="back" href="<?= e($backUrl) ?>">← <?= e(t('plan.back')) ?></a>
    <h1><?= e($title) ?></h1>
    <?php if ($intro !== ''): ?>
      <p class="lede"><?= e($intro) ?></p>
    <?php endif; ?>

    <div class="chip-row" style="margin-block-start:14px">
      <span class="pill brand"><?= e($length) ?></span>
      <span class="pill"><?= e(itin_audience_label($itin)) ?></span>
      <span class="pill"><?= e(t('fee.' . (string) $itin['budget_level'])) ?></span>
      <?php if ($visit !== ''): ?>
        <span class="pill"><?= e(t('plan.about_time', [':time' => $visit])) ?></span>
      <?php endif; ?>
    </div>
  </div>

  <div class="place-body">

    <div>
      <?php if ($days === []): ?>
        <?php // An editor can publish a plan before writing its stops. Saying so
              // is better than an empty page that looks like a bug. ?>
        <div class="panel">
          <p><?= e(t('plan.no_stops')) ?></p>
        </div>
      <?php else: ?>

        <div class="plan-route-wrap">
          <?php foreach ($days as $d): ?>
            <div class="plan-day">
              <?php if ($d['label'] > 0): ?>
                <h2 class="plan-day-head"><?= e(t('plan.day', [':n' => num($d['label'])])) ?></h2>
              <?php else: ?>
                <?php // A single-day plan has no days to tell apart, so it gets no
                      // heading — but the heading level still has to be there for
                      // the outline a screen reader reads. ?>
                <h2 class="sr"><?= e(t('plan.route')) ?></h2>
              <?php endif; ?>

              <ol class="plan-route">
                <?php $n = 0; foreach ($d['stops'] as $s): $n++; ?>
                  <li class="plan-stop<?= $s['live'] ? '' : ' is-text' ?>">
                    <span class="plan-num" aria-hidden="true"><?= e(num($n)) ?></span>

                    <div class="plan-stop-main">
                      <?php if ($s['live']): ?>
                        <h3>
                          <a href="<?= e(APP_BASE . '/place.php?slug=' . rawurlencode($s['slug'])) ?>">
                            <?= e($s['name']) ?>
                          </a>
                        </h3>
                        <?php if ($s['summary'] !== ''): ?>
                          <p class="plan-sum"><?= e($s['summary']) ?></p>
                        <?php endif; ?>
                      <?php else: ?>
                        <?php // A hand-written stop, or one whose place is no
                              // longer published. Either way it is prose, and it
                              // is the only text this stop has. ?>
                        <?php if ($s['note'] !== ''): ?>
                          <p class="plan-note"><?= e($s['note']) ?></p>
                        <?php endif; ?>
                      <?php endif; ?>

                      <?php // The time note is the editor's own sentence about this
                            // stop — "arrive before 4pm" — and it is separate from
                            // the duration pill below, which is the place's own
                            // recorded figure. ?>
                      <?php if ($s['time_note'] !== ''): ?>
                        <p class="plan-time"><?= e($s['time_note']) ?></p>
                      <?php endif; ?>

                      <?php // On a live stop the note is an extra remark about the
                            // place; on a text stop it has already been printed as
                            // the stop itself. ?>
                      <?php if ($s['live'] && $s['note'] !== ''): ?>
                        <p class="plan-note"><?= e($s['note']) ?></p>
                      <?php endif; ?>

                      <div class="meta">
                        <?php $dur = places_duration($s['mins']); ?>
                        <?php if ($dur !== ''): ?>
                          <span class="pill"><?= e($dur) ?></span>
                        <?php endif; ?>
                        <?php if ($s['fee'] !== ''): ?>
                          <span class="pill"><?= e(t('fee.' . $s['fee'])) ?></span>
                        <?php endif; ?>
                        <?php if ($s['mun'] !== null): ?>
                          <span class="pill"><?= e(pick($s['mun'], 'name')) ?></span>
                        <?php endif; ?>
                      </div>
                    </div>

                    <?php if ($s['live']): ?>
                      <?php // Icon-only, so trip.js gives it its own aria-label —
                            // and the same markup the guide's cards use, so one
                            // delegated listener covers both. ?>
                      <button class="trip-btn" type="button"
                              data-trip="<?= e($s['slug']) ?>"
                              data-trip-name="<?= e($s['name']) ?>"
                              aria-pressed="false">
                        <span class="trip-ico" aria-hidden="true">♡</span>
                      </button>
                    <?php endif; ?>
                  </li>
                <?php endforeach; ?>
              </ol>
            </div>
          <?php endforeach; ?>
        </div>

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

        <?php // The same honesty the trip page applies to its own total: a figure
              // that excludes some stops has to say so, or it reads as the whole
              // day when it is only part of it. ?>
        <?php if ($totals['unknown'] > 0 && $totals['mins'] > 0): ?>
          <p class="map-hint">
            <?= e(t('plan.unknown_time', [':n' => num($totals['unknown'])])) ?>
          </p>
        <?php endif; ?>

        <?php if (!empty($itin['verified_at'])): ?>
          <p class="map-hint"><?= e(t('plan.verified', [':date' => (string) $itin['verified_at']])) ?></p>
        <?php endif; ?>
      </div>

      <?php if ($planSlugs !== []): ?>
        <div class="panel plan-actions">
          <?php // Ships hidden and is revealed by itinerary.js: without scripting
                // it cannot touch the trip list, and a button that does nothing is
                // worse than no button. The slugs ride in an attribute rather than
                // in a JSON block because they are validated on the way into the
                // trip list anyway, so nothing here can add anything a slug could
                // not name. ?>
          <button class="btn primary" type="button" data-plan-add hidden
                  data-plan-slugs="<?= e(implode(',', $planSlugs)) ?>">
            <span class="trip-ico" aria-hidden="true">♡</span>
            <span class="trip-txt"><?= e(t('plan.add_all')) ?></span>
          </button>
          <p class="map-hint" data-plan-status hidden></p>
        </div>
      <?php endif; ?>

      <div class="panel plan-actions">
        <a class="btn" href="<?= e($markUrl) ?>"><?= e(t('plan.open_map')) ?></a>
        <?php if ($locSlugs !== []): ?>
          <p class="map-hint"><?= e(t('plan.open_map_hint')) ?></p>
        <?php endif; ?>
      </div>
    </aside>
  </div>
</div>

<?php
// Flat, keyed by bare name — the same shape trip_scripts() emits and the same
// shape itinerary.js reads. A nested object here would leave every lookup
// undefined, and the script's fallbacks would quietly print English on the
// Arabic page instead of failing where anyone would notice.
$scripts = '<script>window.QPLAN_STRINGS = '
    . json_encode([
        'add_all'   => t('plan.add_all'),
        'added_all' => t('plan.added_all'),
        'added_n'   => t('plan.added_n'),
        'already'   => t('plan.already'),
    ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)
    . ';</script>' . "\n"
    . '<script src="' . e(asset('assets/js/itinerary.js')) . '" defer></script>' . "\n";

page_foot(['scripts' => $scripts]);
