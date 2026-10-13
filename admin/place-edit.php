<?php
/**
 * Add or edit one place.
 *
 * The biggest screen in the panel, because a place is the biggest record: prose
 * in two languages, practical details, five flags, a position on the map, tags,
 * and a set of images.
 *
 * It still writes through record_save(). The only thing it does by hand is the
 * tag list, which is a many-to-many and so is not a column on the row.
 */

declare(strict_types=1);

require_once __DIR__ . '/inc/panel.php';
require_once __DIR__ . '/inc/place-def.php';

require_admin('content.edit');

$def = place_def();
admin_require_csrf();

$id = $_SERVER['REQUEST_METHOD'] === 'POST' ? (post_int('id') ?: null) : (get_int('id') ?: null);
$errors = [];
$saved  = false;

/** Replace a place's tags with exactly the posted set. */
function place_save_tags(int $placeId, array $tagIds): void
{
    try {
        q('DELETE FROM place_tag WHERE place_id = ?', [$placeId]);
        $seen = [];
        foreach ($tagIds as $t) {
            if (!is_scalar($t) || !is_numeric($t)) { continue; }
            $tid = (int) $t;
            if ($tid <= 0 || isset($seen[$tid])) { continue; }
            $seen[$tid] = true;
            q('INSERT IGNORE INTO place_tag (place_id, tag_id) VALUES (?, ?)', [$placeId, $tid]);
        }
    } catch (Throwable $e) {
        error_log('place_save_tags: ' . $e->getMessage());
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // The pin picker writes these two, so they are always present; the number
    // fields that can legitimately be blank arrive as ''.
    $res = record_save($def, $_POST, $id);

    if ($res['ok']) {
        place_save_tags((int) $res['id'], (array) ($_POST['tags'] ?? []));
        admin_back(
            admin_url('place-edit.php', ['id' => (int) $res['id']]),
            'Saved. ' . ((string) ($_POST['status'] ?? '') === 'published'
                ? 'This place is live.'
                : 'Still a draft — publish it when the details are checked.')
        );
    }

    $errors = $res['errors'];
    $id     = (int) $res['id'] ?: $id;
}

// --------------------------------------------------------------- the record
$place = $id !== null ? record_by_id('places', $id) : null;

if ($id !== null && $place === null) {
    admin_head(['title' => 'Not found', 'nav' => 'places']);
    ad_title('That place no longer exists');
    ad_panel_open();
    ad_empty('It may have been deleted. Check the bin on the Places screen.',
             '<a class="ad-btn" href="' . e(admin_url('places.php', ['bin' => 1])) . '">Open the bin</a>');
    ad_panel_close();
    admin_foot();
    exit;
}

// On a failed save the form must show what was typed, not what is stored.
$values = $place ?? [
    'status' => 'draft', 'fee_level' => 'free', 'season' => 'all',
    'family_friendly' => 1, 'is_hidden_gem' => 0, 'is_featured' => 0,
    'lat' => '', 'lng' => '',
];
if ($errors !== []) {
    foreach ($_POST as $k => $v) {
        if (is_string($v)) { $values[$k] = $v; }
    }
}

$isNew = $place === null;

[$muns, $locs] = place_location_options();
$cats = place_category_options();

// Existing tags, and the full tag list to choose from.
$myTags = [];
$allTags = [];
try {
    if (!$isNew) {
        foreach (qAll('SELECT tag_id FROM place_tag WHERE place_id = ?', [(int) $place['id']]) as $r) {
            $myTags[(int) $r['tag_id']] = true;
        }
    }
    $allTags = qAll('SELECT id, name_en FROM tags ORDER BY name_en');
} catch (Throwable $e) {
    error_log('place-edit tags: ' . $e->getMessage());
}
// A failed save re-reads them from the form instead.
if ($errors !== []) {
    $myTags = [];
    foreach ((array) ($_POST['tags'] ?? []) as $t) {
        if (is_numeric($t)) { $myTags[(int) $t] = true; }
    }
}

$images = [];
if (!$isNew) {
    try {
        $images = qAll(
            'SELECT * FROM place_images WHERE place_id = ? ORDER BY is_cover DESC, sort_order, id',
            [(int) $place['id']]
        );
    } catch (Throwable $e) {
        error_log('place-edit images: ' . $e->getMessage());
    }
}

// ------------------------------------------------------------------- render
admin_head(['title' => $isNew ? 'New place' : (string) $values['name_en'], 'nav' => 'places']);

ad_title(
    $isNew ? 'Add a place' : 'Edit place',
    $isNew ? 'It starts as a draft, so nothing is public until you say so.' : (string) $values['name_en'],
    array_filter([
        !$isNew ? ['href' => APP_BASE . '/place.php?p=' . urlencode((string) $values['slug']),
                   'label' => 'View on site ↗', 'kind' => 'ghost'] : null,
        ['href' => admin_url('places.php'), 'label' => 'All places', 'kind' => 'ghost'],
    ])
);

if ($errors !== []) {
    if (isset($errors['_form'])) {
        ad_note(e($errors['_form']), 'bad');
    } else {
        ad_note('Nothing was saved. The fields below are the ones that need attention.', 'bad');
    }
}

/** Validation types do not always match how a value should be entered. */
function edit_spec(array $f, array $muns, array $locs, array $cats): array
{
    // Note the explicit assignment rather than $f + [...]: array union keeps the
    // left-hand value, so `$f + ['type' => 'select']` would silently leave the
    // validation type in place and render a plain text box.
    if ($f['k'] === 'municipality_id') {
        $f['type']    = 'select';
        $f['options'] = ['' => '— choose —'] + $muns;
        return $f;
    }
    if ($f['k'] === 'locality_id') {
        $f['type']    = 'select';
        $f['options'] = ['' => '— not on a map dot —'] + $locs;
        return $f;
    }
    if ($f['k'] === 'category_id') {
        $f['type']    = 'select';
        $f['options'] = ['' => '— none —'] + $cats;
        return $f;
    }
    if (($f['type'] ?? '') === 'decimal') {
        // record_save() validates it as a decimal; the browser wants a number
        // box, which is a different thing.
        $f['type'] = 'number';
        $f['step'] = '0.000001';
        return $f;
    }
    return $f;
}

/** Print the fields named, in the order given, skipping any that are missing. */
function edit_fields(array $fields, array $keys, array $values, array $errors, array $muns, array $locs, array $cats): void
{
    $byKey = [];
    foreach ($fields as $f) { $byKey[$f['k']] = $f; }

    foreach ($keys as $k) {
        if (!isset($byKey[$k])) { continue; }
        $f = edit_spec($byKey[$k], $muns, $locs, $cats);
        $v = $values[$k] ?? '';
        if (isset($errors[$k])) {
            echo '<div class="ad-field wide"><span class="ad-err">' . e($f['t'] . ': ' . $errors[$k]) . '</span></div>';
        }
        ad_field($f, $v);
    }
}

function edit_section(string $title, string $help = ''): void
{
    echo '<div class="ad-field wide" style="margin-block-start:10px">'
       . '<h2 style="font-size:1rem;margin:0">' . e($title) . '</h2>'
       . ($help !== '' ? '<span class="ad-help">' . e($help) . '</span>' : '')
       . '</div>';
}

ad_panel_open();
ad_form_open(
    admin_url('place-edit.php'),
    $isNew ? 'New place' : 'Place details',
    'Arabic is required: the guide is bilingual and a place without it disappears from the Arabic site.'
);
echo '<input type="hidden" name="id" value="' . (int) ($place['id'] ?? 0) . '">';

// No extra wrapper: ad_form_open() has already opened .ad-grid, and nesting a
// second one would shift every field onto its own row.
edit_section('Basics');
edit_fields($def['fields'], ['name_en', 'slug', 'name_ar', 'status', 'sort_order'], $values, $errors, $muns, $locs, $cats);

edit_section('Where it is', 'Click the map to drop the pin. The coordinates fill themselves in.');
edit_fields($def['fields'], ['municipality_id', 'locality_id', 'category_id'], $values, $errors, $muns, $locs, $cats);
?>

  <div class="ad-field wide">
    <div class="ad-pin" data-pin-map></div>
    <span class="ad-help" data-pin-status>Loading the map…</span>
  </div>

  <?php
  edit_fields($def['fields'], ['lat', 'lng'], $values, $errors, $muns, $locs, $cats);

  edit_section('What it is', 'The summary shows on the guide card on its own, so it has to make sense without the description.');
  edit_fields($def['fields'], ['summary_en', 'summary_ar', 'description_en', 'description_ar', 'tips_en', 'tips_ar'], $values, $errors, $muns, $locs, $cats);

  edit_section('Practical details', 'Bands rather than exact prices. A price that was right last year is worse than an honest range.');
  edit_fields($def['fields'], ['fee_level', 'fee_note_en', 'fee_note_ar', 'duration_mins', 'best_time_en', 'best_time_ar'], $values, $errors, $muns, $locs, $cats);

  edit_section('Filters');
  edit_fields($def['fields'], ['family_friendly', 'metro_access', 'indoor', 'season', 'is_hidden_gem', 'is_featured'], $values, $errors, $muns, $locs, $cats);

  edit_section('Links');
  edit_fields($def['fields'], ['map_url', 'official_url'], $values, $errors, $muns, $locs, $cats);

  edit_section('Checking', 'The dashboard’s “needs a look” list is driven by these two fields.');
  edit_fields($def['fields'], ['source_note', 'verified_at'], $values, $errors, $muns, $locs, $cats);
  ?>

  <div class="ad-field wide">
    <label>Tags</label>
    <?php if ($allTags === []): ?>
      <span class="ad-help">
        No tags yet. <a href="<?= e(admin_url('tags.php', ['new' => 1])) ?>">Add some</a> first.
      </span>
    <?php else: ?>
      <div style="display:flex;flex-wrap:wrap;gap:10px">
        <?php foreach ($allTags as $t): ?>
          <label class="ad-switch">
            <input type="checkbox" name="tags[]" value="<?= (int) $t['id'] ?>"
              <?= isset($myTags[(int) $t['id']]) ? 'checked' : '' ?>>
            <span><?= e((string) $t['name_en']) ?></span>
          </label>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

<?php
ad_form_close(
    $isNew ? 'Create the place' : 'Save changes',
    admin_url('places.php'),
    $isNew ? '' : ((string) $values['status'] === 'published' ? 'Unpublish and bin' : 'Move to bin')
);
ad_panel_close();

// ------------------------------------------------------------------ images
if (!$isNew) {
    ad_panel_open();
    ?>
    <h2>Images <span class="ad-chip mute"><?= count($images) ?></span></h2>
    <p class="ad-help">
      One image is marked as the cover — that is what shows on the guide card and
      in share previews. Everything else appears in the gallery on the place page.
    </p>
    <?php if ($images === []): ?>
      <?php ad_empty('No images yet. A place with no picture is a place nobody clicks.'); ?>
    <?php else: ?>
      <div class="ad-media">
        <?php foreach ($images as $im): ?>
          <figure>
            <img src="<?= e(APP_BASE . '/uploads/' . ltrim((string) $im['path'], '/')) ?>" alt="" loading="lazy">
            <figcaption>
              <b><?= e((string) ($im['caption_en'] !== '' ? $im['caption_en'] : basename((string) $im['path']))) ?></b>
              <?php if ((int) $im['is_cover']): ?>
                <?= ad_chip('cover', 'ok') ?>
              <?php else: ?>
                <form method="post" action="<?= e(admin_url('media.php')) ?>" class="ad-inline">
                  <?= csrf_field() ?>
                  <input type="hidden" name="do" value="cover">
                  <input type="hidden" name="id" value="<?= (int) $im['id'] ?>">
                  <input type="hidden" name="place" value="<?= (int) $place['id'] ?>">
                  <button class="ad-btn ghost" type="submit"
                          data-ad-confirm="Use this as the cover image for this place?">Make cover</button>
                </form>
              <?php endif; ?>
              · <a href="<?= e(admin_url('media.php', ['edit' => (int) $im['id']])) ?>">Edit</a>
            </figcaption>
          </figure>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
    <p style="margin-block:16px 0">
      <a class="ad-btn primary" href="<?= e(admin_url('media.php', ['place' => (int) $place['id']])) ?>">Upload an image</a>
      <a class="ad-btn ghost" href="<?= e(admin_url('media.php')) ?>">Image library</a>
    </p>
    <?php
    ad_panel_close();
}

admin_foot(['scripts' => '<script src="' . e(admin_asset('js/admin-pin.js')) . '" defer></script>']);
