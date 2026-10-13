<?php
/**
 * Levels — Newcomer, Explorer, Qatar Master.
 *
 * The threshold is the only number that matters: whoever holds the most places
 * among the levels they have passed is the level shown. Ties go to the higher
 * sort_order, so the ladder never has a gap in it.
 */

declare(strict_types=1);

require_once __DIR__ . '/inc/panel.php';

require_admin();

simple_crud([
    'page'      => 'levels.php',
    'nav'       => 'levels',
    'table'     => 'levels',
    'module'    => 'level',
    'entity'    => 'level',
    'singular'  => 'Level',
    'title'     => 'Levels',
    'sub'       => 'The ladder a visitor climbs as they tick places off.',
    'order'     => 'min_places, sort_order',
    'search'    => ['name_en', 'name_ar', 'slug'],
    'deletable' => true,

    'columns' => [
        ['k' => 'name_en', 't' => 'Level'],
        ['k' => 'name_ar', 't' => 'Arabic'],
        ['k' => 'min_places', 't' => 'Needs', 'num' => true,
         'fmt' => static fn(array $r): string => (int) $r['min_places'] . ' places'],
        ['k' => 'color', 't' => 'Colour',
         'fmt' => static fn(array $r): string =>
             '<span class="ad-chip" style="background:' . e((string) $r['color']) . ';color:#fff">'
             . e((string) $r['color']) . '</span>'],
        ['k' => 'sort_order', 't' => 'Order', 'num' => true,
         'fmt' => static fn(array $r): string => (string) (int) $r['sort_order']],
    ],

    'fields' => [
        ['k' => 'name_en', 't' => 'Name (English)', 'required' => true, 'maxlen' => 64],
        ['k' => 'slug', 't' => 'Slug', 'type' => 'slug', 'from' => 'name_en', 'maxlen' => 32],
        ['k' => 'name_ar', 't' => 'Name (Arabic)', 'required' => true, 'maxlen' => 64],
        ['k' => 'min_places', 't' => 'Places needed', 'type' => 'number', 'min' => 0, 'max' => 65535,
         'required' => true, 'help' => 'The lowest level should be 0, so everybody has one from the start.'],
        ['k' => 'color', 't' => 'Colour', 'type' => 'color', 'default' => '#6f9c76'],
        ['k' => 'sort_order', 't' => 'Sort order', 'type' => 'number', 'min' => -999, 'max' => 999],
    ],
]);
