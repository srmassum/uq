<?php
/**
 * Ready-made plans — a day in Doha, a weekend, or a long layover.
 *
 * This is স্তর ৩ item ৮: the trips somebody can take without planning anything
 * themselves. The panel writes them, this reads them, and the two were never
 * connected until now.
 *
 * Filters live in the query string and are read on the server, exactly as they
 * are on the guide. That matters more here than it does there, because "the
 * 8-hour stopover for a family" is a page worth sending to somebody, and a
 * filtered list is still a list when scripting is off.
 *
 * The chips are built from the plans that actually exist rather than from the
 * fixed set features.md lists. A site whose editor has written two plans should
 * offer two chips, not six that mostly lead nowhere.
 */

declare(strict_types=1);

require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/places.php';        // places_duration(), used by itin_card()
require __DIR__ . '/inc/itineraries.php';
require __DIR__ . '/inc/layout.php';

$f     = itin_read_filters($_GET);
$ready = itineraries_ready();

$all   = $ready ? itineraries_list([]) : [];
$rows  = $ready ? itineraries_list($f) : [];
$stats = $ready ? itinerary_stop_stats(array_column($rows, 'id')) : [];
$lens  = $ready ? itineraries_lengths() : [];
$whos  = $ready ? itineraries_audiences() : [];

$shown = count($rows);

// The filters are part of the address — they are read here, a link carrying them
// opens the same list, and "the two-day family plans" is real content rather
// than a duplicate of the unfiltered page. So they stay in the canonical.
$canonical = 'itineraries.php' . ($f === [] ? '' : '?' . http_build_query($f));

page_head([
    'nav'       => 'plans',
    'title'     => t('plans.title'),
    'desc'      => t('plans.sub'),
    'canonical' => $canonical,
]);
?>

<section class="wrap hero compact">
  <span class="pill brand"><?= e(count($all) === 1
        ? t('plans.count_one')
        : t('plans.count', [':n' => num(count($all))])) ?></span>
  <h1><?= e(t('plans.title')) ?></h1>
  <p class="lede"><?= e(t('plans.sub')) ?></p>
</section>

<?php if (!$ready): ?>

  <section class="wrap narrow">
    <div class="panel">
      <h2><?= e(t('plans.empty_h')) ?></h2>
      <p><?= e(t('plans.empty_p')) ?></p>
      <p class="muted"><?= e(t('plans.empty_where')) ?></p>
    </div>
  </section>

<?php else: ?>

<div class="wrap">

  <div class="plan-filters">
    <?php if ($lens !== []): ?>
      <div class="filter-group">
        <span class="glabel"><?= e(t('plans.length')) ?></span>
        <div class="chip-row">
          <?php foreach ($lens as $l): ?>
            <?php
            // Each chip is a link that toggles itself, so a filter needs no
            // JavaScript, the pressed state is already styled by .chip, and the
            // URL is the state.
            $next = $f;
            if (($next['len'] ?? '') === $l['key']) { unset($next['len']); } else { $next['len'] = $l['key']; }
            $on = ($f['len'] ?? '') === $l['key'];
            ?>
            <a class="chip" href="<?= e(itin_url($next)) ?>"
               aria-current="<?= $on ? 'true' : 'false' ?>"><?= e($l['value'] === 1
                    ? ($l['type'] === 'hours' ? t('itin.hour_one') : t('itin.day_one'))
                    : ($l['type'] === 'hours'
                        ? t('itin.hours', [':n' => num($l['value'])])
                        : t('itin.days',  [':n' => num($l['value'])]))) ?></a>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>

    <?php if ($whos !== []): ?>
      <div class="filter-group">
        <span class="glabel"><?= e(t('plans.who')) ?></span>
        <div class="chip-row">
          <?php foreach ($whos as $w): ?>
            <?php
            $next = $f;
            if (($next['who'] ?? '') === $w['slug']) { unset($next['who']); } else { $next['who'] = $w['slug']; }
            $on = ($f['who'] ?? '') === $w['slug'];
            ?>
            <a class="chip" href="<?= e(itin_url($next)) ?>"
               aria-current="<?= $on ? 'true' : 'false' ?>"><?= e(t('who.' . $w['slug'])) ?></a>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>

    <?php if ($f !== []): ?>
      <div class="filter-group">
        <span class="glabel"></span>
        <a class="btn sm ghost" href="<?= e(APP_BASE) ?>/itineraries.php"><?= e(t('plans.clear')) ?></a>
      </div>
    <?php endif; ?>
  </div>

  <p class="muted results-line">
    <?php
    // "12 of 12 plans" is not a sentence anybody needs, so the count only
    // becomes a fraction once a filter is actually on — the same rule the guide
    // uses.
    if ($f === []) {
        echo e($shown === 1 ? t('plans.count_one') : t('plans.count', [':n' => num($shown)]));
    } else {
        echo e(t('plans.showing', [':n' => num($shown), ':total' => num(count($all))]));
    }
    ?>
  </p>

  <?php if ($rows === []): ?>
    <p class="empty"><?= e(t('plans.none')) ?></p>
  <?php else: ?>

    <div class="place-grid">
      <?php foreach ($rows as $p): ?>
        <?php
        // The card markup lives in itin_card() because the home page shows three
        // of these too, and one definition is one place to fix.
        $stat = $stats[(int) $p['id']] ?? ['total' => 0, 'mins' => 0];
        echo itin_card($p, $stat);
        ?>
      <?php endforeach; ?>
    </div>

  <?php endif; ?>
</div>

<?php endif; ?>

<?php
page_foot();
