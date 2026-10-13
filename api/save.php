<?php
/**
 * POST /api/save.php — store a map and hand back a short share link.
 *
 * The request is multipart (FormData) rather than JSON because it carries the
 * rendered PNG alongside the selection. That PNG is the whole point of the
 * endpoint: it is served back from img.php as the og:image, so a link posted to
 * Facebook previews as the person's own map instead of a generic logo.
 *
 * Everything in the body is treated as hostile. The selection is checked
 * against the real slugs and municipality codes, the theme against the real
 * themes, and the image against its actual bytes — a Content-Type header is a
 * claim, not a fact.
 */

declare(strict_types=1);

require dirname(__DIR__) . '/inc/bootstrap.php';
require dirname(__DIR__) . '/inc/mapdata.php';
require dirname(__DIR__) . '/inc/gamify.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    json_fail('POST required.', 405);
}

if (!csrf_ok($_POST['csrf'] ?? null)) {
    json_fail('csrf', 403);
}

if (!map_ready()) {
    json_fail('not_ready', 503);
}

if (!under_rate_limit()) {
    json_fail('rate_limited', 429);
}

// ---------------------------------------------------------------------------
// The selection
// ---------------------------------------------------------------------------

$validLocality = [];
foreach (map_localities() as $l) {
    $validLocality[$l['slug']] = true;
}
$validMunicipality = array_fill_keys(array_keys(map_geo()['municipalities']), true);

/** Decode a posted JSON array into a clean list of known slugs. */
$cleanSlugs = static function (?string $raw, array $allowed, int $cap): array {
    $list = json_decode((string) $raw, true);
    if (!is_array($list)) {
        return [];
    }
    $out = [];
    foreach ($list as $v) {
        if (is_string($v) && isset($allowed[$v])) {
            $out[$v] = true;      // also de-duplicates
        }
        if (count($out) >= $cap) {
            break;
        }
    }
    return array_keys($out);
};

$places  = $cleanSlugs($_POST['places']  ?? null, $validLocality, 200);
$regions = $cleanSlugs($_POST['regions'] ?? null, $validMunicipality, 16);

// ---------------------------------------------------------------------------
// Name, theme, personality
// ---------------------------------------------------------------------------

$name = clean_display_name($_POST['name'] ?? null);

$themes = share_themes();
$theme  = (string) ($_POST['theme'] ?? '');
if (!isset($themes[$theme])) {
    $theme = (string) (array_key_first($themes) ?: 'maroon');
}

/** Match a posted slug against a table's slugs, returning null if unknown. */
$matchSlug = static function (array $rows, ?string $slug): ?string {
    if (!is_string($slug) || $slug === '') {
        return null;
    }
    foreach ($rows as $r) {
        if ($r['slug'] === $slug) {
            return $slug;
        }
    }
    return null;
};

$personality = $matchSlug(personalities(), $_POST['personality'] ?? null);
$levelSlug   = $matchSlug(levels(), $_POST['level'] ?? null);

// ---------------------------------------------------------------------------
// The snapshot
// ---------------------------------------------------------------------------

$snapshot = null;
$shotW = $shotH = $shotBytes = null;

$file = $_FILES['snapshot'] ?? null;
if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    json_fail('snapshot_missing', 400);
}
if (($file['size'] ?? 0) > MAX_SNAPSHOT_BYTES) {
    json_fail('snapshot_too_large', 413);
}
if (!is_uploaded_file($file['tmp_name'])) {
    json_fail('bad_upload', 400);
}

$bytes = file_get_contents($file['tmp_name']);
if ($bytes === false || !looks_like_an_image($bytes)) {
    json_fail('not_png', 415);
}

// getimagesize reads the real header, so a PNG-extension file containing
// something else is caught here rather than being stored and served back.
$info = @getimagesize($file['tmp_name']);
if (!$info || ($info[2] ?? null) !== IMAGETYPE_PNG || $info[0] < 1 || $info[1] < 1) {
    json_fail('not_png', 415);
}

$snapshot  = $bytes;
$shotW     = (int) $info[0];
$shotH     = (int) $info[1];
$shotBytes = strlen($bytes);

// ---------------------------------------------------------------------------
// Store it
// ---------------------------------------------------------------------------

$pdo = db();
$shareCode = make_unique_code();

try {
    $pdo->beginTransaction();

    q(
        'INSERT INTO saved_maps
            (code, display_name, theme, personality, level_slug, snapshot,
             shot_w, shot_h, shot_bytes, mun_count, loc_count, lang, ip_hash)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [
            $shareCode,
            $name !== '' ? $name : null,
            $theme,
            $personality,
            $levelSlug,
            $snapshot,
            $shotW,
            $shotH,
            $shotBytes,
            count($regions),
            count($places),
            current_lang(),
            ip_hash(),
        ]
    );

    $mapId = (int) $pdo->lastInsertId();

    $locIds = [];
    foreach (qAll('SELECT id, slug FROM localities') as $r) {
        $locIds[$r['slug']] = (int) $r['id'];
    }
    $munIds = [];
    foreach (qAll('SELECT id, code FROM municipalities') as $r) {
        $munIds[$r['code']] = (int) $r['id'];
    }

    $ins = $pdo->prepare(
        'INSERT IGNORE INTO saved_map_items (saved_map_id, item_type, item_id) VALUES (?, ?, ?)'
    );
    foreach ($places as $slug) {
        if (isset($locIds[$slug])) {
            $ins->execute([$mapId, 'locality', $locIds[$slug]]);
        }
    }
    foreach ($regions as $munCode) {
        if (isset($munIds[$munCode])) {
            $ins->execute([$mapId, 'municipality', $munIds[$munCode]]);
        }
    }

    q(
        'INSERT INTO card_events (saved_map_id, event, format, theme, ip_hash) VALUES (?, ?, ?, ?, ?)',
        [$mapId, 'create', 'png_1x1', $theme, ip_hash()]
    );

    $pdo->commit();
} catch (Throwable $ex) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('save.php: ' . $ex->getMessage());
    json_fail('save_failed', 500);
}

json_out([
    'ok'      => true,
    'code'    => $shareCode,
    'url'     => site_url('s.php?c=' . $shareCode),
    'image'   => site_url('img.php?c=' . $shareCode),
    'places'  => count($places),
    'regions' => count($regions),
]);
