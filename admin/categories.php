<?php
/**
 * Place categories.
 *
 * Deleting one is safe: places.category_id is ON DELETE SET NULL, so the place
 * survives and simply becomes uncategorised — which the dashboard's "no
 * category" state and the place list's own filter will both show you.
 */

declare(strict_types=1);

require_once __DIR__ . '/inc/panel.php';

require_admin();

simple_crud([
    'page'      => 'categories.php',
    'nav'       => 'cats',
    'table'     => 'categories',
    'module'    => 'category',
    'entity'    => 'category',
    'singular'  => 'Category',
    'title'     => 'Categories',
    'sub'       => 'What a place is — heritage, beach, mall. Each one gets a colour and an icon on the guide.',
    'order'     => 'sort_order, name_en',
    'search'    => ['name_en', 'name_ar', 'slug'],
    'deletable' => true,

    'columns' => [
        ['k' => 'name_en', 't' => 'Name'],
        ['k' => 'name_ar', 't' => 'Arabic'],
        ['k' => 'color', 't' => 'Colour',
         'fmt' => static fn(array $r): string =>
             '<span class="ad-chip" style="background:' . e((string) $r['color']) . ';color:#fff">'
             . e((string) $r['color']) . '</span>'],
        ['k' => 'icon', 't' => 'Icon'],
        ['k' => 'sort_order', 't' => 'Order', 'num' => true,
         'fmt' => static fn(array $r): string => (string) (int) $r['sort_order']],
    ],

    'fields' => [
        ['k' => 'name_en', 't' => 'Name (English)', 'required' => true, 'maxlen' => 64],
        ['k' => 'slug', 't' => 'Slug', 'type' => 'slug', 'from' => 'name_en', 'maxlen' => 32,
         'help' => 'The public filter uses this: places.php?cat=beach'],
        ['k' => 'name_ar', 't' => 'Name (Arabic)', 'required' => true, 'maxlen' => 64],
        ['k' => 'color', 't' => 'Colour', 'type' => 'color', 'default' => '#6f9c76'],
        ['k' => 'icon', 't' => 'Icon', 'maxlen' => 32,
         'help' => 'A short name the front end understands, e.g. museum, beach, mall.'],
        ['k' => 'sort_order', 't' => 'Sort order', 'type' => 'number', 'min' => 0, 'max' => 999],
    ],
]);
