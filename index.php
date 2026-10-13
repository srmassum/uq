<?php
/**
 * The map builder — the page the whole site exists for.
 *
 * Division of labour, which is worth stating because it is easy to erode:
 *   PHP  renders structure and every translated string.
 *   JS   owns the selection, and only ever flips classes or fills in numbers.
 *
 * So there is exactly one copy of each place name, badge name and sentence,
 * and adding a locality means editing data/localities.php and re-running
 * setup/prepare.php — never touching this file.
 */

declare(strict_types=1);

require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/mapdata.php';
require __DIR__ . '/inc/gamify.php';
require __DIR__ . '/inc/places.php';        // places_duration(), for the plan band
require __DIR__ . '/inc/itineraries.php';
require __DIR__ . '/inc/layout.php';

require_map_data();

$geo    = map_geo();
$locs   = map_localities();
$themes = share_themes();
$byMun  = localities_by_municipality();
$total  = total_markable();

// Checklist order comes from data/municipalities.php (already sorted); the
// geometry file is keyed in SVG order, which is alphabetical and not what we want.
$ordered = [];
foreach (require __DIR__ . '/data/municipalities.php' as $i => $m) {
    $ordered[$m['code']] = $i;
}
$muns = $geo['municipalities'];
uksort($muns, fn($a, $b) => ($ordered[$a] ?? 999) <=> ($ordered[$b] ?? 999));

$game     = gamify_payload();
$levels   = $game['levels'];
$badges   = $game['badges'];
$personas = $game['personalities'];

// Three ready-made plans for the band at the foot of the page. Only three, and
// only if there are any: the home page is the map builder and this is a way out
// for somebody who is not going to build anything, not a second plans page.
// itineraries_list() already orders by the editor's sort_order, so the three
// that arrive are the three the editor put first.
$planBand  = [];
$planStats = [];
if (itineraries_ready()) {
    $planBand  = itineraries_list(['limit' => 3]);
    $planStats = itinerary_stop_stats(array_column($planBand, 'id'));
}

// Strip the parsed ring arrays: the `d` string already carries every coordinate,
// and shipping both roughly doubles the payload for no gain.
$geoJs = $geo;
foreach ($geoJs['municipalities'] as $code => $m) {
    unset($geoJs['municipalities'][$code]['rings']);
}

$payload = [
    'geo'        => $geoJs,
    'localities' => $locs,
    'themes'     => $themes,
    'game'       => $game,

    // Sentences the JavaScript has to produce at runtime, kept as whole
    // translated templates so no English is ever assembled in the browser.
    'ui' => [
        'progress'    => t('map.progress'),
        'muns'        => t('map.mun_progress'),
        'empty'       => t('map.empty'),
        'all'         => t('map.all_marked'),
        'noMatch'     => t('map.no_match'),
        'markedOne'   => t('map.marked_one'),
        'markedMany'  => t('map.marked_many'),
        'loaded'      => t('map.loaded'),
        'keepYours'   => t('map.keep_yours'),
        'creating'    => t('share.creating'),
        'working'     => t('share.working'),
        'error'       => t('share.error'),
        'rateLimited' => t('share.rate_limited'),
        'copied'      => t('share.copied'),
        'makeLink'    => t('share.make_link'),
        'linkReady'   => t('share.link_ready'),
        'cardPct'     => t('card.pct'),
        'cardCredit'  => t('card.credit'),
        'anonName'    => t('card.anon_name'),
        'brand'       => t('site.name'),
    ],

    'meta' => [
        'lang' => current_lang(),
        'rtl'  => is_rtl(),
        'base' => APP_BASE,
        'csrf' => csrf_token(),
    ],
];

page_head([
    'nav'  => 'map',
    'desc' => t('hero.sub'),
    // ?c= arrives from someone else's share link and ?mark= from a place in the
    // guide. Both change what the visitor sees and neither is a different page:
    // the map, the list and the cards are the same, and a crawler following a
    // hundred share links would otherwise file a hundred copies of the home page.
    'canonical' => 'index.php',
]);
?>

<section class="wrap hero compact">
  <span class="pill brand"><?= e(t('hero.eyebrow', [
        ':places' => num((string) $total),
        ':muns'   => num((string) count($muns)),
      ])) ?></span>
  <h1><?= e(t('map.title')) ?></h1>
  <p class="lede"><?= e(t('hero.sub')) ?></p>
</section>

<div class="wrap builder">

  <!-- ---------------------------------------------------------- the map -->
  <div class="map-stage">
    <div class="map-frame" id="map-frame"></div>

    <div class="map-tools">
      <button class="btn sm" type="button" data-act="select-all"><?= e(t('map.select_all')) ?></button>
      <button class="btn sm" type="button" data-act="clear-all"><?= e(t('map.clear_all')) ?></button>
      <button class="btn sm ghost" type="button" data-act="reset-view"><?= e(t('map.reset_view')) ?></button>
      <span class="map-hint grow"><?= e(t('map.zoom_hint')) ?></span>
    </div>
  </div>

  <!-- ---------------------------------------------------- progress + list -->
  <div class="map-side">

    <div class="panel">
      <?php // The share is the number people actually quote out loud, so it sits
            // on the counter row rather than in the small print underneath. ?>
      <div class="counter">
        <span class="big" data-count>0</span>
        <span class="of">/ <?= (int) $total ?></span>
        <span class="pct" data-pct>0%</span>
      </div>
      <div class="bar"><i data-bar style="inline-size:0"></i></div>

      <p class="muted" data-summary
         data-tpl-places="<?= e(t('map.progress')) ?>"
         data-tpl-muns="<?= e(t('map.mun_progress')) ?>"
         data-tpl-empty="<?= e(t('map.empty')) ?>"
         data-tpl-all="<?= e(t('map.all_marked')) ?>"></p>

      <?php if ($levels): ?>
        <div class="tiers">
          <?php foreach ($levels as $lv): ?>
            <span class="pill" data-level="<?= e($lv['slug']) ?>" hidden
                  style="background:color-mix(in srgb, <?= e($lv['color']) ?> 16%, transparent); color:<?= e($lv['color']) ?>; border-color:transparent">
              <?= e(t('level.label')) ?>: <?= e(pick($lv, 'name')) ?>
            </span>
          <?php endforeach; ?>

          <?php foreach ($levels as $i => $lv): ?>
            <?php if ($i === 0) continue; ?>
            <p class="muted next-line" data-next="<?= e($lv['slug']) ?>" hidden>
              <?= str_replace(
                    [':n', ':level'],
                    ['<b data-n>0</b>', e(pick($lv, 'name'))],
                    e(t('level.next'))
                  ) ?>
            </p>
          <?php endforeach; ?>

          <p class="muted next-line" data-next="" hidden><?= e(t('level.max')) ?></p>
        </div>
      <?php endif; ?>
    </div>

    <div class="panel">
      <div class="panel-head">
        <h2><?= e(t('nav.map')) ?></h2>
        <span class="pill" data-mun-pill>0 / <?= count($muns) ?></span>
      </div>

      <label class="sr" for="map-search"><?= e(t('map.search')) ?></label>
      <input class="search" id="map-search" type="search" autocomplete="off"
             placeholder="<?= e(t('map.search')) ?>"
             data-no-match="<?= e(t('map.no_match')) ?>">

      <p class="empty" data-no-results hidden></p>

      <?php foreach ($muns as $code => $m): ?>
        <?php $list = $byMun[$code] ?? []; ?>
        <div class="mun-group" data-code="<?= e($code) ?>" data-open="1">
          <div class="mun-head" data-code="<?= e($code) ?>" role="checkbox" aria-checked="false" tabindex="0">
            <span class="swatch" aria-hidden="true"></span>
            <span class="nm"><?= e(pick($m, 'name')) ?></span>
            <span class="count">0 / <?= count($list) ?></span>
            <button class="fold-btn" type="button" data-fold="<?= e($code) ?>"
                    aria-label="<?= e(pick($m, 'name')) ?>">
              <span class="chev" aria-hidden="true">▾</span>
            </button>
          </div>

          <div class="mun-body">
            <?php if ($list): ?>
              <button class="mark-all" type="button" data-mark-all="<?= e($code) ?>">
                <?= e(t('map.mark_all', [':n' => (string) count($list)])) ?>
              </button>
            <?php endif; ?>

            <?php foreach ($list as $l): ?>
              <?php $kl = kind_label($l['kind']); ?>
              <div class="loc-row" data-slug="<?= e($l['slug']) ?>"
                   role="checkbox" aria-checked="false" tabindex="0">
                <span class="tick" aria-hidden="true"></span>
                <span class="nm"><?= e(pick($l, 'name')) ?></span>
                <?php if ($kl !== ''): ?><span class="kh"><?= e($kl) ?></span><?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

  </div>
</div>

<!-- ------------------------------------------------- badges and personality -->
<div class="wrap extras">

  <?php if ($badges): ?>
    <div class="panel">
      <div class="panel-head">
        <h2><?= e(t('badges.title')) ?></h2>
        <span class="pill" data-badge-count>0 / <?= count($badges) ?></span>
      </div>
      <div class="badge-grid">
        <?php foreach ($badges as $b): ?>
          <div class="badge" data-badge="<?= e($b['slug']) ?>" title="<?= e(pick($b, 'description')) ?>">
            <div class="ico" aria-hidden="true"><?= e($b['icon']) ?></div>
            <div class="nm"><?= e(pick($b, 'name')) ?></div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>

  <div class="panel">
    <div class="panel-head"><h2><?= e(t('personality.title')) ?></h2></div>

    <?php foreach ($personas as $p): ?>
      <div class="persona" data-persona="<?= e($p['slug']) ?>" hidden>
        <span class="glyph" aria-hidden="true"><?= e(persona_icon($p['slug'])) ?></span>
        <span>
          <span class="nm"><?= e(pick($p, 'name')) ?></span>
          <span class="bl"><?= e(pick($p, 'blurb')) ?></span>
        </span>
      </div>
    <?php endforeach; ?>

    <p class="muted" data-persona-hint><?= e(t('personality.hint')) ?></p>
  </div>

</div>

<!-- ------------------------------------------------------------- share card -->
<div class="wrap" style="padding-block-end:64px">
  <div class="panel" id="share-panel">
    <div class="panel-head">
      <h2><?= e(t('share.title')) ?></h2>
      <span class="map-hint"><?= e(t('share.private_note')) ?></span>
    </div>

    <div class="extras" style="padding:0;gap:22px">

      <div>
        <div class="field">
          <label for="card-name"><?= e(t('share.name_label')) ?></label>
          <input class="text-in" id="card-name" type="text" maxlength="48" autocomplete="name"
                 placeholder="<?= e(t('share.name_placeholder')) ?>">
        </div>

        <div class="field">
          <span class="lbl"><?= e(t('share.photo')) ?></span>
          <div class="avatar-row">
            <div class="avatar" id="avatar" aria-hidden="true">＋</div>
            <div>
              <button class="btn sm" type="button" id="photo-pick"><?= e(t('share.photo')) ?></button>
              <button class="btn sm ghost" type="button" id="photo-clear" hidden><?= e(t('share.photo_remove')) ?></button>
            </div>
          </div>
          <input type="file" id="photo-file" accept="image/*" hidden>
          <p class="map-hint"><?= e(t('share.photo_hint')) ?></p>
        </div>

        <div class="field">
          <span class="lbl"><?= e(t('share.theme')) ?></span>
          <div class="chip-row" id="theme-chips">
            <?php foreach ($themes as $slug => $th): ?>
              <button class="chip" type="button" data-theme="<?= e($slug) ?>" aria-pressed="false">
                <span class="dot" style="background:<?= e($th['visited']) ?>" aria-hidden="true"></span>
                <?= e(pick($th, 'name')) ?>
              </button>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="field">
          <span class="lbl"><?= e(t('share.size')) ?></span>
          <div class="chip-row" id="size-chips">
            <button class="chip" type="button" data-size="post"  aria-pressed="true"><?= e(t('share.size_post')) ?> · 1:1</button>
            <button class="chip" type="button" data-size="story" aria-pressed="false"><?= e(t('share.size_story')) ?> · 9:16</button>
            <button class="chip" type="button" data-size="wide"  aria-pressed="false"><?= e(t('share.size_wide')) ?> · 1.91:1</button>
          </div>
        </div>
      </div>

      <div>
        <div class="card-preview">
          <canvas id="card-preview" width="1000" height="1000"></canvas>
        </div>

        <div class="share-actions">
          <button class="btn" type="button" id="dl-png"><?= e(t('share.download_png')) ?></button>
          <button class="btn" type="button" id="dl-pdf"><?= e(t('share.download_pdf')) ?></button>
          <button class="btn primary" type="button" id="make-link"><?= e(t('share.make_link')) ?></button>
        </div>

        <div class="link-box" id="link-box" hidden>
          <input id="link-url" type="text" readonly aria-label="<?= e(t('share.link_ready')) ?>">
          <button class="btn sm" type="button" id="copy-link"><?= e(t('share.copy')) ?></button>
        </div>
      </div>

    </div>
  </div>
</div>

<?php if ($planBand !== []): ?>
<!-- ------------------------------------------------------- ready-made plans -->
<?php // The way out for somebody who is not going to mark anything. Three plans
      // is enough to show what one looks like; the link carries the rest. ?>
<div class="wrap plan-band">
  <div class="plan-band-head">
    <div>
      <h2><?= e(t('plans.title')) ?></h2>
      <p class="lede"><?= e(t('plans.sub')) ?></p>
    </div>
    <a class="btn" href="<?= e(APP_BASE) ?>/itineraries.php"><?= e(t('plans.see_all')) ?></a>
  </div>

  <div class="place-grid">
    <?php foreach ($planBand as $p): ?>
      <?= itin_card($p, $planStats[(int) $p['id']] ?? ['total' => 0, 'mins' => 0]) ?>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<?php
$scripts = '<script>window.QATAR = '
    . json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)
    . ';</script>' . "\n"
    . '<script src="' . e(asset('assets/js/map.js')) . '" defer></script>' . "\n"
    . '<script src="' . e(asset('assets/js/gamify.js')) . '" defer></script>' . "\n"
    . '<script src="' . e(asset('assets/js/export.js')) . '" defer></script>' . "\n"
    . '<script src="' . e(asset('assets/js/app.js')) . '" defer></script>' . "\n";

page_foot(['scripts' => $scripts]);
