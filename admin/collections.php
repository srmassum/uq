<?php
/**
 * Collections — the home page's featured row, seasonal sets, hidden-gem lists.
 *
 * Which places are in a collection is edited on the collection's own page, since
 * that is a list of picks rather than a field.
 */

declare(strict_types=1);

require_once __DIR__ . '/inc/panel.php';

require_admin();

simple_crud([
    'page'      => 'collections.php',
    'nav'       => 'colls',
    'table'     => 'collections',
    'module'    => 'collection',
    'entity'    => 'collection',
    'singular'  => 'Collection',
    'title'     => 'Collections',
    'sub'       => 'Grouped sets of places: what is featured on the home page, what to see in winter, the hidden gems.',
    'order'     => 'sort_order, title_en',
    'search'    => ['title_en', 'title_ar', 'slug'],
    'soft'      => true,
    'deletable' => true,
    'has_status' => true,

    'columns' => [
        ['k' => 'title_en', 't' => 'Collection', 'fmt' => static function (array $r): string {
            static $counts = null;
            if ($counts === null) {
                $counts = [];
                try {
                    foreach (qAll('SELECT collection_id, COUNT(*) n FROM collection_places GROUP BY collection_id') as $row) {
                        $counts[(int) $row['collection_id']] = (int) $row['n'];
                    }
                } catch (Throwable $e) {
                    error_log('collections.php: ' . $e->getMessage());
                }
            }
            $n = $counts[(int) $r['id']] ?? 0;

            return '<a class="ad-rowlink" href="' . e(admin_url('collection-edit.php', ['id' => (int) $r['id']])) . '">'
                 . e((string) $r['title_en']) . '</a>'
                 . '<span class="ad-sub">' . $n . ' place' . ($n === 1 ? '' : 's') . '</span>';
        }],
        ['k' => 'kind', 't' => 'Kind', 'fmt' => static fn(array $r): string => ad_chip((string) $r['kind'])],
        ['k' => 'blurb_en', 't' => 'Blurb'],
        ['k' => 'status', 't' => 'Status', 'fmt' => static fn(array $r): string =>
            ad_status_chip((string) $r['status'])],
        ['k' => 'sort_order', 't' => 'Order', 'num' => true,
         'fmt' => static fn(array $r): string => (string) (int) $r['sort_order']],
    ],

    'fields' => [
        ['k' => 'title_en', 't' => 'Title (English)', 'required' => true, 'maxlen' => 160],
        ['k' => 'slug', 't' => 'Slug', 'type' => 'slug', 'from' => 'title_en', 'maxlen' => 64],
        ['k' => 'title_ar', 't' => 'Title (Arabic)', 'required' => true, 'maxlen' => 160],
        ['k' => 'blurb_en', 't' => 'Blurb (English)', 'type' => 'textarea', 'rows' => 2, 'maxlen' => 255],
        ['k' => 'blurb_ar', 't' => 'Blurb (Arabic)', 'type' => 'textarea', 'rows' => 2, 'maxlen' => 255],
        ['k' => 'kind', 't' => 'Kind', 'type' => 'enum',
         'values' => ['editorial', 'featured', 'hidden_gems', 'seasonal'], 'default' => 'editorial',
         'help' => 'How the home page decides to use it. "featured" is the row at the top.'],
        ['k' => 'status', 't' => 'Status', 'type' => 'enum',
         'values' => ['draft', 'published', 'archived'], 'default' => 'draft'],
        ['k' => 'sort_order', 't' => 'Sort order', 'type' => 'number', 'min' => -999, 'max' => 999],
    ],
]);
