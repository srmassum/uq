<?php
/**
 * GET /img.php?c=CODE — serve a saved map's rendered card.
 *
 * This is the og:image target. Facebook, WhatsApp, X and Telegram all fetch it
 * when someone pastes a share link, which is the entire reason the card is
 * stored as a blob rather than discarded client-side.
 *
 * The blob was validated as a real PNG by getimagesize() before it was stored,
 * and it is served with nosniff, so it cannot be talked into being markup.
 */

declare(strict_types=1);

require __DIR__ . '/inc/bootstrap.php';

$code = strtoupper(trim((string) ($_GET['c'] ?? '')));

if (!preg_match('/^[' . CODE_ALPHABET . ']{' . SHORT_CODE_LEN . '}$/', $code)) {
    serve_placeholder(400);
}

$row = qOne(
    'SELECT snapshot FROM saved_maps WHERE code = ? AND status = \'active\'',
    [$code]
);

$png = $row['snapshot'] ?? null;

// PDO hands back MEDIUMBLOB as a string, but a stream is possible depending on
// how the driver is configured, so both shapes are handled.
if (is_resource($png)) {
    $png = stream_get_contents($png);
}

if (!is_string($png) || $png === '') {
    serve_placeholder(404);
}

header('Content-Type: image/png');
header('Content-Length: ' . strlen($png));
// Immutable: a code always maps to the same card, so this is safe to pin hard.
header('Cache-Control: public, max-age=31536000, immutable');
header('ETag: "qum-' . $code . '"');
header('Content-Disposition: inline; filename="qatar-map-' . $code . '.png"');
echo $png;
exit;


/**
 * A last-resort image so a link never previews with nothing at all. Only
 * reached for a code that does not exist, or one saved before snapshots were
 * required — so a plain wordmark is enough, and GD's built-in bitmap font
 * means no font file has to ship with the site.
 */
function serve_placeholder(int $status): never
{
    http_response_code($status);
    header('Content-Type: image/png');
    header('Cache-Control: no-store');

    if (!function_exists('imagecreatetruecolor')) {
        exit;
    }

    $w = 1080;
    $h = 1080;
    $im = imagecreatetruecolor($w, $h);

    $bg   = imagecolorallocate($im, 0xfb, 0xfa, 0xf9);
    $bar  = imagecolorallocate($im, 0x8b, 0x15, 0x38);
    $ink  = imagecolorallocate($im, 0x78, 0x71, 0x6c);

    imagefilledrectangle($im, 0, 0, $w, $h, $bg);
    imagefilledrectangle($im, 0, 0, $w, 14, $bar);

    // imagestring's built-in font is fixed-width and tiny, so it is scaled up
    // by drawing to a small canvas and resampling.
    $tmp = imagecreatetruecolor(320, 40);
    $tbg = imagecolorallocate($tmp, 0xfb, 0xfa, 0xf9);
    $tink = imagecolorallocate($tmp, 0x8b, 0x15, 0x38);
    imagefilledrectangle($tmp, 0, 0, 320, 40, $tbg);
    imagestring($tmp, 5, 40, 12, 'Qatar Unseen', $tink);
    imagecopyresampled($im, $tmp, 240, 500, 0, 0, 600, 75, 320, 40);
    imagedestroy($tmp);

    imagestring($im, 3, 460, 600, 'map not available', $ink);

    imagepng($im);
    imagedestroy($im);
    exit;
}
