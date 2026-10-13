<?php
/**
 * The places guide's data layer.
 *
 * Everything here reads from MySQL, because places are content: they get edited,
 * unpublished and re-verified, and the guide has to reflect that on the next
 * request without a rebuild. (The map's geometry is the opposite case and lives
 * in a JSON file — see inc/mapdata.php.)
 *
 * Every function degrades to an empty result rather than throwing, so the guide
 * page renders an honest "not set up yet" state if the tables are missing,
 * instead of a stack trace on a fresh checkout. Failures are logged.
 */

declare(strict_types=1);

/** True once the places table exists and has published rows in it. */
function places_ready(): bool
{
    try {
        return (int) qVal(
            "SELECT COUNT(*) FROM places WHERE status = 'published' AND deleted_at IS NULL"
        ) > 0;
    } catch (Throwable $e) {
        error_log('places_ready: ' . $e->getMessage());
        return false;
    }
}

/**
 * Every column the guide renders, with the joined names flattened into
 * `mun`, `cat` and `loc` sub-arrays so pick() can be used on each of them.
 */
const PLACE_SELECT = "
    SELECT  p.*,
            m.code AS mun_code, m.slug AS mun_slug, m.name_en AS mun_en, m.name_ar AS mun_ar,
            c.slug AS cat_slug, c.name_en AS cat_en, c.name_ar AS cat_ar,
            c.icon AS cat_icon, c.color AS cat_color,
            l.slug AS loc_slug, l.name_en AS loc_en, l.name_ar AS loc_ar
    FROM places p
    JOIN municipalities m ON m.id = p.municipality_id
    LEFT JOIN categories  c ON c.id = p.category_id
    LEFT JOIN localities  l ON l.id = p.locality_id
";

/** Reshape a joined row so pick($p['mun'], 'name') works. */
function places_shape(array $r): array
{
    $r['mun'] = ['code' => $r['mun_code'], 'slug' => $r['mun_slug'],
                 'name_en' => $r['mun_en'], 'name_ar' => $r['mun_ar']];
    $r['cat'] = ['slug' => $r['cat_slug'], 'name_en' => $r['cat_en'], 'name_ar' => $r['cat_ar'],
                 'icon' => $r['cat_icon'], 'color' => $r['cat_color']];
    $r['loc'] = $r['loc_slug'] === null ? null
              : ['slug' => $r['loc_slug'], 'name_en' => $r['loc_en'], 'name_ar' => $r['loc_ar']];
    return $r;
}

/**
 * Build the WHERE clause and bindings for a filter set.
 *
 * $f keys, all optional strings: mun (municipality code), cat (category slug),
 * q (search text), free ('1'), hidden ('1'), family ('1'), metro ('1'),
 * indoor ('1'), season ('all'|'winter'|'summer').
 */
function places_where(array $f): array
{
    $w  = ["p.status = 'published'", 'p.deleted_at IS NULL'];
    $b  = [];

    if (!empty($f['mun']))  { $w[] = 'm.code = :mun';  $b[':mun'] = $f['mun']; }
    if (!empty($f['cat']))  { $w[] = 'c.slug = :cat';  $b[':cat'] = $f['cat']; }

    if (!empty($f['free'])) { $w[] = "p.fee_level = 'free'"; }
    if (!empty($f['hidden'])) { $w[] = 'p.is_hidden_gem = 1'; }
    if (!empty($f['family'])) { $w[] = 'p.family_friendly = 1'; }
    if (!empty($f['metro']))  { $w[] = 'p.metro_access = 1'; }
    if (!empty($f['indoor'])) { $w[] = 'p.indoor = 1'; }

    if (!empty($f['season']) && in_array($f['season'], ['all', 'winter', 'summer'], true)) {
        $w[] = 'p.season = :season';
        $b[':season'] = $f['season'];
    }

    if (!empty($f['q'])) {
        // LIKE rather than the FULLTEXT index: the table is small, and FULLTEXT
        // silently returns nothing for words under its minimum token length,
        // which is exactly the case for short place names people actually type.
        //
        // Six separate placeholders rather than one repeated :q — with
        // ATTR_EMULATE_PREPARES off, PDO sends a native prepared statement and
        // the same named marker may not appear twice in it. Repeating it throws
        // HY093, which would have made search silently return nothing.
        $w[] = '(p.name_en LIKE :q1 OR p.name_ar LIKE :q2 OR p.summary_en LIKE :q3
                 OR p.summary_ar LIKE :q4 OR l.name_en LIKE :q5 OR m.name_en LIKE :q6)';
        $needle = '%' . places_like_escape((string) $f['q']) . '%';
        for ($i = 1; $i <= 6; $i++) {
            $b[':q' . $i] = $needle;
        }
    }

    return ['WHERE ' . implode(' AND ', $w), $b];
}

/** Neutralise the LIKE wildcards so a search for "50%" means "50%". */
function places_like_escape(string $s): string
{
    return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $s);
}

/** ORDER BY for a sort key, restricted to a known set so it cannot be injected. */
function places_order(string $sort): string
{
    switch ($sort) {
        case 'mun':  return 'm.sort_order, l.sort_order, p.sort_order';
        case 'fee':  return "FIELD(p.fee_level,'free','low','medium','high'), p.sort_order";
        case 'name': return 'p.name_en';
        default:     return 'p.sort_order, p.name_en';
    }
}

/** The filtered guide listing. */
function places_list(array $f = [], string $sort = 'default', int $limit = 200, int $offset = 0): array
{
    [$where, $bind] = places_where($f);
    $sql = PLACE_SELECT . " $where ORDER BY " . places_order($sort) . ' LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset;

    try {
        return array_map('places_shape', qAll($sql, $bind));
    } catch (Throwable $e) {
        error_log('places_list: ' . $e->getMessage());
        return [];
    }
}

/** How many places the current filter matches — shown above the grid. */
function places_count(array $f = []): int
{
    [$where, $bind] = places_where($f);
    $sql = 'SELECT COUNT(*) FROM places p
            JOIN municipalities m ON m.id = p.municipality_id
            LEFT JOIN categories c ON c.id = p.category_id
            LEFT JOIN localities l ON l.id = p.locality_id ' . $where;

    try {
        return (int) qVal($sql, $bind);
    } catch (Throwable $e) {
        error_log('places_count: ' . $e->getMessage());
        return 0;
    }
}

/** One place by slug, for the detail page. Null means genuinely not there. */
function place_by_slug(string $slug): ?array
{
    if ($slug === '' || strlen($slug) > 96) {
        return null;
    }
    try {
        $row = qOne(PLACE_SELECT . " WHERE p.slug = :slug
                       AND p.status = 'published' AND p.deleted_at IS NULL
                     LIMIT 1", [':slug' => $slug]);
    } catch (Throwable $e) {
        error_log('place_by_slug: ' . $e->getMessage());
        return null;
    }
    return $row ? places_shape($row) : null;
}

/**
 * Nearby places for the detail page: same locality first, then the rest of the
 * municipality. Done as two queries rather than a distance sum, because "near"
 * on a guide page means "same area", and the coordinates of two places in the
 * same municipality can be 60km apart.
 */
function places_related(array $place, int $limit = 3): array
{
    $out  = [];
    $seen = [(int) $place['id']];

    try {
        if (!empty($place['locality_id'])) {
            $rows = qAll(PLACE_SELECT . " WHERE p.status = 'published' AND p.deleted_at IS NULL
                             AND p.locality_id = :lid AND p.id <> :id
                           ORDER BY p.sort_order LIMIT " . (int) $limit,
                [':lid' => (int) $place['locality_id'], ':id' => (int) $place['id']]);
            foreach ($rows as $r) { $out[] = places_shape($r); $seen[] = (int) $r['id']; }
        }

        if (count($out) < $limit) {
            $need = $limit - count($out);
            $in   = implode(',', array_map('intval', $seen));
            $rows = qAll(PLACE_SELECT . " WHERE p.status = 'published' AND p.deleted_at IS NULL
                             AND p.municipality_id = :mid AND p.id NOT IN ($in)
                           ORDER BY p.sort_order LIMIT " . (int) $need,
                [':mid' => (int) $place['municipality_id']]);
            foreach ($rows as $r) { $out[] = places_shape($r); }
        }
    } catch (Throwable $e) {
        error_log('places_related: ' . $e->getMessage());
    }

    return $out;
}

/** Categories that actually have published places, with counts, in display order. */
function places_categories(): array
{
    try {
        return qAll("SELECT c.slug, c.name_en, c.name_ar, c.icon, c.color,
                            COUNT(p.id) AS n
                     FROM categories c
                     JOIN places p ON p.category_id = c.id
                                  AND p.status = 'published' AND p.deleted_at IS NULL
                     GROUP BY c.id
                     ORDER BY c.sort_order");
    } catch (Throwable $e) {
        error_log('places_categories: ' . $e->getMessage());
        return [];
    }
}

/** Municipalities that actually have published places, with counts. */
function places_municipalities(): array
{
    try {
        return qAll("SELECT m.code, m.slug, m.name_en, m.name_ar, COUNT(p.id) AS n
                     FROM municipalities m
                     JOIN places p ON p.municipality_id = m.id
                                  AND p.status = 'published' AND p.deleted_at IS NULL
                     GROUP BY m.id
                     ORDER BY m.sort_order");
    } catch (Throwable $e) {
        error_log('places_municipalities: ' . $e->getMessage());
        return [];
    }
}

/** 40 → "40 min", 150 → "2 h 30 min". Zero or null means we do not know. */
function places_duration(?int $mins): string
{
    if ($mins === null || $mins <= 0) {
        return '';
    }
    if ($mins < 60) {
        return num((string) $mins) . ' ' . t('unit.min');
    }
    $h = intdiv($mins, 60);
    $m = $mins % 60;
    $s = num((string) $h) . ' ' . t('unit.hr');
    return $m > 0 ? $s . ' ' . num((string) $m) . ' ' . t('unit.min') : $s;
}

/**
 * Every published place, in the smallest shape a page needs to draw a row.
 *
 * The trip page holds its list in the browser, so the server cannot know which
 * places to render — it has to hand over the whole catalogue and let the client
 * pick. That makes payload size a real concern: PLACE_SELECT would drag the
 * description and tips TEXT columns along for all sixty rows, which is most of
 * the weight and none of the use. This selects only what a row shows.
 *
 * Keys are shortened for the same reason: a 60-row object repeats every key 60
 * times, so `{n:…,m:…,d:…}` is meaningfully smaller on the wire than
 * `{name:…,municipality:…,duration_mins:…}`. The names are given here once.
 *
 * The array is keyed by slug, so each entry holds only what is not already in
 * the key:
 *
 *   n  name (both languages)   m  municipality (both)
 *   i  category icon           c  category colour
 *   d  duration in minutes (int|null)   f  fee level   l  locality slug (or '')
 *
 * No coordinates: the trip page keeps the order the visitor added places in,
 * which is the order they intend to travel them. Sorting by distance would
 * quietly rearrange a plan somebody had already made.
 */
function places_index(): array
{
    try {
        $rows = qAll("
            SELECT  p.slug,
                    p.name_en, p.name_ar,
                    m.name_en AS mun_en, m.name_ar AS mun_ar,
                    c.icon AS cat_icon, c.color AS cat_color,
                    p.duration_mins, p.fee_level,
                    l.slug AS loc_slug,
                    p.lat, p.lng
              FROM places p
              JOIN municipalities m ON m.id = p.municipality_id
              LEFT JOIN categories  c ON c.id = p.category_id
              LEFT JOIN localities  l ON l.id = p.locality_id
             WHERE p.status = 'published' AND p.deleted_at IS NULL
             ORDER BY m.sort_order, p.sort_order, p.name_en");
    } catch (Throwable $e) {
        error_log('places_index: ' . $e->getMessage());
        return [];
    }

    $out = [];
    foreach ($rows as $r) {
        $dur = $r['duration_mins'] === null ? null : (int) $r['duration_mins'];
        $out[(string) $r['slug']] = [
            'n' => ['en' => (string) $r['name_en'], 'ar' => (string) $r['name_ar']],
            'm' => ['en' => (string) $r['mun_en'],  'ar' => (string) $r['mun_ar']],
            'i' => (string) ($r['cat_icon'] ?? ''),
            'c' => (string) ($r['cat_color'] ?? ''),
            // Null rather than 0: the trip total has to be able to say "three of
            // these have no time recorded" instead of quietly counting them as
            // no time at all.
            'd' => ($dur !== null && $dur > 0) ? $dur : null,
            'f' => (string) $r['fee_level'],
            'l' => (string) ($r['loc_slug'] ?? ''),
        ];
    }
    return $out;
}

/**
 * Read and validate the guide's filter inputs from a request array.
 *
 * Shared by the listing, the detail page (which carries filters back) and any
 * future feed, so a value that one page accepts cannot be one another rejects.
 * Everything is whitelisted by shape rather than escaped after the fact —
 * an unknown value is dropped, never forwarded into SQL.
 */
function places_read_filters(array $src): array
{
    // Values arrive from $_GET, so any of them can be an array (?free[]=1).
    // Only strings are considered; everything else is simply not a filter.
    $str = static fn(array $a, string $k): string => isset($a[$k]) && is_string($a[$k]) ? $a[$k] : '';

    $f = [];

    $mun = strtoupper(trim($str($src, 'mun')));
    if (preg_match('/^QA[A-Z]{2}$/', $mun) === 1) {
        $f['mun'] = $mun;
    }

    $cat = strtolower(trim($str($src, 'cat')));
    if (preg_match('/^[a-z0-9-]{1,32}$/', $cat) === 1) {
        $f['cat'] = $cat;
    }

    $q = trim($str($src, 'q'));
    if ($q !== '') {
        $f['q'] = mb_substr($q, 0, 60);
    }

    foreach (['free', 'hidden', 'family', 'metro', 'indoor'] as $k) {
        if ($str($src, $k) === '1') {
            $f[$k] = '1';
        }
    }

    $season = strtolower(trim($str($src, 'season')));
    if (in_array($season, ['all', 'winter', 'summer'], true)) {
        $f['season'] = $season;
    }

    return $f;
}

/** The sort key, restricted to the ones places_order() understands. */
function places_sort_key(array $src): string
{
    $s = isset($src['sort']) && is_string($src['sort']) ? strtolower(trim($src['sort'])) : '';
    return in_array($s, ['name', 'mun', 'fee'], true) ? $s : 'default';
}

/** Rebuild a filter set as a query string, for links that keep the context. */
function places_query(array $f, string $sort = 'default'): string
{
    $q = $f;
    if ($sort !== 'default') {
        $q['sort'] = $sort;
    }
    return $q === [] ? '' : http_build_query($q);
}

/**
 * The small tags shown on a card and in the facts list.
 * Only facts we actually hold are shown — no "family friendly" badge on a place
 * where nobody has decided, because a default of 1 is not a recommendation.
 */
function places_tags(array $p): array
{
    $tags = [];

    if (!empty($p['cat']['slug'])) {
        $tags[] = ['kind' => 'cat', 'text' => pick($p['cat'], 'name'),
                   'icon' => $p['cat']['icon'], 'color' => $p['cat']['color']];
    }

    $fee = (string) $p['fee_level'];
    $tags[] = [
        'kind'  => 'fee',
        'text'  => t('fee.' . $fee),
        'free'  => $fee === 'free',
    ];

    if (!empty($p['is_hidden_gem'])) {
        $tags[] = ['kind' => 'hidden', 'text' => t('places.hidden_tag')];
    }
    if (!empty($p['metro_access'])) {
        $tags[] = ['kind' => 'metro', 'text' => t('places.metro_tag')];
    }

    return $tags;
}
