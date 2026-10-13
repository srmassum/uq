<?php
/**
 * Analytics.
 *
 * Everything here comes from tables the site already writes — saved_maps,
 * saved_map_items and card_events — so it needs no third-party script and no
 * cookie banner.
 *
 * Two of the roadmap's four questions cannot be answered from what is stored,
 * and the screen says so rather than showing an invented number:
 *
 *   - "visitors by country" needs a geo-IP lookup. The site keeps an HMAC of the
 *     IP address for rate limiting and deliberately never the address itself, so
 *     there is nothing to resolve. If it is ever wanted, it has to be a choice
 *     made knowingly, because it means storing something about visitors.
 *   - "what people search for" needs the search box to log its terms. It does
 *     not, today.
 */

declare(strict_types=1);

require_once __DIR__ . '/inc/panel.php';

require_admin();

/** Run a count, or null when it cannot be answered. */
function an_one(string $sql, array $p = []): ?int
{
    try {
        return (int) qVal($sql, $p);
    } catch (Throwable $e) {
        error_log('analytics: ' . $e->getMessage());
        return null;
    }
}

function an_rows(string $sql, array $p = []): array
{
    try {
        return qAll($sql, $p);
    } catch (Throwable $e) {
        error_log('analytics: ' . $e->getMessage());
        return [];
    }
}

function an_n(?int $n): string
{
    return $n === null ? '—' : number_format($n);
}

$window = get_int('days', 30);
if (!in_array($window, [7, 30, 90, 365], true)) {
    $window = 30;
}
$w = max(1, $window);

$events = [];
foreach (an_rows(
    "SELECT event, COUNT(*) n FROM card_events
      WHERE created_at > NOW() - INTERVAL {$w} DAY
      GROUP BY event"
) as $r) {
    $events[(string) $r['event']] = (int) $r['n'];
}

$formats = an_rows(
    "SELECT format, COUNT(*) n FROM card_events
      WHERE event = 'download' AND format <> '' AND created_at > NOW() - INTERVAL {$w} DAY
      GROUP BY format ORDER BY n DESC"
);

$themes = an_rows(
    "SELECT theme, COUNT(*) n FROM saved_maps
      WHERE theme <> '' AND created_at > NOW() - INTERVAL {$w} DAY
      GROUP BY theme ORDER BY n DESC"
);

$langs = an_rows(
    "SELECT lang, COUNT(*) n FROM saved_maps
      WHERE created_at > NOW() - INTERVAL {$w} DAY
      GROUP BY lang ORDER BY n DESC"
);

$topPlaces = an_rows(
    'SELECT p.id, p.name_en, p.views, p.visit_marks, m.name_en AS mun
       FROM places p JOIN municipalities m ON m.id = p.municipality_id
      WHERE p.deleted_at IS NULL
      ORDER BY p.views DESC, p.visit_marks DESC LIMIT 12'
);

$topLocalities = an_rows('SELECT * FROM v_popular_localities LIMIT 12');

// How much of the country the average finished card covers, and which
// municipalities are most often ticked.
$coverage = an_rows(
    'SELECT m.name_en, COUNT(smi.saved_map_id) AS picks
       FROM municipalities m
       LEFT JOIN saved_map_items smi
              ON smi.item_type = "municipality" AND smi.item_id = m.id
      GROUP BY m.id, m.name_en
      ORDER BY picks DESC'
);

$mapsTotal = an_one("SELECT COUNT(*) FROM saved_maps WHERE status = 'active'");
$avgMun    = an_one('SELECT AVG(mun_count) FROM saved_maps');
$avgLoc    = an_one('SELECT AVG(loc_count) FROM saved_maps');

$munTotal = an_one('SELECT COUNT(*) FROM municipalities') ?: 8;
$locTotal = an_one('SELECT COUNT(*) FROM localities') ?: 48;

admin_head(['title' => 'Analytics', 'nav' => 'analytics']);

ad_title(
    'Analytics',
    'Everything here is counted from the site’s own tables. Nothing is loaded from a third party, which is why there is no cookie banner on the public pages.',
    array_map(
        static fn(int $d): array => [
            'href'  => admin_url('analytics.php', ['days' => $d]),
            'label' => 'Last ' . $d . ' days',
            'kind'  => $window === $d ? 'primary' : 'ghost',
        ],
        [7, 30, 90, 365]
    )
);

ad_note(
    'Two things the roadmap asks for are not on this page, because the data does not exist: '
    . '<b>visitors by country</b> would need a geo-IP lookup, and the site stores only an HMAC of an '
    . 'address, never the address; <b>what people search for</b> would need the guide’s search box to '
    . 'log its terms, which it does not. Both are one small change away if you want them — but they '
    . 'are choices about what the site remembers about visitors, so they should be made on purpose.',
    'info'
);

?>

<div class="ad-stats" style="margin-block-end:18px">
  <div class="ad-stat"><b><?= an_n(an_one("SELECT COUNT(*) FROM saved_maps WHERE created_at > NOW() - INTERVAL {$w} DAY")) ?></b><span>Maps saved</span></div>
  <div class="ad-stat"><b><?= an_n($mapsTotal) ?></b><span>Live shared maps</span></div>
  <div class="ad-stat"><b><?= an_n(an_one("SELECT COALESCE(SUM(views),0) FROM saved_maps WHERE created_at > NOW() - INTERVAL {$w} DAY")) ?></b><span>Share-page views</span></div>
  <div class="ad-stat"><b><?= an_n($events['download'] ?? 0) ?></b><span>Cards downloaded</span></div>
  <div class="ad-stat"><b><?= an_n(($events['share'] ?? 0) + ($events['share_click'] ?? 0)) ?></b><span>Share clicks</span></div>
</div>

<div class="ad-panel">
  <h2>How far people get</h2>
  <?php if ($mapsTotal === null || (int) $mapsTotal === 0): ?>
    <?php ad_empty('No saved maps in this window yet.'); ?>
  <?php else: ?>
    <dl class="ad-kv">
      <dt>Average municipalities ticked</dt>
      <dd><?= e(number_format((float) $avgMun, 1)) ?> of <?= (int) $munTotal ?></dd>
      <dt>Average localities ticked</dt>
      <dd><?= e(number_format((float) $avgLoc, 1)) ?> of <?= (int) $locTotal ?></dd>
    </dl>
    <p class="ad-help" style="margin-block:12px 0">
      A low average is not a problem — it is the shape of the viral loop. Most
      people mark the few places they have been and share; the completionists
      come back.
    </p>
  <?php endif; ?>
</div>

<div class="ad-grid-2">

  <div class="ad-panel">
    <h2>Most viewed places</h2>
    <?php if ($topPlaces === []): ?>
      <?php ad_empty('No places to rank yet.'); ?>
    <?php else: ?>
      <div class="ad-tablewrap">
        <table class="ad-table">
          <thead><tr><th>Place</th><th>Municipality</th><th class="num">Views</th><th class="num">Marks</th></tr></thead>
          <tbody>
          <?php foreach ($topPlaces as $p): ?>
            <tr>
              <td><a class="ad-rowlink" href="<?= e(admin_url('place-edit.php', ['id' => (int) $p['id']])) ?>"><?= e((string) $p['name_en']) ?></a></td>
              <td><?= e((string) $p['mun']) ?></td>
              <td class="num"><?= (int) $p['views'] ?></td>
              <td class="num"><?= (int) $p['visit_marks'] ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

  <div class="ad-panel">
    <h2>Most ticked localities</h2>
    <?php if ($topLocalities === []): ?>
      <?php ad_empty('Nobody has saved a map yet, so nothing is ranked.'); ?>
    <?php else: ?>
      <ul class="ad-list">
        <?php foreach ($topLocalities as $l): ?>
          <li>
            <a class="ad-rowlink" href="<?= e(APP_BASE . '/index.php?mark=' . urlencode((string) $l['slug'])) ?>" target="_blank" rel="noopener"><?= e((string) $l['name_en']) ?></a>
            <span class="ad-muted"><?= (int) $l['marks'] ?> marks</span>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>

  <div class="ad-panel">
    <h2>Municipalities by how often they are ticked</h2>
    <?php if ($coverage === []): ?>
      <?php ad_empty('Nothing to show yet.'); ?>
    <?php else: ?>
      <?php
      $max = 1;
      foreach ($coverage as $c) { $max = max($max, (int) $c['picks']); }
      ?>
      <ul class="ad-list">
        <?php foreach ($coverage as $c): ?>
          <li>
            <span style="flex:1"><?= e((string) $c['name_en']) ?></span>
            <span class="ad-bar" style="flex:1;max-inline-size:180px">
              <i style="inline-size:<?= (int) round(100 * (int) $c['picks'] / $max) ?>%"></i>
            </span>
            <span class="ad-muted"><?= (int) $c['picks'] ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>

  <div class="ad-panel">
    <h2>Card formats and themes</h2>
    <?php if ($formats === [] && $themes === [] && $langs === []): ?>
      <?php ad_empty('No cards downloaded or saved in this window.'); ?>
    <?php else: ?>
      <?php if ($formats !== []): ?>
        <h3 style="font-size:.9rem;margin:0 0 6px">Downloads by format</h3>
        <ul class="ad-list">
          <?php foreach ($formats as $f): ?>
            <li><span><?= e((string) $f['format']) ?></span><span class="ad-muted"><?= (int) $f['n'] ?></span></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
      <?php if ($themes !== []): ?>
        <h3 style="font-size:.9rem;margin:14px 0 6px">Themes chosen</h3>
        <ul class="ad-list">
          <?php foreach ($themes as $t): ?>
            <li><span><?= e((string) $t['theme']) ?></span><span class="ad-muted"><?= (int) $t['n'] ?></span></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
      <?php if ($langs !== []): ?>
        <h3 style="font-size:.9rem;margin:14px 0 6px">Language of the card</h3>
        <ul class="ad-list">
          <?php foreach ($langs as $l): ?>
            <li><span><?= $l['lang'] === 'ar' ? 'Arabic' : 'English' ?></span><span class="ad-muted"><?= (int) $l['n'] ?></span></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    <?php endif; ?>
  </div>

</div>

<?php
admin_foot();
