<?php
/**
 * Feedback — "this is wrong" reports and "you should add this" suggestions.
 *
 * Deliberately not a generic CRUD screen. The useful thing here is the queue:
 * what is new, what is being looked at, what is finished. A report about a place
 * that is still sitting in "new" is the whole reason this screen exists.
 */

declare(strict_types=1);

require_once __DIR__ . '/inc/panel.php';

require_admin();

admin_require_csrf();

$perPage = 25;

// ------------------------------------------------------------------ actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!admin_can('feedback.triage')) {
        admin_back(admin_url('feedback.php'), 'You do not have permission to change feedback.', 'bad');
    }

    $id = post_int('id');
    $to = post_str('status');

    $allowed = ['new', 'in_review', 'done', 'rejected'];
    if ($id > 0 && in_array($to, $allowed, true)) {
        try {
            q('UPDATE feedback SET status = ?, admin_note = ? WHERE id = ?',
              [$to, mb_substr(post_str('admin_note'), 0, 2000), $id]);
            log_activity('feedback', 'triage', 'feedback', $id, null, ['status' => $to]);
            admin_back(admin_url('feedback.php', $_GET), 'Marked as ' . str_replace('_', ' ', $to) . '.');
        } catch (Throwable $e) {
            error_log('feedback triage: ' . $e->getMessage());
            admin_back(admin_url('feedback.php', $_GET), 'Could not save that.', 'bad');
        }
    }

    // A delete is a real delete: feedback has no deleted_at column, and keeping
    // spam forever helps nobody.
    if (post_str('do') === 'delete' && $id > 0 && admin_is_admin()) {
        try {
            $row = record_by_id('feedback', $id);
            q('DELETE FROM feedback WHERE id = ?', [$id]);
            log_activity('feedback', 'delete', 'feedback', $id, $row, null);
            admin_back(admin_url('feedback.php', $_GET), 'Deleted.');
        } catch (Throwable $e) {
            error_log('feedback delete: ' . $e->getMessage());
        }
    }

    admin_back(admin_url('feedback.php'), 'Nothing to do.', 'warn');
}

// -------------------------------------------------------------------- reads
$status = get_str('status', 'new');
$kind   = get_str('kind');
$page   = max(1, get_int('p', 1));

$where = [];
$bind  = [];
if (in_array($status, ['new', 'in_review', 'done', 'rejected'], true)) {
    $where[] = 'f.status = ?';
    $bind[]  = $status;
}
if (in_array($kind, ['wrong_info', 'new_place', 'bug', 'other'], true)) {
    $where[] = 'f.kind = ?';
    $bind[]  = $kind;
}
$wsql = $where === [] ? '' : 'WHERE ' . implode(' AND ', $where);

$counts = [];
$rows   = [];
$total  = 0;
try {
    foreach (qAll('SELECT status, COUNT(*) n FROM feedback GROUP BY status') as $r) {
        $counts[(string) $r['status']] = (int) $r['n'];
    }
    $total = (int) qVal("SELECT COUNT(*) FROM feedback f $wsql", $bind);
    $rows  = qAll(
        "SELECT f.* FROM feedback f $wsql ORDER BY f.created_at DESC LIMIT $perPage OFFSET " . (($page - 1) * $perPage),
        $bind
    );
} catch (Throwable $e) {
    error_log('feedback.php: ' . $e->getMessage());
    ad_note('Could not read the feedback table. Has <code>sql/schema.sql</code> been imported?', 'bad');
}

// The place a report points at, if it points at one — a link is worth having.
$placeNames = [];
$ids = [];
foreach ($rows as $r) {
    if ((string) $r['entity_type'] === 'place' && (int) ($r['entity_id'] ?? 0) > 0) {
        $ids[] = (int) $r['entity_id'];
    }
}
if ($ids !== []) {
    try {
        $in = implode(',', array_fill(0, count($ids), '?'));
        foreach (qAll("SELECT id, name_en FROM places WHERE id IN ($in)", $ids) as $p) {
            $placeNames[(int) $p['id']] = (string) $p['name_en'];
        }
    } catch (Throwable $e) {
        error_log('feedback place names: ' . $e->getMessage());
    }
}

// ------------------------------------------------------------------- render
admin_head(['title' => 'Feedback', 'nav' => 'feedback']);

ad_title('Feedback', 'What visitors told us is wrong, missing, or broken.');

$tabs = [
    'new'       => 'New',
    'in_review' => 'In review',
    'done'      => 'Done',
    'rejected'  => 'Rejected',
    ''          => 'All',
];
echo '<p>';
foreach ($tabs as $k => $label) {
    $n = $k === '' ? array_sum($counts) : ($counts[$k] ?? 0);
    $on = $status === $k;
    echo '<a class="ad-btn ' . ($on ? 'primary' : 'ghost') . '" style="margin-inline-end:6px" href="'
       . e(admin_url('feedback.php', $k === '' ? [] : ['status' => $k])) . '">'
       . e($label) . ' <span class="ad-chip' . ($k === 'new' && $n > 0 ? ' warn' : '') . '">' . (int) $n . '</span></a>';
}
echo '</p>';

foreach ($rows as $f) {
    ad_panel_open();
    ?>
    <div style="display:flex;justify-content:space-between;gap:16px;flex-wrap:wrap;align-items:baseline">
      <h2 style="margin:0">
        <?= ad_chip(str_replace('_', ' ', (string) $f['kind']), $f['kind'] === 'wrong_info' ? 'warn' : '') ?>
        <?php if ((string) $f['contact'] !== ''): ?>
          <span class="ad-muted" style="font-size:.85rem">from <?= e((string) $f['contact']) ?></span>
        <?php endif; ?>
      </h2>
      <span class="ad-muted">
        <?= e(ad_when((string) $f['created_at'], false)) ?>
        · <?= e(ad_when((string) $f['created_at'])) ?>
        <?= ad_status_chip((string) $f['status']) ?>
      </span>
    </div>

    <?php if ((int) ($f['entity_id'] ?? 0) > 0): ?>
      <p class="ad-muted">
        About
        <?php if (isset($placeNames[(int) $f['entity_id']])): ?>
          <a href="<?= e(admin_url('place-edit.php', ['id' => (int) $f['entity_id']])) ?>">
            <?= e($placeNames[(int) $f['entity_id']]) ?>
          </a>
        <?php else: ?>
          <?= e((string) $f['entity_type']) ?> #<?= (int) $f['entity_id'] ?>
        <?php endif; ?>
      </p>
    <?php endif; ?>

    <blockquote style="margin:12px 0;padding-inline-start:14px;border-inline-start:3px solid var(--ad-line-2);white-space:pre-wrap"><?= e((string) $f['message']) ?></blockquote>

    <form method="post" action="<?= e(admin_url('feedback.php', $_GET)) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="<?= (int) $f['id'] ?>">
      <div class="ad-grid">
        <?php
        ad_field([
            'k' => 'status', 't' => 'Move to', 'type' => 'select',
            'options' => ['new' => 'New', 'in_review' => 'In review', 'done' => 'Done', 'rejected' => 'Rejected'],
        ], (string) $f['status']);
        ad_field([
            'k' => 'admin_note', 't' => 'Internal note', 'type' => 'textarea', 'rows' => 2,
            'help' => 'What you did about it. Not shown to the visitor.',
        ], (string) ($f['admin_note'] ?? ''));
        ?>
      </div>
      <div class="ad-form-act">
        <button class="ad-btn primary" type="submit">Save</button>
        <?php if (admin_is_admin()): ?>
          <span class="ad-spacer"></span>
          <button class="ad-btn danger" type="submit" name="do" value="delete" formnovalidate
                  data-ad-confirm="Delete this report for good?">Delete</button>
        <?php endif; ?>
      </div>
    </form>
    <?php
    ad_panel_close();
}

if ($rows === []) {
    ad_panel_open();
    ad_empty($status === 'new'
        ? 'Nothing new. Either nobody has complained or somebody has already dealt with it.'
        : 'Nothing in this state.');
    ad_panel_close();
}

ad_pager($total, $perPage, $page, array_filter(['status' => $status, 'kind' => $kind]));

admin_foot();
