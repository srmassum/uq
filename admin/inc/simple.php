<?php
/**
 * A whole list-and-edit screen from one declaration.
 *
 * Seven of the panel's modules — municipalities, categories, tags, levels,
 * badges, card themes, personalities — are the same screen with different
 * columns: a table, a search box, a form, a delete. Writing that seven times
 * would be seven chances for one of them to forget its CSRF check or its
 * capability gate, and seven places to edit when the design changes.
 *
 * So this file owns the shape and each module owns only its declaration.
 *
 * Definition keys:
 *   table, module, entity   passed straight to record_save()
 *   fields                  the write spec (see records.php)
 *   columns                 list columns: k, t, fmt?, num?, w?
 *   singular                "Municipality" — used in buttons and messages
 *   order                   ORDER BY clause, from PHP source, never input
 *   search                  columns the search box checks with LIKE
 *   soft                    has a deleted_at bin
 *   form_title, help        copy for the editor
 *   wide                    field keys that should span the form grid
 */

declare(strict_types=1);

function simple_crud(array $def): void
{
    $table    = (string) $def['table'];
    $singular = (string) ($def['singular'] ?? 'Record');
    $soft     = !empty($def['soft']);
    $nav      = (string) ($def['nav'] ?? '');
    $perPage  = 25;

    admin_require_csrf();

    $bin = $soft && get_str('bin') === '1';

    // ---------------------------------------------------------------- actions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $do = post_str('do');

        if ($do === 'delete' || $do === 'purge' || $do === 'restore') {
            $id = post_int('id');
            if (!admin_can('content.edit') || empty($def['deletable'])) {
                admin_back(admin_url($def['page'], $bin ? ['bin' => 1] : []), 'You do not have permission to do that.', 'bad');
            }

            // Only places, itineraries, collections and guide pages have a
            // deleted_at column. On the tables that do not, "delete" means the
            // real thing — which is why those modules have to say `deletable`
            // explicitly, and why municipalities never does: removing one
            // cascades to every locality and place inside it.
            if ($do === 'restore') {
                $ok  = $soft && record_restore($def, $id);
                $msg = $ok ? "$singular restored." : "Could not restore that $singular.";
            } elseif ($do === 'delete' && $soft) {
                $ok  = record_soft_delete($def, $id);
                $msg = $ok ? "$singular moved to the bin." : "Could not move that $singular to the bin.";
            } else {
                $ok  = record_purge($def, $id);
                $msg = $ok ? "$singular deleted." : "Could not delete that $singular.";
            }

            admin_back(admin_url($def['page'], $bin ? ['bin' => 1] : []), $msg, $ok ? 'ok' : 'bad');
        }

        $bulk = post_str('bulk');
        if ($bulk !== '') {
            $r = bulk_apply($def, posted_ids(), $bulk);
            admin_back(
                admin_url($def['page'], $bin ? ['bin' => 1] : []),
                $r['done'] . ' ' . strtolower($singular) . ($r['done'] === 1 ? '' : 's') . ' updated'
                . ($r['skipped'] > 0 ? ', ' . $r['skipped'] . ' skipped' : '') . '.'
            );
        }

        // Save (create or update).
        $id = post_int('id') ?: null;
        $res = record_save($def, $_POST, $id);
        if ($res['ok']) {
            admin_back(admin_url($def['page']), $singular . ' saved.');
        }
        // Errors fall through and are shown on the form below.
        $formErrors = $res['errors'];
        $editingId  = $id ?? 0;
    } else {
        $formErrors = [];
        $editingId  = get_int('id');
    }

    $creating = get_str('new') === '1';

    // ------------------------------------------------------------------ list
    $q      = get_str('q');
    $sort   = get_str('sort');
    $page   = max(1, get_int('p', 1));
    $offset = ($page - 1) * $perPage;

    $where  = $soft ? ($bin ? ' WHERE deleted_at IS NOT NULL' : ' WHERE deleted_at IS NULL') : '';
    $bind   = [];

    if ($q !== '' && !empty($def['search'])) {
        $parts = [];
        foreach ($def['search'] as $col) {
            // Column names come from the module's declaration, which is PHP
            // source — but the check costs nothing and makes that guarantee
            // local rather than something you have to go and verify.
            if (!preg_match('/^[a-z_]+$/', $col)) { continue; }
            $parts[] = '`' . $col . '` LIKE ?';
            $bind[]  = '%' . admin_like_escape($q) . '%';
        }
        if ($parts !== []) {
            $where .= ($where === '' ? ' WHERE ' : ' AND ') . '(' . implode(' OR ', $parts) . ')';
        }
    }

    $order = (string) ($def['order'] ?? 'id DESC');
    $total = 0;
    $rows  = [];
    try {
        $total = (int) qVal("SELECT COUNT(*) FROM `$table`" . $where, $bind);
        $rows = qAll(
            "SELECT * FROM `$table`" . $where . " ORDER BY $order LIMIT $perPage OFFSET $offset",
            $bind
        );
    } catch (Throwable $e) {
        error_log("simple_crud($table): " . $e->getMessage());
        ad_note('Could not read <code>' . e($table) . '</code>. Has <code>sql/schema.sql</code> been imported?', 'bad');
    }

    // ------------------------------------------------------------- the record
    $editing = $editingId > 0 ? record_by_id($table, $editingId) : null;
    if ($editingId > 0 && $editing === null) {
        ad_note('That ' . e(strtolower($singular)) . ' no longer exists.', 'bad');
    }

    $showForm = $creating || $editing !== null || $formErrors !== [];

    // ----------------------------------------------------------------- render
    admin_head(['title' => $def['title'], 'nav' => $nav]);

    $actions = [];
    if (!$bin && admin_can('content.edit')) {
        $actions[] = ['href' => admin_url($def['page'], ['new' => 1]), 'label' => 'Add ' . strtolower($singular), 'kind' => 'primary'];
    }
    if ($soft) {
        $actions[] = $bin
            ? ['href' => admin_url($def['page']), 'label' => 'Back to the list', 'kind' => 'ghost']
            : ['href' => admin_url($def['page'], ['bin' => 1]), 'label' => 'Bin', 'kind' => 'ghost'];
    }

    ad_title($def['title'], (string) ($def['sub'] ?? ''), $actions);

    if ($formErrors !== []) {
        ad_note('Nothing was saved — please check the highlighted fields.', 'bad');
    }

    if ($showForm && admin_can('content.edit')) {
        $values = $editing ?? [];
        // A failed save must not throw away what was typed.
        foreach (array_keys($formErrors) as $k) {
            $values[$k] = $_POST[$k] ?? '';
        }
        if ($formErrors !== []) {
            foreach ($_POST as $k => $v) {
                if (is_string($v) && !array_key_exists($k, $values)) { $values[$k] = $v; }
            }
        }

        ad_panel_open();
        ad_form_open(
            admin_url($def['page']),
            $editing === null
                ? 'New ' . strtolower($singular)
                : 'Edit ' . strtolower($singular),
            (string) ($def['help'] ?? '')
        );

        echo '<input type="hidden" name="id" value="' . (int) ($editing['id'] ?? 0) . '">';

        foreach ($def['fields'] as $f) {
            $v = $values[$f['k']] ?? ($f['default'] ?? '');
            if (($f['type'] ?? '') === 'password') {
                $v = '';
            }
            if (isset($formErrors[$f['k']])) {
                echo '<div class="ad-field wide"><span class="ad-err">' . e($f['t'] . ': ' . $formErrors[$f['k']]) . '</span></div>';
            }
            ad_field($f, $v);
        }

        $danger = $dangerMsg = '';
        if ($editing !== null && !empty($def['deletable'])) {
            $danger = $soft ? 'Move to bin' : 'Delete';
            if (!$soft) {
                $dangerMsg = 'Delete this for good? Places pointing at it will lose the link, and there is no undo.';
            }
        }
        ad_form_close('Save ' . strtolower($singular), admin_url($def['page']), $danger, $dangerMsg);
        ad_panel_close();
    } elseif (!$bin && !admin_can('content.edit')) {
        ad_note('Your account can view this list but not change it.', 'info');
    }

    // --------------------------------------------------------------- the list
    ad_panel_open();

    if ($q !== '' || $sort !== '') {
        echo '<p class="ad-muted" style="margin:0 0 12px">'
           . (int) $total . ' match' . ($total === 1 ? '' : 'es')
           . ($q !== '' ? ' for “' . e($q) . '”' : '') . '.</p>';
    }

    $fields = [];
    if (!empty($def['search'])) {
        $fields[] = ['k' => 'q', 't' => 'Search', 'ph' => 'Name…'];
    }
    if (!empty($def['sorts'])) {
        $fields[] = ['k' => 'sort', 't' => 'Sort', 'type' => 'select', 'options' => $def['sorts']];
    }

    if ($fields !== []) {
        ad_filters(admin_url($def['page'], $bin ? ['bin' => 1] : []), $fields, ['q' => $q, 'sort' => $sort]);
    }

    $cols = $def['columns'];
    $bulkActions = $bin
        ? ['restore' => 'Restore', 'purge' => 'Delete for good']
        : array_filter([
            'publish'   => !empty($def['has_status']) ? 'Publish' : null,
            'unpublish' => !empty($def['has_status']) ? 'Unpublish' : null,
            // bulk_apply()'s "delete" goes through record_soft_delete(), which
            // refuses on a table with no deleted_at column. So a hard-delete
            // table uses "purge" here and the label hides the difference.
            $soft ? 'delete' : 'purge' => !empty($def['deletable'])
                ? ($soft ? 'Move to bin' : 'Delete')
                : null,
        ]);

    $useBulk = $bulkActions !== [] && admin_can('content.edit');

    if ($useBulk) {
        echo '<form method="post" action="' . e(admin_url($def['page'], $bin ? ['bin' => 1] : [])) . '">';
        echo csrf_field();
    }

    ad_table($cols, $rows, [
        'bulk'     => $useBulk,
        'empty'    => $bin ? 'The bin is empty.' : 'Nothing here yet.',
        'row_link' => static fn(array $r): string => admin_url($def['page'], ['id' => (int) $r['id']]),
    ]);

    if ($useBulk) {
        ad_bulk_bar($bulkActions);
        echo '</form>';
    }

    $qs = array_filter(['q' => $q, 'sort' => $sort, 'bin' => $bin ? '1' : '']);
    ad_pager($total, $perPage, $page, $qs);

    ad_panel_close();

    admin_foot();
}
