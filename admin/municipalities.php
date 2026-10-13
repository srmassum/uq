<?php
/**
 * The eight municipalities.
 *
 * No delete, on purpose. municipality_id is a foreign key on localities and
 * places with ON DELETE CASCADE, so removing a municipality would silently take
 * every locality and place inside it — and the map would lose a shape with no
 * way back. Renaming is fine; removing is not a thing this screen offers.
 */

declare(strict_types=1);

require_once __DIR__ . '/inc/panel.php';

require_admin();

simple_crud([
    'page'      => 'municipalities.php',
    'nav'       => 'muns',
    'table'     => 'municipalities',
    'module'    => 'municipality',
    'entity'    => 'municipality',
    'singular'  => 'Municipality',
    'title'     => 'Municipalities',
    'sub'       => 'The eight shapes on the map. The code is the SVG path id and must not change — the map file keys off it.',
    'order'     => 'sort_order, name_en',
    'search'    => ['name_en', 'name_ar', 'alt_en', 'code', 'slug'],
    'deletable' => false,
    'has_status' => false,

    'columns' => [
        ['k' => 'name_en', 't' => 'Name'],
        ['k' => 'name_ar', 't' => 'Arabic'],
        ['k' => 'code',    't' => 'Map code'],
        ['k' => 'alt_en',  't' => 'Also spelled'],
        ['k' => 'sort_order', 't' => 'Order', 'num' => true,
         'fmt' => static fn(array $r): string => (string) (int) $r['sort_order']],
    ],

    'fields' => [
        ['k' => 'name_en', 't' => 'Name (English)', 'required' => true, 'maxlen' => 64],
        ['k' => 'slug', 't' => 'Slug', 'type' => 'slug', 'from' => 'name_en', 'maxlen' => 32,
         'help' => 'Used in URLs. Leave blank to build one from the name.'],
        ['k' => 'name_ar', 't' => 'Name (Arabic)', 'required' => true, 'maxlen' => 64],
        ['k' => 'alt_en', 't' => 'Alternate English spelling', 'maxlen' => 64,
         'help' => 'Qatar spells several of these two ways (Al Rayyan / Ar Rayyan). List the other one here.'],
        ['k' => 'code', 't' => 'Map code', 'required' => true, 'maxlen' => 4, 'mono' => true,
         'help' => 'The path id in assets/data/qatar.svg — QADA, QAKH, QAMS, QARA, QASH, QAUS, QAWA, QAZA.'],
        ['k' => 'blurb_en', 't' => 'Blurb (English)', 'type' => 'textarea', 'rows' => 2, 'maxlen' => 255],
        ['k' => 'blurb_ar', 't' => 'Blurb (Arabic)', 'type' => 'textarea', 'rows' => 2, 'maxlen' => 255],
        ['k' => 'sort_order', 't' => 'Sort order', 'type' => 'number', 'min' => 0, 'max' => 999,
         'help' => 'Lower numbers come first in lists and checklists.'],
    ],
]);
