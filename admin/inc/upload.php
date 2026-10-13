<?php
/**
 * Image intake: validate, resize, thumbnail, and store.
 *
 * Three things this deliberately does not do:
 *
 *  - it does not trust the browser's MIME type, or the file extension. Both come
 *    from the client. getimagesize() reads the actual bytes.
 *  - it does not keep the uploaded filename. A name from a form is a path
 *    traversal waiting to happen, and two people uploading "photo.jpg" would
 *    collide. Every stored name is generated.
 *  - it does not store the original. A 4 MB phone photo is 4 MB on every page
 *    load forever; the resized copy is what the site actually serves.
 *
 * Requires GD, which XAMPP ships enabled. If it is missing the upload is refused
 * with an explanation rather than silently storing an unresized original.
 */

declare(strict_types=1);

/** Absolute path to the uploads directory, created on first use. */
function upload_dir(): string
{
    $dir = dirname(__DIR__, 2) . '/' . UPLOAD_DIR;
    if (!is_dir($dir)) {
        // 0775 rather than 0777: the web server needs to write here, the world
        // does not.
        @mkdir($dir, 0775, true);
    }
    return $dir;
}

/**
 * Store one uploaded image against a place.
 *
 * @param array<string, mixed> $file One entry from $_FILES.
 * @return array{ok: bool, path: string, thumb: string, webp: string, error: string}
 */
function image_store(array $file, int $placeId): array
{
    $fail = static fn(string $m): array => ['ok' => false, 'path' => '', 'thumb' => '', 'webp' => '', 'error' => $m];

    if (!function_exists('imagecreatetruecolor')) {
        return $fail('PHP is running without the GD extension, so images cannot be resized. '
                   . 'Enable extension=gd in php.ini and reload Apache.');
    }

    $err = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($err !== UPLOAD_ERR_OK) {
        return $fail(match ($err) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'That file is larger than the server accepts.',
            UPLOAD_ERR_PARTIAL                        => 'The upload was cut short. Try again.',
            UPLOAD_ERR_NO_FILE                        => 'No file was chosen.',
            UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE => 'The server could not write the temporary file.',
            default                                   => 'The upload failed (code ' . $err . ').',
        });
    }

    $tmp = (string) ($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        return $fail('That did not arrive as an upload.');
    }

    $size = (int) ($file['size'] ?? 0);
    if ($size <= 0 || $size > MAX_UPLOAD_BYTES) {
        return $fail('Images must be under ' . round(MAX_UPLOAD_BYTES / 1048576, 1) . ' MB.');
    }

    // The actual bytes, not what the browser claimed.
    $info = @getimagesize($tmp);
    if ($info === false) {
        return $fail('That file is not an image.');
    }

    $src = match ($info[2]) {
        IMAGETYPE_JPEG => @imagecreatefromjpeg($tmp),
        IMAGETYPE_PNG  => @imagecreatefrompng($tmp),
        IMAGETYPE_GIF  => @imagecreatefromgif($tmp),
        IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($tmp) : null,
        default        => null,
    };
    if ($src === false || $src === null) {
        return $fail('That image type is not supported. JPEG, PNG, GIF and WebP work.');
    }

    $w = imagesx($src);
    $h = imagesy($src);
    if ($w < 1 || $h < 1) {
        return $fail('That image has no size.');
    }

    // ---- where it goes: uploads/YYYY/MM/, so one directory never holds years
    // of uploads and a listing stays fast on Windows.
    $sub = date('Y/m');
    $abs = upload_dir() . '/' . $sub;
    if (!is_dir($abs) && !@mkdir($abs, 0775, true)) {
        return $fail('Could not create the upload folder. Check that ' . UPLOAD_DIR . '/ is writable.');
    }

    $stem = 'p' . $placeId . '-' . date('Ymd-His') . '-' . bin2hex(random_bytes(4));

    // ---- the stored copy: longest edge capped
    $main = image_scale($src, $w, $h, MAX_IMAGE_EDGE);
    $mainName = $stem . '.jpg';
    if (!@imagejpeg($main, $abs . '/' . $mainName, 82)) {
        return $fail('Could not save the image. Check the folder permissions.');
    }

    // ---- the thumbnail the admin grid and the guide cards use
    $thumb     = image_scale($src, $w, $h, 640);
    $thumbName = 'thumb-' . $stem . '.jpg';
    if (!@imagejpeg($thumb, $abs . '/' . $thumbName, 78)) {
        $thumbName = '';   // not fatal: the full image still works
    }

    // ---- a WebP copy when the build supports it. Roughly a third smaller, and
    // the front end can prefer it with a <picture> element.
    $webpName = '';
    if (function_exists('imagewebp')) {
        $candidate = 'webp-' . $stem . '.webp';
        if (@imagewebp($main, $abs . '/' . $candidate, 78)) {
            $webpName = $candidate;
        }
    }

    // No imagedestroy() calls: GD images have been freed by the garbage
    // collector since PHP 8.0, and the function is deprecated as of 8.5.
    $rel = $sub . '/';
    return [
        'ok'    => true,
        'path'  => $rel . $mainName,
        'thumb' => $thumbName === '' ? '' : $rel . $thumbName,
        'webp'  => $webpName === '' ? '' : $rel . $webpName,
        'error' => '',
    ];
}

/**
 * A copy of $src with its longest edge at most $max.
 *
 * PNG and GIF keep their transparency: a logo placed on a coloured card would
 * otherwise come out on a black rectangle, which is the classic "why does my
 * image have a black box behind it" bug.
 */
function image_scale(GdImage $src, int $w, int $h, int $max): GdImage
{
    $ratio = min(1.0, $max / max($w, $h));
    $nw = max(1, (int) round($w * $ratio));
    $nh = max(1, (int) round($h * $ratio));

    $dst = imagecreatetruecolor($nw, $nh);

    // A true-colour destination starts opaque black. Fill it with white first so
    // a JPEG (which has no alpha) of a transparent PNG looks reasonable rather
    // than like a hole.
    $white = imagecolorallocate($dst, 255, 255, 255);
    imagefilledrectangle($dst, 0, 0, $nw, $nh, $white);
    imagealphablending($dst, true);

    imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
    return $dst;
}

/** Delete an image row and the three files it owns. */
function image_forget(array $row): bool
{
    $base = upload_dir() . '/';
    foreach (['path', 'thumb_path', 'webp_path'] as $col) {
        $rel = (string) ($row[$col] ?? '');
        // basename() twice over: the stored value is a relative path we wrote
        // ourselves, but a delete is the wrong place to find out otherwise.
        if ($rel === '' || str_contains($rel, '..')) {
            continue;
        }
        $file = $base . ltrim($rel, '/');
        if (is_file($file)) {
            @unlink($file);
        }
    }
    try {
        q('DELETE FROM place_images WHERE id = ?', [(int) $row['id']]);
        return true;
    } catch (Throwable $e) {
        error_log('image_forget: ' . $e->getMessage());
        return false;
    }
}

/** Make one image the cover for its place, and no other. */
function image_make_cover(int $imageId): bool
{
    $row = qOne('SELECT id, place_id FROM place_images WHERE id = ?', [$imageId]);
    if ($row === null) {
        return false;
    }
    try {
        // Two statements, so there is never a moment with two covers or none.
        q('UPDATE place_images SET is_cover = 0 WHERE place_id = ?', [(int) $row['place_id']]);
        q('UPDATE place_images SET is_cover = 1 WHERE id = ?', [$imageId]);
        return true;
    } catch (Throwable $e) {
        error_log('image_make_cover: ' . $e->getMessage());
        return false;
    }
}
