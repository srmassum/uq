<?php
/**
 * Ready-made plans — the data layer behind itineraries.php and itinerary.php.
 *
 * This is স্তর ৩ item ৮ of features.md: the 1/2/3-day trips and the 8/12/24-hour
 * stopovers, for families, couples, budget travellers and people who want the
 * desert. The panel has been able to write these rows since the admin was built;
 * nothing outside the panel has ever read them, which is what this file exists
 * to change.
 *
 * Same shape and the same rules as inc/places.php: everything degrades to an
 * empty result rather than throwing, so a database without these tables renders
 * an honest "nothing here yet" page instead of a stack trace.
 *
 * One thing is deliberately NOT joined: a stop may point at a place that has
 * since been unpublished or binned. The stop is still part of the plan — its
 * time note and its own text are content in their own right — so the query keeps
 * the row and reports the place's state separately, and the page decides whether
 * the stop is allowed to be a link. Hiding the stop instead would silently
 * shorten somebody's day.
 */

declare(strict_types=1);

/**
 * The single definition of "this stop's place is still worth linking to".
 *
 * Used by itinerary_stop_stats() below. itinerary_stops() asks the same question
 * in PHP, because it has a whole row to hand and a closure reads better there —
 * the two must agree, and they are kept next to each other so they can be
 * changed together.
 */
const ITIN_LIVE_SQL = "p.id IS NOT NULL AND p.status = 'published' AND p.deleted_at IS NULL";

/** True once at least one published plan exists. */
function itineraries_ready(): bool
{
    try {
        return (int) qVal(
            "SELECT COUNT(*) FROM itineraries WHERE status = 'published' AND deleted_at IS NULL"
        ) > 0;
    } catch (Throwable $e) {
        error_log('itineraries_ready: ' . $e->getMessage());
        return false;
    }
}

/**
 * The plans, in the order the editor put them.
 *
 * No stop counts here: the same numbers are needed for every card on the list,
 * so itinerary_stop_stats() fetches them for all the plans in one query rather
 * than one subquery per row repeating what counts as a live stop.
 *
 * @param array{len?:string, who?:string, budget?:string, limit?:int} $f
 */
function itineraries_list(array $f = []): array
{
    $w = ["i.status = 'published'", 'i.deleted_at IS NULL'];
    $b = [];

    if (!empty($f['who'])) {
        // 'any' is a plan that suits everybody, so asking for families should
        // find the family plans and the general ones — but not the adventure
        // ones, which is the whole point of filtering.
        $w[] = "(i.audience = :who OR i.audience = 'any')";
        $b[':who'] = $f['who'];
    }

    if (!empty($f['budget'])) {
        $w[] = 'i.budget_level = :budget';
        $b[':budget'] = $f['budget'];
    }

    if (!empty($f['len'])) {
        [$type, $value] = itin_split_len((string) $f['len']);
        if ($type !== '') {
            $w[] = 'i.duration_type = :dtype AND i.duration_value = :dvalue';
            $b[':dtype']  = $type;
            $b[':dvalue'] = $value;
        }
    }

    // Interpolated rather than bound, and clamped rather than trusted: a bound
    // placeholder cannot stand in for a LIMIT quantity on this connection, which
    // runs with emulated prepares off. The clamp is what makes that safe, and it
    // is why the value never reaches here from a request — the only caller that
    // passes one is the home page asking for three.
    $limit = '';
    if (isset($f['limit'])) {
        $limit = ' LIMIT ' . max(1, min(50, (int) $f['limit']));
    }

    try {
        return qAll("
            SELECT i.*
              FROM itineraries i
             WHERE " . implode(' AND ', $w) . "
             ORDER BY i.sort_order, FIELD(i.duration_type, 'hours', 'days'),
                      i.duration_value, i.title_en" . $limit, $b);
    } catch (Throwable $e) {
        error_log('itineraries_list: ' . $e->getMessage());
        return [];
    }
}

/**
 * Stop counts and total minutes for a set of plans, keyed by plan id.
 *
 * One grouped query for the whole list. `total` is every stop on the route, which
 * is what a card counts, because that matches the numbered list down the plan
 * page. `mins` counts only the stops whose place is still linkable, mirroring
 * ITIN_LIVE_SQL: a stop pointing at a place that has been unpublished has no
 * duration to add, and counting it as zero would make a full day read as a short
 * one.
 *
 * @param  list<int> $ids
 * @return array<int, array{total:int, mins:int}>
 */
function itinerary_stop_stats(array $ids): array
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn(int $i): bool => $i > 0)));
    if ($ids === []) {
        return [];
    }

    $in = implode(',', $ids);

    try {
        $rows = qAll("
            SELECT s.itinerary_id,
                   COUNT(*) AS total,
                   SUM(CASE WHEN " . ITIN_LIVE_SQL . "
                            THEN COALESCE(p.duration_mins, 0) ELSE 0 END) AS mins
              FROM itinerary_stops s
              LEFT JOIN places p ON p.id = s.place_id
             WHERE s.itinerary_id IN ($in)
             GROUP BY s.itinerary_id");
    } catch (Throwable $e) {
        error_log('itinerary_stop_stats: ' . $e->getMessage());
        return [];
    }

    $out = [];
    foreach ($rows as $r) {
        $out[(int) $r['itinerary_id']] = [
            'total' => (int) $r['total'],
            'mins'  => (int) $r['mins'],
        ];
    }
    return $out;
}

/**
 * The lengths that actually have a published plan behind them.
 *
 * Built from the rows rather than from a fixed list of 1/2/3/8/12/24, so the
 * filter never offers a chip that leads to an empty page — which is exactly what
 * a hardcoded list would do on a site whose editor has only written two plans.
 *
 * @return list<array{key:string, type:string, value:int, n:int}>
 */
function itineraries_lengths(): array
{
    try {
        $rows = qAll("
            SELECT duration_type, duration_value, COUNT(*) AS n
              FROM itineraries
             WHERE status = 'published' AND deleted_at IS NULL
             GROUP BY duration_type, duration_value
             ORDER BY FIELD(duration_type, 'hours', 'days'), duration_value");
    } catch (Throwable $e) {
        error_log('itineraries_lengths: ' . $e->getMessage());
        return [];
    }

    $out = [];
    foreach ($rows as $r) {
        $type  = (string) $r['duration_type'];
        $value = (int) $r['duration_value'];
        if (!in_array($type, ['hours', 'days'], true) || $value < 1) {
            continue;
        }
        $out[] = [
            'key'   => itin_len_key($type, $value),
            'type'  => $type,
            'value' => $value,
            'n'     => (int) $r['n'],
        ];
    }
    return $out;
}

/** The audiences that actually have a published plan behind them. */
function itineraries_audiences(): array
{
    try {
        $rows = qAll("
            SELECT audience, COUNT(*) AS n
              FROM itineraries
             WHERE status = 'published' AND deleted_at IS NULL AND audience <> 'any'
             GROUP BY audience
             ORDER BY COUNT(*) DESC, audience");
    } catch (Throwable $e) {
        error_log('itineraries_audiences: ' . $e->getMessage());
        return [];
    }

    $out = [];
    foreach ($rows as $r) {
        $out[] = ['slug' => (string) $r['audience'], 'n' => (int) $r['n']];
    }
    return $out;
}

/** One plan by slug. Null means genuinely not there. */
function itinerary_by_slug(string $slug): ?array
{
    if ($slug === '' || strlen($slug) > 96) {
        return null;
    }
    try {
        $row = qOne("
            SELECT i.*
              FROM itineraries i
             WHERE i.slug = :slug AND i.status = 'published' AND i.deleted_at IS NULL
             LIMIT 1", [':slug' => $slug]);
    } catch (Throwable $e) {
        error_log('itinerary_by_slug: ' . $e->getMessage());
        return null;
    }
    return $row ?: null;
}

/**
 * Every stop on a plan, in the order the plan visits them.
 *
 * A row whose place_id is NULL, or whose place is no longer published, comes
 * back with `place_slug` NULL and `place_state` saying which of the two it is.
 * The page then prints the stop as text rather than as a link — a route that
 * quietly drops a stop because an editor unpublished a place would misrepresent
 * the day it is describing.
 */
function itinerary_stops(int $itineraryId): array
{
    try {
        $rows = qAll("
            SELECT  s.id, s.place_id, s.day_number, s.sort_order,
                    s.time_note_en, s.time_note_ar, s.note_en, s.note_ar,
                    p.slug AS place_slug, p.name_en, p.name_ar,
                    p.summary_en, p.summary_ar, p.duration_mins, p.fee_level,
                    p.status AS place_status, p.deleted_at AS place_deleted,
                    m.code AS mun_code, m.name_en AS mun_en, m.name_ar AS mun_ar,
                    c.icon AS cat_icon, c.color AS cat_color,
                    l.slug AS loc_slug, l.name_en AS loc_en, l.name_ar AS loc_ar
              FROM itinerary_stops s
              LEFT JOIN places         p ON p.id = s.place_id
              LEFT JOIN municipalities m ON m.id = p.municipality_id
              LEFT JOIN categories     c ON c.id = p.category_id
              LEFT JOIN localities     l ON l.id = p.locality_id
             WHERE s.itinerary_id = :id
             ORDER BY s.day_number, s.sort_order, s.id", [':id' => $itineraryId]);
    } catch (Throwable $e) {
        error_log('itinerary_stops: ' . $e->getMessage());
        return [];
    }

    $live = static fn(array $r): bool => $r['place_slug'] !== null
        && (string) $r['place_status'] === 'published'
        && $r['place_deleted'] === null;

    $out = [];
    foreach ($rows as $r) {
        $isLive = $live($r);

        $out[] = [
            'id'         => (int) $r['id'],
            'day'        => max(1, (int) $r['day_number']),
            'time_note'  => pick($r, 'time_note'),
            'note'       => pick($r, 'note'),

            // A stop whose place has been unpublished or binned is not a link
            // and has no name to show — see the note at the top of this file.
            // It keeps its own text, so the day still reads whole.
            'live'       => $isLive,

            'slug'       => $isLive ? (string) $r['place_slug'] : '',
            'name'       => $isLive ? pick($r, 'name') : '',
            'summary'    => $isLive ? pick($r, 'summary') : '',
            'mins'       => $isLive && $r['duration_mins'] !== null ? (int) $r['duration_mins'] : null,
            'fee'        => $isLive ? (string) $r['fee_level'] : '',
            'icon'       => $isLive ? (string) ($r['cat_icon'] ?? '') : '',
            'color'      => $isLive ? (string) ($r['cat_color'] ?? '') : '',
            'mun'        => $isLive
                ? ['code' => (string) $r['mun_code'], 'name_en' => (string) $r['mun_en'], 'name_ar' => (string) $r['mun_ar']]
                : null,
            // The map marks localities rather than places, so this is what the
            // "see the route on the map" link hands to index.php?mark=.
            'loc'        => $isLive && $r['loc_slug'] !== null ? (string) $r['loc_slug'] : '',
        ];
    }
    return $out;
}

/**
 * Stops grouped into the days the plan runs over.
 *
 * A day plan has one group; a three-day plan has three; a stopover has one.
 * Numbering comes from the group's position in the sorted list rather than from
 * a `1..n` loop, so a plan whose stops were written with day numbers 1 and 3
 * renders as two days instead of three with a hole in the middle.
 *
 * @return list<array{day:int, label:int, stops:list<array>}>
 */
function itinerary_days(array $stops): array
{
    $byDay = [];
    foreach ($stops as $s) {
        $byDay[$s['day']][] = $s;
    }
    ksort($byDay);

    $multi = count($byDay) > 1;
    $out   = [];
    $n     = 0;

    foreach ($byDay as $stopsInDay) {
        $n++;
        $out[] = [
            // The stored day number, kept for anything that needs the real thing.
            'day'   => $n,
            // Only worth printing when there is more than one. "Day 1" above a
            // list of four stops and nothing else is a label describing a
            // distinction the plan does not make.
            'label' => $multi ? $n : 0,
            'stops' => $stopsInDay,
        ];
    }
    return $out;
}

/**
 * The plan's own arithmetic: how long the stops add up to, and how many of them
 * have no recorded time.
 *
 * Only live stops count. A stop pointing at a place that no longer exists has no
 * duration to add, and counting a stopover of "unknown" length as zero would
 * make a full day read as a short one. A hand-written stop is not counted at all
 * for the same reason: it is not a place with a duration, it is a sentence.
 *
 * `live` is the number of stops that contributed a duration — NOT the length of
 * the route. The route is as long as the numbered list on the page, which is
 * count($stops), and the page uses that for what it shows.
 *
 * @return array{mins:int, unknown:int, live:int}
 */
function itinerary_totals(array $stops): array
{
    $mins = 0;
    $unknown = 0;
    $live = 0;

    foreach ($stops as $s) {
        if (!$s['live']) {
            continue;
        }
        $live++;
        if ($s['mins'] === null || $s['mins'] <= 0) {
            $unknown++;
        } else {
            $mins += $s['mins'];
        }
    }

    return ['mins' => $mins, 'unknown' => $unknown, 'live' => $live];
}

/** "2 days", "8 hours", "1 day" — the plan's length, in words. */
function itin_length_label(array $itin): string
{
    $value = max(1, (int) ($itin['duration_value'] ?? 1));

    if ((string) ($itin['duration_type'] ?? 'days') === 'hours') {
        return $value === 1
            ? t('itin.hour_one')
            : t('itin.hours', [':n' => num($value)]);
    }

    return $value === 1
        ? t('itin.day_one')
        : t('itin.days', [':n' => num($value)]);
}

/** Who the plan is for, as a printable word. Never empty. */
function itin_audience_label(array $itin): string
{
    $who = (string) ($itin['audience'] ?? 'any');
    return t('who.' . $who);
}

/** The short key the length filter uses: 'd2' for two days, 'h8' for eight hours. */
function itin_len_key(string $type, int $value): string
{
    return ($type === 'hours' ? 'h' : 'd') . $value;
}

/** The inverse: 'h8' back to ['hours', 8]. An unknown key gives ['', 0]. */
function itin_split_len(string $key): array
{
    if (preg_match('/^([dh])([0-9]{1,2})$/', $key, $m) !== 1) {
        return ['', 0];
    }
    $value = (int) $m[2];
    if ($value < 1 || $value > 30) {
        return ['', 0];
    }
    return [$m[1] === 'h' ? 'hours' : 'days', $value];
}

/**
 * Read and validate the plan filters from a request array.
 *
 * Same contract as places_read_filters(): every value is whitelisted by shape,
 * and anything unrecognised is dropped rather than forwarded. Values arrive from
 * $_GET, so any of them may be an array — only strings are considered.
 *
 * @return array{len?:string, who?:string, budget?:string}
 */
function itin_read_filters(array $src): array
{
    $str = static fn(array $a, string $k): string => isset($a[$k]) && is_string($a[$k]) ? $a[$k] : '';

    $f = [];

    $len = strtolower(trim($str($src, 'len')));
    if (itin_split_len($len)[0] !== '') {
        $f['len'] = $len;
    }

    $who = strtolower(trim($str($src, 'who')));
    if (in_array($who, ['any', 'family', 'couple', 'budget', 'adventure', 'solo'], true)) {
        $f['who'] = $who;
    }

    $budget = strtolower(trim($str($src, 'budget')));
    if (in_array($budget, ['free', 'low', 'medium', 'high'], true)) {
        $f['budget'] = $budget;
    }

    return $f;
}

/** A link back to the plan list with a filter set applied or removed. */
function itin_url(array $f): string
{
    return APP_BASE . '/itineraries.php' . ($f === [] ? '' : '?' . http_build_query($f));
}

/**
 * The same filter set as a query string, for links that carry plan context.
 *
 * Returns '' when there is nothing to carry, so a call site can append it
 * without checking — see the "back to the plans" link on the detail page.
 */
function itin_query(array $f): string
{
    return $f === [] ? '' : http_build_query($f);
}

/**
 * One plan as a card.
 *
 * Shared by the plan list and by the band on the home page, because the two want
 * exactly the same thing and a second copy of this markup would be a second
 * place to fix the next time the card changes.
 *
 * The figure in the thumb is the plan's length rather than a photograph: the
 * panel's cover_image field is optional, and a card that looks broken whenever an
 * editor has not filled it in is worse than a card that never needed it.
 *
 * `$stat` is one row of itinerary_stop_stats(), so the caller decides whether it
 * is worth asking for the counts — the home page asks for three plans, the list
 * page for all of them.
 *
 * Needs places_duration() from inc/places.php, which every caller already loads
 * for its own totals.
 *
 * @param array<string,mixed> $plan
 * @param array{total:int, mins:int} $stat
 */
function itin_card(array $plan, array $stat): string
{
    $slug  = (string) ($plan['slug'] ?? '');
    $count = max(1, (int) ($plan['duration_value'] ?? 1));
    $hours = (string) ($plan['duration_type'] ?? 'days') === 'hours';
    $intro = pick($plan, 'intro');
    $dur   = places_duration((int) ($stat['mins'] ?? 0));

    // Every stop on the route, including hand-written ones and any whose place
    // has since been unpublished — those are still numbered lines on the plan
    // page, and a card that says "7 stops" above a route of 8 makes the reader
    // doubt the rest of the numbers.
    $stops = (int) ($stat['total'] ?? 0);

    $h  = '<a class="place-card" href="' . e(APP_BASE . '/itinerary.php?slug=' . rawurlencode($slug)) . '">';
    $h .= '<div class="thumb plan-thumb">'
        . '<b>' . e(num($count)) . '</b>'
        . '<small>' . e($hours ? t('itin.unit_hours') : t('itin.unit_days')) . '</small>'
        . '</div>';

    $h .= '<div class="body">';
    $h .= '<h3>' . e(pick($plan, 'title')) . '</h3>';
    if ($intro !== '') {
        $h .= '<p class="sm">' . e($intro) . '</p>';
    }

    $h .= '<div class="meta">';
    $h .= '<span class="pill">' . e(itin_audience_label($plan)) . '</span>';
    $h .= '<span class="pill">' . e(t('fee.' . (string) ($plan['budget_level'] ?? 'medium'))) . '</span>';

    // "0 stops" would be the only thing the card says about a plan an editor
    // published before writing its route, so the pill is left off entirely.
    if ($stops > 0) {
        $h .= '<span class="pill">'
            . e($stops === 1 ? t('plans.stops_one') : t('plans.stops', [':n' => num($stops)]))
            . '</span>';
    }
    if ($dur !== '') {
        $h .= '<span class="pill">' . e(t('plans.about_time', [':time' => $dur])) . '</span>';
    }
    $h .= '</div></div></a>';

    return $h;
}
