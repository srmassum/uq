<?php
/**
 * Reads the map files produced by setup/prepare.php.
 *
 * These are build artefacts, not user input — but the path `d` strings get
 * written straight into SVG markup, so they are still validated against a
 * strict character whitelist before use. A build script that one day emits
 * something odd should not be able to inject markup.
 */

declare(strict_types=1);

function data_path(string $file): string
{
    return dirname(__DIR__) . '/assets/data/' . $file;
}

/** True once setup/prepare.php has been run. */
function map_ready(): bool
{
    return is_file(data_path('geo.json')) && is_file(data_path('localities.json'));
}

function read_json_file(string $file): array
{
    $full = data_path($file);
    if (!is_file($full)) {
        return [];
    }
    $raw = file_get_contents($full);
    if ($raw === false) {
        return [];
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

/** Only digits, signs, dots, spaces, commas and the handful of path commands. */
function safe_svg_path(?string $d): string
{
    if (!is_string($d) || $d === '') {
        return '';
    }
    return preg_match('/^[MmLlHhVvZzCcSsQqTtAa0-9eE+\-.,\s]*$/', $d) ? $d : '';
}

/** Map geometry: municipalities, their outlines, bounding boxes and label anchors. */
function map_geo(): array
{
    static $geo = null;
    if ($geo !== null) {
        return $geo;
    }

    $geo = read_json_file('geo.json');
    foreach ($geo['municipalities'] ?? [] as $code => $m) {
        $geo['municipalities'][$code]['d'] = safe_svg_path($m['d'] ?? '');
    }
    return $geo;
}

/**
 * Localities, in the order prepare.php derived them (which follows data/localities.php).
 * Each entry already carries its derived municipality and projected x/y.
 */
function map_localities(): array
{
    static $locs = null;
    if ($locs !== null) {
        return $locs;
    }
    $locs = read_json_file('localities.json');
    return $locs;
}

/** Localities grouped by municipality code, preserving order. */
function localities_by_municipality(): array
{
    $out = [];
    foreach (map_localities() as $l) {
        $out[$l['mun']][] = $l;
    }
    return $out;
}

/**
 * Translated label for a locality kind, or '' if we do not know the kind.
 *
 * Returning '' rather than the raw value matters: t() falls back to the key
 * itself, so an unrecognised kind would otherwise print "kind.foo" next to a
 * place name. A kind added to data/localities.php shows up as a blank tag
 * until it is given a string here, which is the failure mode we want.
 */
function kind_label(?string $kind): string
{
    static $known = ['city', 'town', 'district', 'landmark', 'industrial', 'natural'];
    return in_array($kind, $known, true) ? t('kind.' . $kind) : '';
}

/** Total markable things: every locality, since a municipality is covered by its places. */
function total_markable(): int
{
    return count(map_localities());}

/**
 * The share-card palettes. Canonical copy lives in sql/seed-core.sql; this is
 * the fallback for the window where prepare.php has run but the database has
 * not been imported yet, so the map is still usable and still looks right.
 */
function default_share_themes(): array
{
    $rows = [
        ['maroon', 'Maroon', 'عنابي',   '#ffffff', '#e7e2df', '#8b1538', '#8b1538', '#1c1917', '#78716c', 0, 10],
        ['desert', 'Desert', 'صحراوي',  '#fdf8ef', '#efe2c9', '#c2703a', '#a65a28', '#3f2d16', '#8a7355', 0, 20],
        ['pearl',  'Pearl',  'لؤلؤي',   '#f6fbfd', '#dceaf0', '#2b7a9b', '#1f6c8c', '#0f2b36', '#5b8291', 0, 30],
        ['night',  'Night',  'ليلي',    '#0b1220', '#1e293b', '#60a5fa', '#93c5fd', '#f1f5f9', '#94a3b8', 1, 40],
        ['flag',   'Flag',   'العلم',   '#ffffff', '#f0ece9', '#8b1538', '#8b1538', '#1c1917', '#78716c', 0, 50],
        ['slate',  'Slate',  'رمادي',   '#f8fafc', '#e2e8f0', '#334155', '#0f172a', '#0f172a', '#64748b', 0, 60],
    ];
    $out = [];
    foreach ($rows as $r) {
        $out[$r[0]] = [
            'slug' => $r[0], 'name_en' => $r[1], 'name_ar' => $r[2],
            'bg' => $r[3], 'land' => $r[4], 'visited' => $r[5], 'accent' => $r[6],
            'ink' => $r[7], 'ink_soft' => $r[8], 'is_dark' => $r[9], 'sort_order' => $r[10],
        ];
    }
    return $out;
}

/** The stylesheet themes, keyed by slug. */
function share_themes(): array
{
    static $themes = null;
    if ($themes !== null) {
        return $themes;
    }

    $themes = default_share_themes();

    // The table wins when it exists. A missing one is not an error worth
    // stopping a page for — the visitor just gets the built-in palette.
    try {
        foreach (qAll('SELECT * FROM share_themes ORDER BY sort_order') as $r) {
            $themes[$r['slug']] = $r;
        }
    } catch (Throwable $e) {
        error_log('share_themes: falling back to defaults — ' . $e->getMessage());
    }

    return $themes;
}
