<?php
/**
 * Itineraries — 1/2/3-day trips and 8/12/24-hour stopovers.
 *
 * The list and the shape of the trip live here; the stops themselves are on
 * itinerary-edit.php, because a stop list is a different kind of editing from a
 * row of fields.
 */

declare(strict_types=1);

require_once __DIR__ . '/inc/panel.php';

require_admin();

simple_crud([
    'page'      => 'itineraries.php',
    'nav'       => 'itin',
    'table'     => 'itineraries',
    'module'    => 'itinerary',
    'entity'    => 'itinerary',
    'singular'  => 'Itinerary',
    'title'     => 'Itineraries',
    'sub'       => 'Ready-made routes. The stops are edited on the itinerary’s own page.',
    'order'     => 'sort_order, title_en',
    'search'    => ['title_en', 'title_ar', 'slug'],
    'soft'      => true,
    'deletable' => true,
    'has_status' => true,
    'touch'     => true,

    'columns' => [
        ['k' => 'title_en', 't' => 'Itinerary', 'fmt' => static function (array $r): string {
            // One grouped count rather than a query per row. Inside a formatter
            // a thrown exception would abort the page mid-table, so this fails
            // to a blank rather than to a stack trace.
            static $counts = null;
            if ($counts === null) {
                $counts = [];
                try {
                    foreach (qAll('SELECT itinerary_id, COUNT(*) n FROM itinerary_stops GROUP BY itinerary_id') as $row) {
                        $counts[(int) $row['itinerary_id']] = (int) $row['n'];
                    }
                } catch (Throwable $e) {
                    error_log('itineraries.php: ' . $e->getMessage());
                }
            }
            $n = $counts[(int) $r['id']] ?? 0;

            return '<a class="ad-rowlink" href="'
                 . e(admin_url('itinerary-edit.php', ['id' => (int) $r['id']])) . '">'
                 . e((string) $r['title_en']) . '</a>'
                 . '<span class="ad-sub">' . $n . ' stop' . ($n === 1 ? '' : 's')
                 . ' · changed ' . e(ad_when((string) $r['updated_at'])) . '</span>';
        }],
        ['k' => 'duration_value', 't' => 'Length', 'num' => true,
         'fmt' => static fn(array $r): string =>
             (int) $r['duration_value'] . ' ' . e((string) $r['duration_type'])],
        ['k' => 'audience', 't' => 'For', 'fmt' => static fn(array $r): string =>
            ad_chip((string) $r['audience'])],
        ['k' => 'budget_level', 't' => 'Budget', 'fmt' => static fn(array $r): string =>
            ad_chip((string) $r['budget_level'])],
        ['k' => 'status', 't' => 'Status', 'fmt' => static fn(array $r): string =>
            ad_status_chip((string) $r['status'])],
    ],

    'fields' => [
        ['k' => 'title_en', 't' => 'Title (English)', 'required' => true, 'maxlen' => 160],
        ['k' => 'slug', 't' => 'Slug', 'type' => 'slug', 'from' => 'title_en', 'maxlen' => 96],
        ['k' => 'title_ar', 't' => 'Title (Arabic)', 'required' => true, 'maxlen' => 160],
        ['k' => 'intro_en', 't' => 'Intro (English)', 'type' => 'textarea', 'rows' => 3],
        ['k' => 'intro_ar', 't' => 'Intro (Arabic)', 'type' => 'textarea', 'rows' => 3],
        ['k' => 'duration_type', 't' => 'Measured in', 'type' => 'enum',
         'values' => ['hours', 'days'], 'default' => 'days',
         'help' => 'A layover is hours; a trip is days. This changes how the stops are grouped.'],
        ['k' => 'duration_value', 't' => 'How many', 'type' => 'int', 'required' => true, 'min' => 1, 'max' => 30],
        ['k' => 'audience', 't' => 'Who it is for', 'type' => 'enum',
         'values' => ['any', 'family', 'couple', 'budget', 'adventure', 'solo'], 'default' => 'any'],
        ['k' => 'budget_level', 't' => 'Budget', 'type' => 'enum',
         'values' => ['free', 'low', 'medium', 'high'], 'default' => 'medium'],
        ['k' => 'cover_image', 't' => 'Cover image', 'type' => 'image'],
        ['k' => 'status', 't' => 'Status', 'type' => 'enum',
         'values' => ['draft', 'published', 'archived'], 'default' => 'draft'],
        ['k' => 'verified_at', 't' => 'Details checked', 'type' => 'date',
         'help' => 'Opening hours move. This is what puts the route back on the "needs a look" list a season later.'],
        ['k' => 'sort_order', 't' => 'Sort order', 'type' => 'number', 'min' => -999, 'max' => 999],
    ],
]);
