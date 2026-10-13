<?php
/**
 * Loads the gamification definitions — levels, badges, travel personalities.
 *
 * Only the definitions come from here. Working out which badges a visitor has
 * earned happens in the browser (assets/js/gamify.js), because the selection it
 * is judged against lives in localStorage and never reaches the server until
 * someone asks for a share link.
 *
 * Every loader returns an empty array rather than throwing when the database
 * has not been imported yet: a missing badge table should cost you the badges
 * panel, not the whole map.
 */

declare(strict_types=1);

/** Ascending by min_places, so the last row whose threshold is met is current. */
function levels(): array
{
    static $rows = null;
    if ($rows !== null) {
        return $rows;
    }
    try {
        $rows = qAll('SELECT slug, name_en, name_ar, min_places, color FROM levels ORDER BY min_places, sort_order');
    } catch (Throwable $e) {
        error_log('levels: ' . $e->getMessage());
        $rows = [];
    }
    return $rows;
}

function badges(): array
{
    static $rows = null;
    if ($rows !== null) {
        return $rows;
    }
    try {
        $rows = qAll('SELECT slug, name_en, name_ar, description_en, description_ar, icon, rule_json
                        FROM badges ORDER BY sort_order');
    } catch (Throwable $e) {
        error_log('badges: ' . $e->getMessage());
        $rows = [];
    }
    return $rows;
}

function personalities(): array
{
    static $rows = null;
    if ($rows !== null) {
        return $rows;
    }
    try {
        $rows = qAll('SELECT slug, name_en, name_ar, blurb_en, blurb_ar, weights_json
                        FROM personalities ORDER BY priority');
    } catch (Throwable $e) {
        error_log('personalities: ' . $e->getMessage());
        $rows = [];
    }
    return $rows;
}

/**
 * Personality icons live here rather than in the table: they are presentation,
 * and the set is small and fixed. An unknown slug gets a compass rather than
 * an empty box.
 */
function persona_icon(string $slug): string
{
    $icons = [
        'desert-soul'      => '🐪',
        'coastline-chaser' => '🌊',
        'city-explorer'    => '🏙️',
        'heritage-hunter'  => '🏺',
        'family-planner'   => '🧺',
        'completionist'    => '🏆',
    ];
    return $icons[$slug] ?? '🧭';
}

/**
 * The JSON columns, decoded once and handed to the browser as real objects.
 * A row whose JSON is malformed is dropped rather than crashing the page —
 * it is content someone typed, not something we control.
 */function gamify_payload(): array
{
    $decode = function (array $rows, string $col): array {
        $out = [];
        foreach ($rows as $r) {
            $rule = json_decode($r[$col] ?? '', true);
            if (!is_array($rule)) {
                continue;
            }
            unset($r[$col]);
            $r['rule'] = $rule;
            $out[] = $r;
        }
        return $out;
    };

    return [
        'levels'        => levels(),
        'badges'        => $decode(badges(), 'rule_json'),
        'personalities' => $decode(personalities(), 'weights_json'),
    ];
}
