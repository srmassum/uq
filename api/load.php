<?php
/**
 * GET /api/load.php?c=CODE — read back a saved map.
 *
 * Used by the share page so a visitor can start from someone else's selection
 * instead of an empty map. Returns slugs and codes rather than database ids,
 * because those are what the client-side map speaks.
 */

declare(strict_types=1);

require dirname(__DIR__) . '/inc/bootstrap.php';
require dirname(__DIR__) . '/inc/mapdata.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    json_fail('GET required.', 405);
}

$code = strtoupper(trim((string) ($_GET['c'] ?? '')));

// Shape check before the query, so a malformed code never reaches the database.
if (!preg_match('/^[' . CODE_ALPHABET . ']{' . SHORT_CODE_LEN . '}$/', $code)) {
    json_fail('bad_code', 400);
}

$row = qOne(
    'SELECT id, code, display_name, theme, personality, level_slug, mun_count, loc_count, views
       FROM saved_maps
      WHERE code = ? AND status = \'active\'',
    [$code]
);

if ($row === null) {
    json_fail('not_found', 404);
}

$items = qAll(
    'SELECT i.item_type, l.slug AS locality_slug, m.code AS municipality_code
       FROM saved_map_items i
       LEFT JOIN localities     l ON i.item_type = \'locality\'     AND l.id = i.item_id
       LEFT JOIN municipalities m ON i.item_type = \'municipality\' AND m.id = i.item_id
      WHERE i.saved_map_id = ?',
    [(int) $row['id']]
);

$places = [];
$regions = [];
foreach ($items as $it) {
    if ($it['item_type'] === 'locality' && $it['locality_slug'] !== null) {
        $places[] = $it['locality_slug'];
    } elseif ($it['item_type'] === 'municipality' && $it['municipality_code'] !== null) {
        $regions[] = $it['municipality_code'];
    }
}

json_out([
    'ok'          => true,
    'code'        => $row['code'],
    'name'        => $row['display_name'],
    'theme'       => $row['theme'],
    'personality' => $row['personality'],
    'level'       => $row['level_slug'],
    'places'      => $places,
    'regions'     => $regions,
    'total'       => total_markable(),
]);
