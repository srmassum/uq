<?php
/**
 * Share-card themes.
 *
 * These colours drive two renderers at once — the live map on the front page and
 * the canvas code that draws the downloadable PNG. They read the same values, so
 * a card downloaded from a theme always matches what was on screen. That is why
 * every colour here is editable rather than compiled into the export script.
 */

declare(strict_types=1);

require_once __DIR__ . '/inc/panel.php';

require_admin();

$swatch = static fn(string $col): callable => static fn(array $r): string =>
    '<span class="ad-chip" style="background:' . e((string) $r[$col]) . ';color:#fff">'
    . e((string) $r[$col]) . '</span>';

simple_crud([
    'page'      => 'themes.php',
    'nav'       => 'themes',
    'table'     => 'share_themes',
    'module'    => 'theme',
    'entity'    => 'theme',
    'singular'  => 'Theme',
    'title'     => 'Card themes',
    'sub'       => 'The colour sets a visitor picks from before downloading their map.',
    'order'     => 'sort_order, name_en',
    'search'    => ['name_en', 'name_ar', 'slug'],
    'deletable' => true,

    'columns' => [
        ['k' => 'name_en', 't' => 'Theme'],
        ['k' => 'slug', 't' => 'Slug'],
        ['k' => 'bg', 't' => 'Background', 'fmt' => $swatch('bg')],
        ['k' => 'land', 't' => 'Unvisited', 'fmt' => $swatch('land')],
        ['k' => 'visited', 't' => 'Visited', 'fmt' => $swatch('visited')],
        ['k' => 'ink', 't' => 'Text', 'fmt' => $swatch('ink')],
        ['k' => 'is_dark', 't' => 'Dark', 'num' => true,
         'fmt' => static fn(array $r): string => (int) $r['is_dark'] ? 'yes' : '—'],
    ],

    'fields' => [
        ['k' => 'name_en', 't' => 'Name (English)', 'required' => true, 'maxlen' => 64],
        ['k' => 'slug', 't' => 'Slug', 'type' => 'slug', 'from' => 'name_en', 'maxlen' => 32,
         'help' => 'Saved maps store this slug, so renaming it orphans cards that already exist. Add a new theme instead.'],
        ['k' => 'name_ar', 't' => 'Name (Arabic)', 'required' => true, 'maxlen' => 64],

        ['k' => 'bg', 't' => 'Background', 'type' => 'color', 'default' => '#ffffff'],
        ['k' => 'land', 't' => 'Unvisited land', 'type' => 'color', 'default' => '#e2e8f0',
         'help' => 'The municipality shapes nobody has ticked.'],
        ['k' => 'visited', 't' => 'Visited land', 'type' => 'color', 'default' => '#6f9c76'],
        ['k' => 'accent', 't' => 'Accent', 'type' => 'color', 'default' => '#0f1720',
         'help' => 'Progress bar and the ring around the visitor’s photo.'],
        ['k' => 'ink', 't' => 'Text', 'type' => 'color', 'default' => '#0f1720'],
        ['k' => 'ink_soft', 't' => 'Soft text', 'type' => 'color', 'default' => '#64748b'],
        ['k' => 'is_dark', 't' => 'Dark theme', 'type' => 'checkbox', 'on' => 'Text on this theme is light'],
        ['k' => 'sort_order', 't' => 'Sort order', 'type' => 'number', 'min' => 0, 'max' => 999],
    ],
]);
