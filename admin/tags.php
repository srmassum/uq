<?php
/**
 * Tags — the free-form labels under a place (family-friendly, free entry,
 * photogenic). Unlike categories a place can carry any number of them, and the
 * link row is removed automatically when a tag goes.
 */

declare(strict_types=1);

require_once __DIR__ . '/inc/panel.php';

require_admin();

simple_crud([
    'page'      => 'tags.php',
    'nav'       => 'tags',
    'table'     => 'tags',
    'module'    => 'tag',
    'entity'    => 'tag',
    'singular'  => 'Tag',
    'title'     => 'Tags',
    'sub'       => 'Extra labels a place can carry. Used by the guide filters and the itinerary builder.',
    'order'     => 'name_en',
    'search'    => ['name_en', 'name_ar', 'slug'],
    'deletable' => true,

    'columns' => [
        ['k' => 'name_en', 't' => 'Name'],
        ['k' => 'name_ar', 't' => 'Arabic'],
        ['k' => 'slug', 't' => 'Slug'],
        ['k' => 'id', 't' => 'Used by', 'num' => true,
         'fmt' => static function (array $r): string {
             // A count per row would be a query per row; one grouped query up
             // front is cheaper and the number is worth having, because it is
             // what tells you whether a tag is safe to remove.
             static $counts = null;
             if ($counts === null) {
                 $counts = [];
                 try {
                     foreach (qAll('SELECT tag_id, COUNT(*) n FROM place_tag GROUP BY tag_id') as $row) {
                         $counts[(int) $row['tag_id']] = (int) $row['n'];
                     }
                 } catch (Throwable $e) {
                     error_log('tags.php: ' . $e->getMessage());
                 }
             }
             $n = $counts[(int) $r['id']] ?? 0;
             return $n === 0
                 ? '<span class="ad-muted">unused</span>'
                 : $n . ' place' . ($n === 1 ? '' : 's');
         }],
    ],

    'fields' => [
        ['k' => 'name_en', 't' => 'Name (English)', 'required' => true, 'maxlen' => 64],
        ['k' => 'slug', 't' => 'Slug', 'type' => 'slug', 'from' => 'name_en', 'maxlen' => 48],
        ['k' => 'name_ar', 't' => 'Name (Arabic)', 'required' => true, 'maxlen' => 64],
    ],
]);
