<?php
/**
 * The activity log — who changed what, and what it was before.
 *
 * The table it reads is written by log_activity(), which every content write in
 * the panel goes through. The before/after snapshots are the point: "who
 * unpublished this?" is only answered usefully if the answer includes what the
 * row said beforehand.
 *
 * Secrets are redacted on the way *in* (see redact_secrets() in log.php), so a
 * password hash never reaches this screen even for an admin user edit.
 */

declare(strict_types=1);

require_once __DIR__ . '/inc/panel.php';

require_admin();

$perPage = 40;

$module = get_str('module');
$action = get_str('action');
$who    = get_int('admin');
$days   = get_int('days', 30);
if (!in_array($days, [1, 7, 30, 90, 0], true)) {
    $days = 30;
}

$where = [];
$bind  = [];

$knownModules = [];
$knownActions = [];
$people       = [];
$total        = 0;
$rows         = [];
$readError    = '';

try {
    foreach (qAll('SELECT DISTINCT module FROM activity_log ORDER BY module') as $r) {
        $knownModules[] = (string) $r['module'];
    }
    foreach (qAll('SELECT DISTINCT action FROM activity_log ORDER BY action') as $r) {
        $knownActions[] = (string) $r['action'];
    }
    foreach (qAll('SELECT id, name, email FROM admin_users ORDER BY name, email') as $r) {
        $people[(int) $r['id']] = trim((string) $r['name']) !== '' ? (string) $r['name'] : (string) $r['email'];
    }

    if (in_array($module, $knownModules, true)) {
        $where[] = 'l.module = ?';
        $bind[]  = $module;
    }
    if (in_array($action, $knownActions, true)) {
        $where[] = 'l.action = ?';
        $bind[]  = $action;
    }
    if ($who > 0) {
        $where[] = 'l.admin_id = ?';
        $bind[]  = $who;
    }
    if ($days > 0) {
        // Interpolated rather than bound: with emulated prepares off, PDO sends a
        // bound value as a string and MySQL refuses a string as an INTERVAL
        // quantity. $days is an int from a closed list, so this is safe.
        $where[] = "l.created_at > NOW() - INTERVAL {$days} DAY";
    }

    $wsql  = $where === [] ? '' : 'WHERE ' . implode(' AND ', $where);
    $total = (int) qVal("SELECT COUNT(*) FROM activity_log l $wsql", $bind);
    $rows  = qAll(
        "SELECT l.*, a.name AS admin_name, a.email AS admin_email
           FROM activity_log l
           LEFT JOIN admin_users a ON a.id = l.admin_id
           $wsql
          ORDER BY l.created_at DESC, l.id DESC
          LIMIT $perPage OFFSET " . (max(1, get_int('p', 1)) - 1) * $perPage,
        $bind
    );
} catch (Throwable $e) {
    error_log('activity.php: ' . $e->getMessage());
    $readError = $e->getMessage();
}

/**
 * A one-line summary of what changed, drawn from the before/after JSON.
 * Falls back to the action name when there is nothing to diff (a create, or a
 * purge where the after-snapshot is deliberately empty).
 */
function act_summary(array $row): string
{
    $before = $row['before_json'] === null ? null : json_decode((string) $row['before_json'], true);
    $after  = $row['after_json']  === null ? null : json_decode((string) $row['after_json'], true);

    if (!is_array($before) || !is_array($after)) {
        return '';
    }

    $diff = activity_diff($before, $after);
    if ($diff === []) {
        return '';
    }

    $bits = [];
    foreach ($diff as $k => $v) {
        // Long bodies would push everything else off the line; the full
        // before/after is one click away in the detail row.
        $from = mb_substr((string) ($v['from'] ?? ''), 0, 40);
        $to   = mb_substr((string) ($v['to'] ?? ''), 0, 40);
        $bits[] = $k . ': ' . ($from === '' ? '∅' : $from) . ' → ' . ($to === '' ? '∅' : $to);
        if (count($bits) === 3) {
            $bits[] = '…';
            break;
        }
    }
    return implode('; ', $bits);
}

/** Which screens a log row's entity can be opened on, where one exists. */
function act_link(array $row): ?string
{
    $type = (string) $row['entity_type'];
    $id   = (int) ($row['entity_id'] ?? 0);
    if ($id <= 0) {
        return null;
    }

    $pages = [
        'place'       => 'place-edit.php',
        'itinerary'   => 'itinerary-edit.php',
        'collection'  => 'collection-edit.php',
        'guide_page'  => 'guide.php',
        'municipality'=> 'municipalities.php',
        'category'    => 'categories.php',
        'tag'         => 'tags.php',
        'admin_user'  => 'users.php',
    ];

    return isset($pages[$type]) ? admin_url($pages[$type], ['id' => $id]) : null;
}

admin_head(['title' => 'Activity log', 'nav' => 'activity']);

ad_title(
    'Activity log',
    'Every write in this panel, with the values it replaced. Entries are never edited or removed from this screen.'
);

if ($readError !== '') {
    ad_note('Could not read the log: ' . e($readError) . '. Has <code>sql/schema.sql</code> been imported?', 'bad');
}

ad_filters(admin_url('activity.php'), [
    ['k' => 'module', 't' => 'Module', 'type' => 'select', 'options' => ['' => 'Any'] + array_combine($knownModules, $knownModules)],
    ['k' => 'action', 't' => 'Action', 'type' => 'select', 'options' => ['' => 'Any'] + array_combine($knownActions, $knownActions)],
    ['k' => 'admin',  't' => 'Who',    'type' => 'select', 'options' => ['' => 'Anyone'] + $people],
    ['k' => 'days',   't' => 'When',   'type' => 'select', 'options' => [
        1 => 'Last 24 hours', 7 => 'Last 7 days', 30 => 'Last 30 days',
        90 => 'Last 90 days', 0 => 'All time',
    ]],
], ['module' => $module, 'action' => $action, 'admin' => $who > 0 ? $who : '', 'days' => $days]);

if ($rows === []) {
    ad_panel_open();
    ad_empty($total === 0 && $where === []
        ? 'Nothing has been changed in the panel yet. The first edit will appear here.'
        : 'No entries match those filters.');
    ad_panel_close();
} else {
    ad_panel_open();
    ?>

    <div class="ad-tablewrap">
      <table class="ad-table">
        <thead>
          <tr>
            <th style="inline-size:150px">When</th>
            <th style="inline-size:140px">Who</th>
            <th style="inline-size:120px">Module</th>
            <th style="inline-size:110px">Action</th>
            <th>What changed</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
          <?php
          $summary = act_summary($r);
          $link    = act_link($r);
          $who2    = trim((string) ($r['admin_name'] ?? '')) !== ''
                   ? (string) $r['admin_name']
                   : (string) ($r['admin_email'] ?? '');
          ?>
          <tr>
            <td title="<?= e((string) $r['created_at']) ?>">
              <?= e(ad_when((string) $r['created_at'], false)) ?>
              <span class="ad-sub"><?= e(ad_when((string) $r['created_at'])) ?></span>
            </td>
            <td>
              <?php if ($who2 === ''): ?>
                <span class="ad-muted">system</span>
              <?php else: ?>
                <?= e($who2) ?>
              <?php endif; ?>
            </td>
            <td><?= e((string) $r['module']) ?></td>
            <td><?= ad_chip(str_replace('_', ' ', (string) $r['action']),
                    $r['action'] === 'purge' || $r['action'] === 'soft_delete' ? 'bad'
                      : ($r['action'] === 'create' ? 'ok' : '')) ?></td>
            <td>
              <?php if ((int) ($r['entity_id'] ?? 0) > 0): ?>
                <?php if ($link !== null): ?>
                  <a href="<?= e($link) ?>"><?= e((string) $r['entity_type']) ?> #<?= (int) $r['entity_id'] ?></a>
                <?php else: ?>
                  <span class="ad-muted"><?= e((string) $r['entity_type']) ?> #<?= (int) $r['entity_id'] ?></span>
                <?php endif; ?>
              <?php endif; ?>
              <?php if ($summary !== ''): ?>
                <span class="ad-sub"><?= e($summary) ?></span>
              <?php endif; ?>
              <?php if ($r['before_json'] !== null || $r['after_json'] !== null): ?>
                <details style="margin-block-start:6px">
                  <summary class="ad-muted" style="cursor:pointer;font-size:12.5px">Before / after</summary>
                  <div class="ad-grid-2" style="margin-block-start:8px">
                    <?php foreach (['Before' => $r['before_json'], 'After' => $r['after_json']] as $label => $json): ?>
                      <div>
                        <p class="ad-help" style="margin:0 0 4px"><?= e($label) ?></p>
                        <?php if ($json === null): ?>
                          <span class="ad-muted">—</span>
                        <?php else: ?>
                          <?php
                          // Pretty-printed for reading, not for re-parsing, so a
                          // decode failure can safely fall back to the raw string.
                          $pretty = json_decode((string) $json, true);
                          $pretty = is_array($pretty)
                              ? json_encode($pretty, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                              : (string) $json;
                          ?>
                          <pre class="mono" style="margin:0;padding:10px;border:1px solid var(--ad-line);border-radius:9px;background:var(--ad-bg);font-size:12px;max-block-size:260px;overflow:auto"><?= e((string) $pretty) ?></pre>
                        <?php endif; ?>
                      </div>
                    <?php endforeach; ?>
                  </div>
                </details>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <?php
    ad_panel_close();
    ad_pager($total, $perPage, max(1, get_int('p', 1)), array_filter([
        'module' => $module, 'action' => $action,
        'admin' => $who > 0 ? $who : null, 'days' => $days,
    ], static fn($v): bool => $v !== null && $v !== ''));
}

admin_foot();
