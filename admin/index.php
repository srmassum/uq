<?php
/**
 * The dashboard.
 *
 * Answers the five questions features.md asks of it: how much content is there,
 * what happened this week, which places people actually mark, what needs a
 * human's attention, and who changed what most recently.
 *
 * Every number is fetched through dash_one()/dash_rows(), which swallow a
 * failure and return a null. A missing table — a half-imported schema — should
 * cost you one card, not the whole page.
 */

declare(strict_types=1);

require_once __DIR__ . '/inc/panel.php';

require_admin();

/** One value, or null when the query could not run. */
function dash_one(string $sql, array $params = []): ?int
{
    try {
        return (int) qVal($sql, $params);
    } catch (Throwable $e) {
        error_log('dashboard: ' . $e->getMessage());
        return null;
    }
}

/** A list of rows, or an empty array. */
function dash_rows(string $sql, array $params = []): array
{
    try {
        return qAll($sql, $params);
    } catch (Throwable $e) {
        error_log('dashboard: ' . $e->getMessage());
        return [];
    }
}

/** A number that might be missing, shown as an em dash rather than a false zero. */
function dash_num(?int $n): string
{
    return $n === null ? '—' : number_format($n);
}

$c = [
    'places'  => dash_one('SELECT COUNT(*) FROM places WHERE deleted_at IS NULL'),
    'pub'     => dash_one("SELECT COUNT(*) FROM places WHERE deleted_at IS NULL AND status = 'published'"),
    'images'  => dash_one('SELECT COUNT(*) FROM place_images'),
    'plans'   => dash_one('SELECT COUNT(*) FROM itineraries WHERE deleted_at IS NULL'),
    'badges'  => dash_one('SELECT COUNT(*) FROM badges WHERE is_active = 1'),
    'users'   => dash_one('SELECT COUNT(*) FROM admin_users WHERE is_active = 1'),
    'locs'    => dash_one('SELECT COUNT(*) FROM localities'),
    'maps'    => dash_one("SELECT COUNT(*) FROM saved_maps WHERE status = 'active'"),
];

// This week. Everything is "last 7 days" rather than "since Monday", because a
// rolling window is what people actually mean when they ask how things are going.
$week = [
    'cards'     => dash_one('SELECT COUNT(*) FROM saved_maps WHERE created_at > NOW() - INTERVAL 7 DAY'),
    'views'     => dash_one('SELECT COALESCE(SUM(views),0) FROM saved_maps'),
    'downloads' => dash_one("SELECT COUNT(*) FROM card_events WHERE event = 'download' AND created_at > NOW() - INTERVAL 7 DAY"),
    'shares'    => dash_one("SELECT COUNT(*) FROM card_events WHERE event IN ('share','share_click') AND created_at > NOW() - INTERVAL 7 DAY"),
];

$topPlaces = dash_rows(
    "SELECT p.id, p.name_en, p.slug, p.views, p.visit_marks, p.status, m.name_en AS mun
       FROM places p
       JOIN municipalities m ON m.id = p.municipality_id
      WHERE p.deleted_at IS NULL
      ORDER BY p.visit_marks DESC, p.views DESC
      LIMIT 8"
);

$topMuns = dash_rows(
    "SELECT m.name_en, COUNT(*) AS marks
       FROM saved_map_items smi
       JOIN municipalities m ON m.id = smi.item_id
      WHERE smi.item_type = 'municipality'
      GROUP BY m.id, m.name_en
      ORDER BY marks DESC
      LIMIT 8"
);

$stale   = dash_rows('SELECT * FROM v_stale_places LIMIT 6');
$staleN  = dash_one('SELECT COUNT(*) FROM v_stale_places');
$noImg   = dash_rows('SELECT * FROM v_missing_images LIMIT 6');
$noImgN  = dash_one('SELECT COUNT(*) FROM v_missing_images');

// Drafts nobody has touched in a month. Not in the roadmap's list, but it is the
// same kind of problem as the other two and costs one query.
$idleDrafts = dash_rows(
    "SELECT id, name_en, status, updated_at
       FROM places
      WHERE deleted_at IS NULL AND status = 'draft'
        AND updated_at < NOW() - INTERVAL 30 DAY
      ORDER BY updated_at ASC
      LIMIT 6"
);
$idleN = dash_one(
    "SELECT COUNT(*) FROM places
      WHERE deleted_at IS NULL AND status = 'draft' AND updated_at < NOW() - INTERVAL 30 DAY"
);

$recent = dash_rows(
    "SELECT a.module, a.action, a.entity_type, a.entity_id, a.created_at, u.name, u.email
       FROM activity_log a
       LEFT JOIN admin_users u ON u.id = a.admin_id
      ORDER BY a.id DESC
      LIMIT 10"
);

$newFeedback = dash_one("SELECT COUNT(*) FROM feedback WHERE status = 'new'");
$todo = ($staleN ?? 0) + ($noImgN ?? 0) + ($idleN ?? 0) + ($newFeedback ?? 0);

admin_head(['title' => 'Dashboard', 'nav' => 'dash']);
?>

<div class="ad-head">
  <div>
    <h1>Dashboard</h1>
    <p class="ad-lede">
      <?= e(SITE_NAME) ?> — <?= dash_num($c['pub']) ?> of <?= dash_num($c['places']) ?>
      places published across <?= dash_num($c['locs']) ?> localities.
    </p>
  </div>
  <div class="ad-head-act">
    <?php if (admin_can('content.edit')): ?>
      <a class="ad-btn primary" href="<?= e(admin_url('place-edit.php')) ?>">Add a place</a>
    <?php endif; ?>
    <a class="ad-btn ghost" href="<?= e(APP_BASE) ?>/index.php" target="_blank" rel="noopener">View site ↗</a>
  </div>
</div>

<?php if ($todo > 0): ?>
  <p class="ad-note warn">
    <b><?= (int) $todo ?> thing<?= $todo === 1 ? '' : 's' ?></b> could do with a look —
    see the lists below.
  </p>
<?php endif; ?>

<!-- ------------------------------------------------------------- the totals -->
<div class="ad-stats" style="margin-block-end:18px">
  <div class="ad-stat">
    <b><?= dash_num($c['places']) ?></b>
    <span>Places <span class="ad-muted">· <?= dash_num($c['pub']) ?> published</span></span>
  </div>
  <div class="ad-stat">
    <b><?= dash_num($c['images']) ?></b>
    <span>Images</span>
  </div>
  <div class="ad-stat">
    <b><?= dash_num($c['plans']) ?></b>
    <span>Itineraries</span>
  </div>
  <div class="ad-stat">
    <b><?= dash_num($c['badges']) ?></b>
    <span>Active badges</span>
  </div>
  <div class="ad-stat">
    <b><?= dash_num($c['users']) ?></b>
    <span>Admin users</span>
  </div>
</div>

<!-- ------------------------------------------------------------ this week -->
<div class="ad-panel">
  <h2>Last 7 days</h2>
  <div class="ad-stats">
    <div class="ad-stat">
      <b><?= dash_num($week['cards']) ?></b>
      <span>Maps saved</span>
    </div>
    <div class="ad-stat">
      <b><?= dash_num($week['views']) ?></b>
      <span>Shared-map views <span class="ad-muted">· all time</span></span>
    </div>
    <div class="ad-stat">
      <b><?= dash_num($week['downloads']) ?></b>
      <span>Cards downloaded</span>
    </div>
    <div class="ad-stat">
      <b><?= dash_num($week['shares']) ?></b>
      <span>Share clicks</span>
    </div>
    <div class="ad-stat">
      <b><?= dash_num($c['maps']) ?></b>
      <span>Live shared maps</span>
    </div>
  </div>
  <?php if (($week['cards'] ?? 0) === 0 && ($c['maps'] ?? 0) === 0): ?>
    <p class="ad-muted" style="margin-block:14px 0">
      No saved maps yet. The counter only moves once someone finishes a card and
      saves it — until then this row stays at zero, which is expected on a fresh
      install.
    </p>
  <?php endif; ?>
</div>

<div class="ad-grid-2">

  <!-- -------------------------------------------------------- most marked -->
  <div class="ad-panel">
    <h2>Most marked places</h2>
    <?php if ($topPlaces === []): ?>
      <?php ad_empty('No places yet.'); ?>
    <?php else: ?>
      <div class="ad-tablewrap">
        <table class="ad-table">
          <thead><tr><th>Place</th><th>Municipality</th><th class="num">Marks</th><th class="num">Views</th></tr></thead>
          <tbody>
          <?php foreach ($topPlaces as $p): ?>
            <tr>
              <td>
                <a class="ad-rowlink" href="<?= e(admin_url('place-edit.php', ['id' => (int) $p['id']])) ?>">
                  <?= e((string) $p['name_en']) ?>
                </a>
                <?php if ($p['status'] !== 'published'): ?>
                  <span class="ad-sub"><?= e((string) $p['status']) ?></span>
                <?php endif; ?>
              </td>
              <td><?= e((string) $p['mun']) ?></td>
              <td class="num"><?= (int) $p['visit_marks'] ?></td>
              <td class="num"><?= (int) $p['views'] ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php if (($topPlaces[0]['visit_marks'] ?? 0) === 0): ?>
        <p class="ad-muted" style="margin-block-start:12px">
          Marks come from people ticking a locality that contains this place, so
          all zeros here is normal before launch.
        </p>
      <?php endif; ?>
    <?php endif; ?>
  </div>

  <!-- --------------------------------------------------- most municipalities -->
  <div class="ad-panel">
    <h2>Most visited municipalities</h2>
    <?php if ($topMuns === []): ?>
      <?php ad_empty('No saved maps yet, so there is nothing to rank.'); ?>
    <?php else: ?>
      <?php $maxMarks = max(1, (int) $topMuns[0]['marks']); ?>
      <ul class="ad-list">
        <?php foreach ($topMuns as $m): ?>
          <li>
            <span><?= e((string) $m['name_en']) ?></span>
            <span class="ad-muted"><?= (int) $m['marks'] ?> marks</span>
          </li>
        <?php endforeach; ?>
      </ul>
      <div class="ad-bar" style="margin-block-start:14px">
        <i style="inline-size:<?= (int) round(100 * (int) $topMuns[0]['marks'] / $maxMarks) ?>%"></i>
      </div>
    <?php endif; ?>
  </div>

</div>

<!-- ------------------------------------------------------------- warnings -->
<h2 id="todo" style="font-size:1.05rem;margin-block:6px 12px">Needs a look</h2>

<div class="ad-grid-2">

  <div class="ad-panel">
    <h2>
      Details not checked
      <?php if (($staleN ?? 0) > 0): ?><span class="ad-chip warn"><?= (int) $staleN ?></span><?php endif; ?>
    </h2>
    <p class="ad-help">
      Published places whose facts have never been verified, or were last checked
      more than 90 days ago. Set <b>Details checked</b> on the place editor once
      you have been through the opening hours and prices.
    </p>
    <?php if ($stale === []): ?>
      <?php ad_empty('Everything published has been checked recently.'); ?>
    <?php else: ?>
      <ul class="ad-list">
        <?php foreach ($stale as $p): ?>
          <li>
            <a class="ad-rowlink" href="<?= e(admin_url('place-edit.php', ['id' => (int) $p['id']])) ?>">
              <?= e((string) $p['name_en']) ?>
            </a>
            <span class="ad-muted">
              <?= $p['verified_at'] === null
                    ? 'never checked'
                    : (int) $p['days_since_check'] . ' days' ?>
            </span>
          </li>
        <?php endforeach; ?>
      </ul>
      <?php if (($staleN ?? 0) > count($stale)): ?>
        <p class="ad-muted">…and <?= (int) $staleN - count($stale) ?> more.</p>
      <?php endif; ?>
    <?php endif; ?>
  </div>

  <div class="ad-panel">
    <h2>
      No image
      <?php if (($noImgN ?? 0) > 0): ?><span class="ad-chip warn"><?= (int) $noImgN ?></span><?php endif; ?>
    </h2>
    <p class="ad-help">
      A place with no picture is a place nobody clicks. Upload one from the
      Media screen, or from the place editor.
    </p>
    <?php if ($noImg === []): ?>
      <?php ad_empty('Every place has at least one image.'); ?>
    <?php else: ?>
      <ul class="ad-list">
        <?php foreach ($noImg as $p): ?>
          <li>
            <a class="ad-rowlink" href="<?= e(admin_url('place-edit.php', ['id' => (int) $p['id']])) ?>">
              <?= e((string) $p['name_en']) ?>
            </a>
            <span class="ad-muted"><?= e((string) $p['municipality']) ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
      <?php if (($noImgN ?? 0) > count($noImg)): ?>
        <p class="ad-muted">…and <?= (int) $noImgN - count($noImg) ?> more.</p>
      <?php endif; ?>
    <?php endif; ?>
  </div>

  <?php if ($idleDrafts !== []): ?>
    <div class="ad-panel">
      <h2>Drafts left sitting <span class="ad-chip mute"><?= (int) $idleN ?></span></h2>
      <p class="ad-help">Started more than 30 days ago and not touched since. Finish them or bin them.</p>
      <ul class="ad-list">
        <?php foreach ($idleDrafts as $p): ?>
          <li>
            <a class="ad-rowlink" href="<?= e(admin_url('place-edit.php', ['id' => (int) $p['id']])) ?>">
              <?= e((string) $p['name_en']) ?>
            </a>
            <span class="ad-muted"><?= e(ad_when((string) $p['updated_at'])) ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <?php if (($newFeedback ?? 0) > 0): ?>
    <div class="ad-panel">
      <h2>New feedback <span class="ad-chip warn"><?= (int) $newFeedback ?></span></h2>
      <p class="ad-help">
        Reports and suggestions from visitors that nobody has picked up yet.
      </p>
      <p><a class="ad-btn" href="<?= e(admin_url('feedback.php', ['status' => 'new'])) ?>">Open the feedback queue</a></p>
    </div>
  <?php endif; ?>

</div>

<!-- ---------------------------------------------------------- recent edits -->
<div class="ad-panel">
  <h2>Recent edits</h2>
  <?php if ($recent === []): ?>
    <?php ad_empty('Nothing has been changed yet.'); ?>
  <?php else: ?>
    <div class="ad-tablewrap">
      <table class="ad-table">
        <thead><tr><th>When</th><th>Who</th><th>What</th><th>Action</th></tr></thead>
        <tbody>
        <?php foreach ($recent as $r): ?>
          <tr>
            <td><?= e(ad_when((string) $r['created_at'])) ?></td>
            <td><?= e(trim((string) $r['name']) !== '' ? (string) $r['name'] : (string) ($r['email'] ?? '—')) ?></td>
            <td>
              <?= e((string) $r['module']) ?>
              <?php if ($r['entity_id'] !== null): ?>
                <span class="ad-sub">#<?= (int) $r['entity_id'] ?></span>
              <?php endif; ?>
            </td>
            <td><?= ad_chip((string) $r['action'], $r['action'] === 'purge' ? 'bad' : '') ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <p style="margin-block:14px 0">
      <a class="ad-btn ghost" href="<?= e(admin_url('activity.php')) ?>">Full activity log</a>
    </p>
  <?php endif; ?>
</div>

<?php
admin_foot();
