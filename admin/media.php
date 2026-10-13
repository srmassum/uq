<?php
/**
 * The image library.
 *
 * Every picture on the site lives here: uploading, captions, photographer credit
 * and licence, which one is the cover, and which ones are sitting on a place
 * that has been deleted.
 *
 * It also serves the JSON the place editor's "Choose" button fetches, at
 * media.php?pick=1. That is why the pick request is handled before anything is
 * rendered — a JSON endpoint that has already printed a sidebar is not JSON.
 */

declare(strict_types=1);

require_once __DIR__ . '/inc/panel.php';
require_once __DIR__ . '/inc/upload.php';

require_admin('content.edit');

$def = [
    'page'   => 'media.php',
    'nav'    => 'media',
    'table'  => 'place_images',
    'module' => 'media',
    'entity' => 'place_image',
    'fields' => [
        ['k' => 'place_id', 't' => 'Place', 'type' => 'int', 'required' => true, 'min' => 1],
        ['k' => 'caption_en', 't' => 'Caption (English)', 'maxlen' => 255],
        ['k' => 'caption_ar', 't' => 'Caption (Arabic)', 'maxlen' => 255],
        ['k' => 'credit', 't' => 'Photographer', 'maxlen' => 160,
         'help' => 'Who took it. Leave blank for your own work.'],
        ['k' => 'licence', 't' => 'Licence', 'maxlen' => 80,
         'help' => 'e.g. own work, CC BY-SA 4.0, press kit. Worth recording now — a licence you cannot remember later is a licence you cannot use.'],
        ['k' => 'sort_order', 't' => 'Sort order', 'type' => 'number', 'min' => -999, 'max' => 999],
    ],
];

// ---------------------------------------------------------------------------
// The picker's JSON — before any output
// ---------------------------------------------------------------------------
if (get_str('pick') === '1') {
    header('Content-Type: application/json; charset=utf-8');
    $out = [];
    try {
        $rows = qAll(
            'SELECT i.id, i.path, i.thumb_path, i.caption_en, i.is_cover, p.name_en AS place
               FROM place_images i
               JOIN places p ON p.id = i.place_id
              WHERE p.deleted_at IS NULL
              ORDER BY p.name_en, i.is_cover DESC, i.sort_order
              LIMIT 300'
        );
        foreach ($rows as $r) {
            $rel = (string) ($r['thumb_path'] !== '' ? $r['thumb_path'] : $r['path']);
            $out[] = [
                'path' => (string) $r['path'],
                'url'  => APP_BASE . '/' . UPLOAD_DIR . '/' . ltrim($rel, '/'),
                'name' => (string) ($r['caption_en'] !== '' ? $r['caption_en'] : $r['place']),
                'used' => (int) $r['is_cover'] ? ' · cover' : '',
            ];
        }
    } catch (Throwable $e) {
        error_log('media pick: ' . $e->getMessage());
    }
    echo json_encode(['ok' => true, 'images' => $out], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// ---------------------------------------------------------------------------
// Writes
// ---------------------------------------------------------------------------
admin_require_csrf();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $do = post_str('do');

    // ---- upload: one or many, all filed against one place
    if ($do === 'upload') {
        $placeId = post_int('place_id');
        if ($placeId <= 0) {
            admin_back(admin_url('media.php'), 'Choose which place these images belong to.', 'bad');
        }

        // A multi-file input arrives as parallel arrays; a single-file input
        // arrives as flat scalars. Normalise both into a list.
        $raw   = $_FILES['files'] ?? null;
        $files = [];
        if (is_array($raw) && is_array($raw['name'] ?? null)) {
            foreach (array_keys($raw['name']) as $i) {
                $files[] = [
                    'name'     => $raw['name'][$i],
                    'type'     => $raw['type'][$i],
                    'tmp_name' => $raw['tmp_name'][$i],
                    'error'    => $raw['error'][$i],
                    'size'     => $raw['size'][$i],
                ];
            }
        } elseif (is_array($raw)) {
            $files[] = $raw;
        }

        if ($files === []) {
            admin_back(admin_url('media.php', ['place' => $placeId]), 'No files were chosen.', 'warn');
        }

        $ok = 0;
        $problems = [];
        $firstId = 0;

        // Is this the place's first image? If so it becomes the cover, so a
        // place never ends up with pictures but no cover.
        $existing = (int) qVal('SELECT COUNT(*) FROM place_images WHERE place_id = ?', [$placeId]);

        foreach ($files as $f) {
            if ((int) ($f['error'] ?? 4) === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $res = image_store($f, $placeId);
            if (!$res['ok']) {
                $problems[] = (string) ($f['name'] ?? 'file') . ': ' . $res['error'];
                continue;
            }
            try {
                q(
                    'INSERT INTO place_images (place_id, path, thumb_path, webp_path, is_cover, sort_order)
                     VALUES (?, ?, ?, ?, ?, ?)',
                    [$placeId, $res['path'], $res['thumb'], $res['webp'],
                     ($ok === 0 && $existing === 0) ? 1 : 0, $existing + $ok]
                );
                $firstId = $firstId ?: (int) db()->lastInsertId();
                log_activity('media', 'upload', 'place_image', (int) db()->lastInsertId());
                $ok++;
            } catch (Throwable $e) {
                error_log('media upload row: ' . $e->getMessage());
                $problems[] = (string) ($f['name'] ?? 'file') . ': could not be recorded.';
            }
        }

        $msg = $ok . ' image' . ($ok === 1 ? '' : 's') . ' uploaded.';
        if ($problems !== []) {
            log_activity('media', 'upload_failed', 'place', $placeId, null, ['problems' => $problems]);
            admin_back(
                admin_url('media.php', ['place' => $placeId]),
                $msg . ' ' . count($problems) . ' could not be used: ' . implode(' ', array_slice($problems, 0, 3)),
                'warn'
            );
        }
        admin_back(admin_url('media.php', ['place' => $placeId]), $msg);
    }

    // ---- cover
    if ($do === 'cover') {
        $id = post_int('id');
        $ok = image_make_cover($id);
        if ($ok) {
            log_activity('media', 'cover', 'place_image', $id);
        }
        admin_back(
            admin_url('media.php', array_filter(['place' => post_int('place') ?: null])),
            $ok ? 'Cover image set.' : 'Could not set that as the cover.',
            $ok ? 'ok' : 'bad'
        );
    }

    // ---- delete
    if ($do === 'delete') {
        $id  = post_int('id');
        $row = record_by_id('place_images', $id);
        $ok  = $row !== null && image_forget($row);
        if ($ok) {
            log_activity('media', 'delete', 'place_image', $id, $row, null);
        }
        admin_back(
            admin_url('media.php', array_filter(['place' => post_int('place') ?: null])),
            $ok ? 'Image deleted.' : 'Could not delete that image.',
            $ok ? 'ok' : 'bad'
        );
    }

    // ---- caption / credit / licence
    $id  = post_int('id') ?: null;
    $res = record_save($def, $_POST, $id);
    if ($res['ok']) {
        admin_back(admin_url('media.php', ['place' => post_int('place_id') ?: null]), 'Image details saved.');
    }
    $errors    = $res['errors'];
    $formError = $errors['_form'] ?? 'Nothing was saved — check the highlighted fields.';
    ad_note(e($formError), 'bad');
}

// ---------------------------------------------------------------------------
// Reads
// ---------------------------------------------------------------------------
$place  = get_int('place');
$editId = get_int('edit');
$used   = get_str('used');
$q      = get_str('q');

$perPage = 36;
$page    = max(1, get_int('p', 1));
$offset  = ($page - 1) * $perPage;

$where = ['1'];
$bind  = [];

if ($place > 0) {
    $where[] = 'i.place_id = ?';
    $bind[]  = $place;
}
if ($q !== '') {
    $where[] = '(i.caption_en LIKE ? OR i.credit LIKE ? OR i.path LIKE ? OR p.name_en LIKE ?)';
    $needle  = '%' . admin_like_escape($q) . '%';
    array_push($bind, $needle, $needle, $needle, $needle);
}
if ($used === '0') {
    // "Unused" in this schema can only mean one thing: the image belongs to a
    // place that has been moved to the bin, so nothing on the site shows it.
    // place_images.place_id is NOT NULL, so there is no such thing as an image
    // belonging to no place at all.
    $where[] = 'p.deleted_at IS NOT NULL';
} elseif ($used === '1') {
    $where[] = 'p.deleted_at IS NULL';
}

$wsql = 'WHERE ' . implode(' AND ', $where);

$total = 0;
$rows  = [];
try {
    $total = (int) qVal(
        "SELECT COUNT(*) FROM place_images i JOIN places p ON p.id = i.place_id $wsql", $bind
    );
    $rows = qAll(
        "SELECT i.*, p.name_en AS place_name, p.deleted_at AS place_deleted
           FROM place_images i JOIN places p ON p.id = i.place_id
           $wsql
          ORDER BY p.name_en, i.is_cover DESC, i.sort_order, i.id
          LIMIT $perPage OFFSET $offset",
        $bind
    );
} catch (Throwable $e) {
    error_log('media.php: ' . $e->getMessage());
    ad_note('Could not read the image table. Has <code>sql/schema.sql</code> been imported?', 'bad');
}

$places = [];
try {
    foreach (qAll(
        "SELECT p.id, p.name_en, COUNT(i.id) n
           FROM places p LEFT JOIN place_images i ON i.place_id = p.id
          WHERE p.deleted_at IS NULL
          GROUP BY p.id, p.name_en
          ORDER BY p.name_en"
    ) as $r) {
        $places[(int) $r['id']] = $r['name_en'] . ((int) $r['n'] > 0 ? ' (' . (int) $r['n'] . ')' : '');
    }
} catch (Throwable $e) {
    error_log('media places: ' . $e->getMessage());
}

$editing = $editId > 0 ? record_by_id('place_images', $editId) : null;

// ---------------------------------------------------------------------------
// Render
// ---------------------------------------------------------------------------
admin_head(['title' => 'Images', 'nav' => $used === '0' ? 'unused' : 'media']);

$actions = [];
if ($places !== []) {
    $actions[] = ['href' => '#upload', 'label' => 'Upload images', 'kind' => 'primary'];
}

ad_title(
    $used === '0' ? 'Images on binned places' : 'Images',
    $used === '0'
        ? 'These belong to places you deleted, so nothing on the site is showing them. Deleting an image here frees the disk and cannot be undone.'
        : (string) $total . ' image' . ($total === 1 ? '' : 's')
          . ' across ' . count($places) . ' places.',
    $actions
);

// ---- the upload panel
ad_panel_open();
echo '<h2 id="upload">Upload images</h2>';
if ($places === []) {
    ad_empty('Add a place first — every image belongs to one.',
             '<a class="ad-btn" href="' . e(admin_url('place-edit.php')) . '">Add a place</a>');
} else {
    ad_form_open(admin_url('media.php'), '', 'Uploads are resized to ' . MAX_IMAGE_EDGE
        . 'px on the longest edge, given a thumbnail, and stored under '
        . e(UPLOAD_DIR) . '/YYYY/MM/. The original file is not kept.', true);
    echo '<input type="hidden" name="do" value="upload">';
    ad_field([
        'k' => 'place_id', 't' => 'Place', 'type' => 'select', 'required' => true,
        'options' => ['' => '— choose a place —'] + $places,
        'help' => 'Where these pictures belong. If the place has no cover yet, the first one uploaded becomes it.',
    ], (string) ($place ?: ''));
    ad_field([
        'k' => 'files', 't' => 'Images', 'type' => 'file', 'required' => true, 'multi' => true,
        'help' => 'JPEG, PNG, GIF or WebP, up to ' . round(MAX_UPLOAD_BYTES / 1048576, 1)
                . ' MB each. Select several at once with Ctrl or Shift.',
    ], '');
    ad_form_close('Upload', '');
    ad_panel_close();
}

// ---- one image's details
if ($editing !== null) {
    ad_panel_open();
    ad_form_open(admin_url('media.php', ['edit' => (int) $editing['id']]), 'Image details');
    echo '<input type="hidden" name="id" value="' . (int) $editing['id'] . '">';
    echo '<input type="hidden" name="place" value="' . (int) $editing['place_id'] . '">';
    echo '<input type="hidden" name="place_id" value="' . (int) $editing['place_id'] . '">';
    echo '<div class="ad-field wide"><img class="ad-thumb" style="max-inline-size:340px;max-block-size:240px" src="'
       . e(APP_BASE . '/' . UPLOAD_DIR . '/' . ltrim((string) $editing['path'], '/')) . '" alt=""></div>';
    foreach ($def['fields'] as $f) {
        if ($f['k'] === 'place_id') { continue; }
        ad_field($f, $editing[$f['k']] ?? '');
    }
    ad_form_close('Save details', admin_url('media.php', ['place' => (int) $editing['place_id']]));
    ad_panel_close();
}

// ---- the grid
ad_panel_open();
ad_filters(admin_url('media.php'), [
    ['k' => 'q', 't' => 'Search', 'ph' => 'Caption, credit, place…'],
    ['k' => 'place', 't' => 'Place', 'type' => 'select', 'options' => ['' => 'All places'] + $places],
    ['k' => 'used', 't' => 'Show', 'type' => 'select', 'options' => [
        '' => 'Everything in use', '1' => 'On live places', '0' => 'On binned places',
    ]],
], ['q' => $q, 'place' => $place ?: '', 'used' => $used], 'Filter');

if ($rows === []) {
    ad_empty($used === '0'
        ? 'Nothing here — no images belong to deleted places.'
        : 'No images yet.');
} else {
    echo '<div class="ad-media">';
    foreach ($rows as $im) {
        $rel = (string) ($im['thumb_path'] !== '' ? $im['thumb_path'] : $im['path']);
        ?>
        <figure>
          <img src="<?= e(APP_BASE . '/' . UPLOAD_DIR . '/' . ltrim($rel, '/')) ?>" alt="" loading="lazy">
          <figcaption>
            <b><?= e((string) ($im['caption_en'] !== '' ? $im['caption_en'] : basename((string) $im['path']))) ?></b>
            <span class="ad-muted"><?= e((string) $im['place_name']) ?></span>
            <?php if ((int) $im['is_cover']): ?><?= ad_chip('cover', 'ok') ?><?php endif; ?>
            <?php if ($im['place_deleted'] !== null): ?><?= ad_chip('place binned', 'bad') ?><?php endif; ?>
            <span style="display:flex;gap:6px;flex-wrap:wrap;margin-block-start:6px">
              <a class="ad-btn ghost" href="<?= e(admin_url('media.php', ['edit' => (int) $im['id']])) ?>">Edit</a>
              <?php if (!(int) $im['is_cover']): ?>
                <form method="post" action="<?= e(admin_url('media.php')) ?>" class="ad-inline">
                  <?= csrf_field() ?>
                  <input type="hidden" name="do" value="cover">
                  <input type="hidden" name="id" value="<?= (int) $im['id'] ?>">
                  <input type="hidden" name="place" value="<?= (int) $im['place_id'] ?>">
                  <button class="ad-btn ghost" type="submit">Make cover</button>
                </form>
              <?php endif; ?>
              <form method="post" action="<?= e(admin_url('media.php')) ?>" class="ad-inline">
                <?= csrf_field() ?>
                <input type="hidden" name="do" value="delete">
                <input type="hidden" name="id" value="<?= (int) $im['id'] ?>">
                <input type="hidden" name="place" value="<?= (int) $im['place_id'] ?>">
                <button class="ad-btn danger" type="submit"
                        data-ad-confirm="Delete this image? The file is removed from disk and this cannot be undone.">Delete</button>
              </form>
            </span>
          </figcaption>
        </figure>
        <?php
    }
    echo '</div>';

    ad_pager($total, $perPage, $page, array_filter([
        'q' => $q, 'place' => $place ?: '', 'used' => $used,
    ]));
}

ad_panel_close();

admin_foot();
