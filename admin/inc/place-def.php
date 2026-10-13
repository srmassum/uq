<?php
/**
 * The places write spec, shared by the list screen and the editor.
 *
 * One declaration rather than two: a column added here shows up in both, and
 * there is no way for the editor to accept a field the list screen's bulk
 * actions would then quietly drop.
 */

declare(strict_types=1);

function place_def(): array
{
    return [
        'page'      => 'places.php',
        'nav'       => 'places',
        'table'     => 'places',
        'module'    => 'place',
        'entity'    => 'place',
        'singular'  => 'Place',
        'title'     => 'Places',
        'sub'       => 'Everything in the guide. A place stays a draft until you publish it.',
        'touch'     => true,
        'soft'      => true,
        'deletable' => true,
        'has_status' => true,

        'fields' => [
            // ---- names and slug
            ['k' => 'name_en', 't' => 'Name (English)', 'required' => true, 'maxlen' => 128],
            ['k' => 'slug', 't' => 'Slug', 'type' => 'slug', 'from' => 'name_en', 'maxlen' => 96,
             'help' => 'The public URL: place.php?p=… and the sitemap both use it. Leave blank to build one from the name.'],
            ['k' => 'name_ar', 't' => 'Name (Arabic)', 'required' => true, 'maxlen' => 128],

            // ---- where it is
            ['k' => 'municipality_id', 't' => 'Municipality', 'type' => 'int', 'required' => true, 'min' => 1],
            ['k' => 'locality_id', 't' => 'Locality', 'type' => 'int', 'min' => 0,
             'help' => 'Which dot on the map this belongs to. This is what makes the place appear when someone ticks that area.'],
            ['k' => 'category_id', 't' => 'Category', 'type' => 'int', 'min' => 0],

            // ---- coordinates
            ['k' => 'lat', 't' => 'Latitude', 'type' => 'decimal', 'required' => true,
             'scale' => 6, 'min' => 24.4, 'max' => 26.3,
             'help' => 'Filled in automatically when you click the map below.'],
            ['k' => 'lng', 't' => 'Longitude', 'type' => 'decimal', 'required' => true,
             'scale' => 6, 'min' => 50.5, 'max' => 51.9],

            // ---- copy
            ['k' => 'summary_en', 't' => 'Summary (English)', 'type' => 'textarea', 'rows' => 2, 'maxlen' => 500,
             'help' => 'One or two lines. This is what shows on the guide card, so it has to work on its own.'],
            ['k' => 'summary_ar', 't' => 'Summary (Arabic)', 'type' => 'textarea', 'rows' => 2, 'maxlen' => 500],
            ['k' => 'description_en', 't' => 'Description (English)', 'type' => 'textarea', 'rows' => 7],
            ['k' => 'description_ar', 't' => 'Description (Arabic)', 'type' => 'textarea', 'rows' => 7],
            ['k' => 'tips_en', 't' => 'Things people get wrong (English)', 'type' => 'textarea', 'rows' => 4,
             'help' => 'Written straight to the visitor. The practical bit nobody else tells them.'],
            ['k' => 'tips_ar', 't' => 'Things people get wrong (Arabic)', 'type' => 'textarea', 'rows' => 4],

            // ---- practical details
            ['k' => 'fee_level', 't' => 'Entry cost', 'type' => 'enum',
             'values' => ['free', 'low', 'medium', 'high'], 'default' => 'free',
             'help' => 'A band, not a number. Prices change and a stale exact figure is worse than an honest range.'],
            ['k' => 'fee_note_en', 't' => 'Cost note (English)', 'maxlen' => 160,
             'ph' => 'e.g. Free for residents, QAR 50 for visitors'],
            ['k' => 'fee_note_ar', 't' => 'Cost note (Arabic)', 'maxlen' => 160],
            ['k' => 'duration_mins', 't' => 'Typical visit (minutes)', 'type' => 'int', 'min' => 5, 'max' => 1440,
             'help' => 'Used to add up the length of a day trip.'],
            ['k' => 'best_time_en', 't' => 'Best time to go (English)', 'maxlen' => 160,
             'ph' => 'e.g. October to March, early morning'],
            ['k' => 'best_time_ar', 't' => 'Best time to go (Arabic)', 'maxlen' => 160],

            // ---- facets
            ['k' => 'family_friendly', 't' => 'Family friendly', 'type' => 'bool', 'on' => 'Good with children'],
            ['k' => 'metro_access', 't' => 'Metro access', 'type' => 'bool', 'on' => 'Walkable from a metro station'],
            ['k' => 'indoor', 't' => 'Indoor', 'type' => 'bool', 'on' => 'Mostly indoors (matters in summer)'],
            ['k' => 'is_hidden_gem', 't' => 'Hidden gem', 'type' => 'bool', 'on' => 'Show the hidden-gem badge'],
            ['k' => 'is_featured', 't' => 'Featured', 'type' => 'bool', 'on' => 'Show on the home page'],
            ['k' => 'season', 't' => 'Season', 'type' => 'enum',
             'values' => ['all', 'winter', 'summer'], 'default' => 'all'],

            // ---- links
            ['k' => 'map_url', 't' => 'Google Maps link', 'type' => 'url', 'maxlen' => 255],
            ['k' => 'official_url', 't' => 'Official website', 'type' => 'url', 'maxlen' => 255],

            // ---- workflow
            ['k' => 'status', 't' => 'Status', 'type' => 'enum',
             'values' => ['draft', 'published', 'archived'], 'default' => 'draft',
             'help' => 'Only published places appear on the public site.'],
            ['k' => 'source_note', 't' => 'Where the facts came from', 'maxlen' => 255,
             'help' => 'The page you checked the hours and prices against. Six months from now this is the only thing that will tell you whether the detail is still good.'],
            ['k' => 'verified_at', 't' => 'Details checked', 'type' => 'date',
             'help' => 'Set this the day you last compared the details against a real source. Leaving it blank keeps the place on the dashboard’s "needs a look" list.'],
            ['k' => 'sort_order', 't' => 'Sort order', 'type' => 'number', 'min' => -999, 'max' => 999],
        ],
    ];
}

/** Municipality and locality options for the editor's selects. */
function place_location_options(): array
{
    $muns = [];
    $locs = [];
    try {
        foreach (qAll('SELECT id, name_en FROM municipalities ORDER BY sort_order, name_en') as $r) {
            $muns[(int) $r['id']] = (string) $r['name_en'];
        }
        foreach (qAll(
            'SELECT l.id, l.name_en, m.name_en AS mun
               FROM localities l JOIN municipalities m ON m.id = l.municipality_id
              ORDER BY m.sort_order, l.sort_order, l.name_en'
        ) as $r) {
            $locs[(int) $r['id']] = $r['name_en'] . ' — ' . $r['mun'];
        }
    } catch (Throwable $e) {
        error_log('place_location_options: ' . $e->getMessage());
    }
    return [$muns, $locs];
}

/** Category options. */
function place_category_options(): array
{
    $cats = [0 => '— none —'];
    try {
        foreach (qAll('SELECT id, name_en FROM categories ORDER BY sort_order, name_en') as $r) {
            $cats[(int) $r['id']] = (string) $r['name_en'];
        }
    } catch (Throwable $e) {
        error_log('place_category_options: ' . $e->getMessage());
    }
    return $cats;
}
