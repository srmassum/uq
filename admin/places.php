<?php
/**
 * The places list.
 *
 * Hand-written rather than built on simple_crud(), because every useful filter
 * here spans a join — municipality, category, image count, staleness — and the
 * generic screen only knows how to LIKE-search its own columns.
 *
 * The write path is still the shared one: record_save(), bulk_apply() and the
 * soft-delete trio, so nothing on this page touches SQL for a change.
 */

declare(strict_types=1);

require_once __DIR__ . '/inc/panel.php';
require_once __DIR__ . '/inc/place-def.php';

require_admin();

$def   = place_def();
$table = 'places';

admin_require_csrf();

// ------------------------------------------------------------------ actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ids = posted_ids();
    $bulk = post_str('bulk');

    if ($bulk !== '' && $ids !== []) {
        $r = bulk_apply($def, $ids, $bulk);
        admin_back(
            admin_url('places.php', $_GET),
            $r['done'] . ' place' . ($r['done'] === 1 ? '' : 's') . ' updated'
            . ($r['skipped'] > 0 ? ', ' . $r['skipped'] . ' skipped' : '') . '.'
        );
    }

    admin_back(admin_url('places.php'), 'Nothing was selected.', 'warn');
}

// ------------------------------------------------------------------- filters
$q      = get_str('q');
$mun    = get_int('mun');
$cat    = get_int('cat');
$status = get_str('status');
$flag   = get_str('flag');
$bin    = get_str('bin') === '1';
$sort   = get_str('sort', 'updated');

$perPage = 25;
$page    = max(1, get_int('p', 1));
$offset  = ($page - 1) * $perPage;

$where = [$bin ? 'p.deleted_at IS NOT NULL' : 'p.deleted_at IS NULL'];
$bind  = [];

if ($q !== '') {
    $where[] = '(p.name_en LIKE ? OR p.name_ar LIKE ? OR p.slug LIKE ?)';
    $needle  = '%' . admin_like_escape($q) . '%';
    array_push($bind, $needle, $needle, $needle);
}
if ($mun > 0)    { $where[] = 'p.municipality_id = ?'; $bind[] = $mun; }
if ($cat > 0)    { $where[] = 'p.category_id = ?';     $bind[] = $cat; }
if (in_array($status, ['draft', 'published', 'archived'], true)) {
    $where[] = 'p.status = ?';
    $bind[]  = $status;
}

// A place with no image needs a LEFT JOIN test, which is why this cannot be a
// column filter like the others.
$joins = 'JOIN municipalities m ON m.id = p.municipality_id
          LEFT JOIN categories c ON c.id = p.category_id
          LEFT JOIN (SELECT place_id, COUNT(*) n, SUM(is_cover) covers
                       FROM place_images GROUP BY place_id) i
                 ON i.place_id = p.id';

switch ($flag) {
    case 'gem':      $where[] = 'p.is_hidden_gem = 1'; break;
    case 'featured': $where[] = 'p.is_featured = 1';   break;
    case 'noimg':    $where[] = 'i.place_id IS NULL';  break;
    case 'unchecked':
        $where[] = "(p.verified_at IS NULL OR p.verified_at < CURDATE() - INTERVAL 90 DAY)";
        break;
    case 'uncat':    $where[] = 'p.category_id IS NULL'; break;
}

// Only these three, chosen from this map — a sort parameter is never
// concatenated into the query.
$orders = [
    'updated' => 'p.updated_at DESC',
    'name'    => 'p.name_en ASC',
    'views'   => 'p.views DESC, p.visit_marks DESC',
    'marks'   => 'p.visit_marks DESC, p.views DESC',
    'oldest'  => 'p.updated_at ASC',
];
$orderBy = $orders[$sort] ?? $orders['updated'];

$wsql = 'WHERE ' . implode(' AND ', $where);

$total = 0;
$rows  = [];
try {
    $total = (int) qVal("SELECT COUNT(*) FROM places p $joins $wsql", $bind);
    $rows  = qAll(
        "SELECT p.*, m.name_en AS mun_name, c.name_en AS cat_name,
                COALESCE(i.n, 0) AS img_n, COALESCE(i.covers, 0) AS img_covers
           FROM places p $joins $wsql
          ORDER BY $orderBy
          LIMIT $perPage OFFSET $offset",
        $bind
    );
} catch (Throwable $e) {
    error_log('places.php: ' . $e->getMessage());
    ad_note('Could not read the places table. Has <code>sql/schema.sql</code> been imported?', 'bad');
}

// Filter options.
$muns = [];
$cats = [];
try {
    foreach (qAll('SELECT id, name_en FROM municipalities ORDER BY sort_order, name_en') as $r) {
        $muns[(int) $r['id']] = (string) $r['name_en'];
    }
    foreach (qAll('SELECT id, name_en FROM categories ORDER BY sort_order, name_en') as $r) {
        $cats[(int) $r['id']] = (string) $r['name_en'];
    }
} catch (Throwable $e) {
    error_log('places.php options: ' . $e->getMessage());
}

// -------------------------------------------------------------------- render
admin_head(['title' => 'Places', 'nav' => 'places']);

$actions = [];
if (!$bin && admin_can('content.edit')) {
    $actions[] = ['href' => admin_url('place-edit.php'), 'label' => 'Add a place', 'kind' => 'primary'];
}
$actions[] = $bin
    ? ['href' => admin_url('places.php'), 'label' => 'Back to the list', 'kind' => 'ghost']
    : ['href' => admin_url('places.php', ['bin' => 1]), 'label' => 'Bin', 'kind' => 'ghost'];

ad_title('Places', $bin
    ? 'Deleted places. Nothing here is visible on the site. Restore one, or delete it for good.'
    : 'Everything in the guide, published or not.', $actions);

ad_panel_open();
ad_filters(
    admin_url('places.php', $bin ? ['bin' => 1] : []),
    [
        ['k' => 'q', 't' => 'Search', 'ph' => 'Name or slug…'],
        ['k' => 'mun', 't' => 'Municipality', 'type' => 'select', 'options' => [0 => 'Any'] + $muns],
        ['k' => 'cat', 't' => 'Category', 'type' => 'select', 'options' => [0 => 'Any'] + $cats],
        ['k' => 'status', 't' => 'Status', 'type' => 'select', 'options' => [
            '' => 'Any', 'published' => 'Published', 'draft' => 'Draft', 'archived' => 'Archived',
        ]],
        ['k' => 'flag', 't' => 'Show', 'type' => 'select', 'options' => [
            ''          => 'Everything',
            'gem'       => 'Hidden gems',
            'featured'  => 'Featured only',
            'noimg'     => 'With no image',
            'unchecked' => 'Details not checked',
            'uncat'     => 'With no category',
        ]],
        ['k' => 'sort', 't' => 'Sort', 'type' => 'select', 'options' => [
            'updated' => 'Recently changed',
            'name'    => 'Name A–Z',
            'marks'   => 'Most marked',
            'views'   => 'Most viewed',
            'oldest'  => 'Least recently changed',
        ]],
    ],
    ['q' => $q, 'mun' => $mun, 'cat' => $cat, 'status' => $status, 'flag' => $flag, 'sort' => $sort]
);

echo '<p class="ad-muted" style="margin:0 0 12px">' . (int) $total
   . ' place' . ($total === 1 ? '' : 's') . '.</p>';

$bulkActions = $bin
    ? ['restore' => 'Restore', 'purge' => 'Delete for good']
    : [
        'publish'   => 'Publish',
        'unpublish' => 'Back to draft',
        'archive'   => 'Archive',
        'feature'   => 'Mark featured',
        'unfeature' => 'Unmark featured',
        'delete'    => 'Move to bin',
    ];
$useBulk = admin_can('content.edit');

if ($useBulk) {
    echo '<form method="post" action="' . e(admin_url('places.php', ['bin' => $bin ? 1 : ''])) . '">';
    echo csrf_field();
}

ad_table([
    ['k' => 'name_en', 't' => 'Place', 'fmt' => static function (array $r): string {
        $out = '<a class="ad-rowlink" href="'
             . e(admin_url('place-edit.php', ['id' => (int) $r['id']])) . '">'
             . e((string) $r['name_en']) . '</a>';
        $bits = [];
        if ((string) $r['name_ar'] === '') { $bits[] = '<span class="ad-chip bad">no Arabic</span>'; }
        if ((int) $r['is_featured'])       { $bits[] = ad_chip('featured', 'info'); }
        if ((int) $r['is_hidden_gem'])     { $bits[] = ad_chip('hidden gem'); }
        if ($bits !== []) {
            $out .= '<span class="ad-sub">' . implode(' ', $bits) . '</span>';
        }
        return $out;
    }],
    ['k' => 'mun_name', 't' => 'Municipality'],
    ['k' => 'cat_name', 't' => 'Category', 'fmt' => static fn(array $r): string =>
        (string) ($r['cat_name'] ?? '') === ''
            ? '<span class="ad-chip mute">none</span>'
            : e((string) $r['cat_name'])],
    ['k' => 'status', 't' => 'Status', 'fmt' => static fn(array $r): string =>
        ad_status_chip((string) $r['status'])],
    ['k' => 'img_n', 't' => 'Image', 'num' => true, 'fmt' => static function (array $r): string {
        $n = (int) $r['img_n'];
        if ($n === 0) {
            return '<span class="ad-chip bad">none</span>';
        }
        // A place with pictures but no cover still shows a blank card in the
        // guide, so the two states are worth telling apart.
        $out = $n . ' image' . ($n === 1 ? '' : 's');
        return (int) $r['img_covers'] > 0 ? $out : $out . ' <span class="ad-chip warn">no cover</span>';
    }],
    ['k' => 'verified_at', 't' => 'Checked', 'fmt' => static function (array $r): string {
        if ($r['verified_at'] === null) {
            return '<span class="ad-chip warn">never</span>';
        }
        $days = (int) floor((time() - strtotime((string) $r['verified_at'])) / 86400);
        return $days > 90
            ? ad_chip((int) floor($days / 30) . ' months ago', 'warn')
            : e(ad_when((string) $r['verified_at']));
    }],
    ['k' => 'visit_marks', 't' => 'Marks', 'num' => true,
     'fmt' => static fn(array $r): string => (string) (int) $r['visit_marks']],
    ['k' => 'updated_at', 't' => 'Changed', 'fmt' =>
        static fn(array $r): string => e(ad_when((string) $r['updated_at']))],
], $rows, [
    'bulk'  => $useBulk,
    'empty' => $bin ? 'The bin is empty.' : 'No places match. Try clearing a filter.',
]);

if ($useBulk) {
    ad_bulk_bar($bulkActions, 'Bulk changes are applied one row at a time and every one is logged.');
    echo '</form>';
}

$qs = array_filter([
    'q' => $q, 'mun' => $mun ?: '', 'cat' => $cat ?: '', 'status' => $status,
    'flag' => $flag, 'sort' => $sort === 'updated' ? '' : $sort, 'bin' => $bin ? '1' : '',
]);
ad_pager($total, $perPage, $page, $qs);

ad_panel_close();

admin_foot();
