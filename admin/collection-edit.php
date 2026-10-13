<?php
/**
 * Which places are in one collection — and in what order.
 *
 * Collections.php owns the fields (title, blurb, kind, status); this screen owns
 * the membership. Two tables are involved and neither is a column on
 * collections, which is exactly why it is a screen of its own.
 *
 * The order rule is the same one itinerary-edit.php uses, for the same reason:
 * rows are moved in the DOM and the form posts its inputs in DOM order, so the
 * sequence that arrives IS the new order and sort_order is renumbered from it.
 * No "sort_order" field is ever posted.
 *
 * And, as there: the row buttons save the whole board first, so pressing ▲ on
 * one row cannot silently discard a note typed on another.
 *
 * Honest about the same gap: nothing public reads collection_places yet. The
 * first row of the home page is not wired to the "featured" collection, and this
 * page says so rather than implying the work is live.
 */

declare(strict_types=1);

require_once __DIR__ . '/inc/panel.php';

require_admin('collection.edit');
admin_require_csrf();

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/**
 * The minimum shape record_soft_delete()/record_restore() need for this table.
 * The full field spec lives on collections.php, where the columns are edited.
 */
function col_def(): array
{
    return ['table' => 'collections', 'module' => 'collection', 'entity' => 'collection'];
}

/** Every place in the collection, in the order the site would show them. */
function col_places(int $collectionId): array
{
    try {
        return qAll(
            'SELECT cp.place_id, cp.sort_order,
                    p.name_en, p.name_ar, p.slug, p.status, p.is_featured,
                    p.duration_mins, p.deleted_at,
                    c.name_en AS cat_name,
                    m.name_en AS mun_name
               FROM collection_places cp
               JOIN places p             ON p.id = cp.place_id
               LEFT JOIN categories c    ON c.id = p.category_id
               LEFT JOIN municipalities m ON m.id = p.municipality_id
              WHERE cp.collection_id = ?
              ORDER BY cp.sort_order, cp.place_id',
            [$collectionId]
        );
    } catch (Throwable $e) {
        error_log('col_places: ' . $e->getMessage());
        return [];
    }
}

/**
 * Renumber sort_order from a list of place ids in display order.
 *
 * Steps of 10 so a place can be slotted between two others later without
 * renumbering the row above it. Nothing depends on the gaps.
 */
function col_write_order(int $collectionId, array $idsInOrder): void
{
    $n = 0;
    try {
        foreach ($idsInOrder as $raw) {
            $pid = (int) $raw;
            if ($pid <= 0) {
                continue;
            }
            $n += 10;
            // Scoped by collection_id: a place id that is not in this collection
            // simply matches no row, so a doctored form cannot renumber another
            // collection's list.
            q('UPDATE collection_places SET sort_order = ?
                WHERE collection_id = ? AND place_id = ?',
              [$n, $collectionId, $pid]);
        }
    } catch (Throwable $e) {
        error_log('col_write_order: ' . $e->getMessage());
    }
}

/** Move one place one position. Returns false at either end. */
function col_move(int $collectionId, int $placeId, int $delta): bool
{
    $rows = col_places($collectionId);
    $ids  = array_map(static fn(array $r): int => (int) $r['place_id'], $rows);
    $pos  = array_search($placeId, $ids, true);

    if ($pos === false) {
        return false;
    }
    $swap = $pos + $delta;
    if (!isset($ids[$swap])) {
        return false;
    }

    [$ids[$pos], $ids[$swap]] = [$ids[$swap], $ids[$pos]];
    col_write_order($collectionId, $ids);

    log_activity('collection', 'reorder', 'collection', $collectionId, null, ['place_id' => $placeId]);
    return true;
}

/**
 * Places that can still be added, as select options.
 *
 * Already-present places are left out rather than shown and rejected: the join
 * table has a composite primary key, so picking one again is not an error the
 * editor needs to meet — it is a choice that should not be on offer.
 *
 * @param array<int, int> $already Place ids in the collection.
 */
function col_add_options(array $already): array
{
    $taken = array_fill_keys($already, true);
    $out   = ['' => '— choose a place —'];

    try {
        foreach (qAll(
            'SELECT p.id, p.name_en, p.status, m.name_en AS mun_name
               FROM places p
               LEFT JOIN municipalities m ON m.id = p.municipality_id
              WHERE p.deleted_at IS NULL
              ORDER BY p.name_en'
        ) as $r) {
            $id = (int) $r['id'];
            if (isset($taken[$id])) {
                continue;
            }
            $label = (string) $r['name_en'];
            if ((string) ($r['mun_name'] ?? '') !== '') {
                $label .= ' · ' . (string) $r['mun_name'];
            }
            if ((string) $r['status'] !== 'published') {
                $label .= '  (' . (string) $r['status'] . ')';
            }
            $out[$id] = $label;
        }
    } catch (Throwable $e) {
        error_log('col_add_options: ' . $e->getMessage());
    }

    return $out;
}

/** The posted membership order, filtered to places that are actually in it. */
function col_posted_order(int $collectionId): array
{
    $members = [];
    foreach (col_places($collectionId) as $r) {
        $members[(int) $r['place_id']] = true;
    }

    $posted = $_POST['place_id'] ?? [];
    $order  = [];

    if (is_array($posted)) {
        foreach ($posted as $raw) {
            if (!is_numeric($raw)) {
                continue;
            }
            $pid = (int) $raw;
            if (isset($members[$pid])) {
                $order[] = $pid;
            }
        }
    }

    // A member the form did not mention keeps a place at the end rather than
    // being stranded on a sort_order the renumber never reached.
    foreach (array_keys($members) as $pid) {
        if (!in_array($pid, $order, true)) {
            $order[] = $pid;
        }
    }

    return $order;
}

// ---------------------------------------------------------------------------
// Which collection
// ---------------------------------------------------------------------------

$id  = $_SERVER['REQUEST_METHOD'] === 'POST' ? post_int('id') : get_int('id');
$col = $id > 0 ? record_by_id('collections', $id) : null;

if ($id > 0 && $col === null) {
    admin_head(['title' => 'Not found', 'nav' => 'colls']);
    ad_title('That collection no longer exists');
    ad_panel_open();
    ad_empty('It may have been deleted. Check the bin on the Collections screen.',
             '<a class="ad-btn" href="' . e(admin_url('collections.php', ['bin' => 1])) . '">Open the bin</a>');
    ad_panel_close();
    admin_foot();
    exit;
}

if ($col === null) {
    admin_back(admin_url('collections.php'), 'Pick a collection to edit what is in it.', 'bad');
}

// ------------------------------------------------------------------- actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $back = admin_url('collection-edit.php', ['id' => $id]);

    if (post_str('do') === 'restore') {
        $ok = record_restore(col_def(), $id);
        admin_back($back, $ok ? 'Restored. The places are still in it.' : 'Could not restore that collection.',
                   $ok ? 'ok' : 'bad');
    }

    if (!empty($col['deleted_at'])) {
        admin_back(admin_url('collections.php', ['bin' => 1]),
            'That collection is in the bin. Restore it before editing what is in it.', 'bad');
    }

    $do = post_str('do');

    if ($do === 'bin') {
        $ok = record_soft_delete(col_def(), $id);
        admin_back(admin_url('collections.php'),
            $ok ? 'Moved to the bin, with its list of places. Restore it from the bin if that was a mistake.'
                : 'Could not move that to the bin.',
            $ok ? 'ok' : 'bad');
    }

    if ($do === 'add') {
        $pid = post_int('place_id');

        // A place that does not exist would be refused by the foreign key as a
        // raw database error; checking turns that into a sentence. Requiring
        // deleted_at IS NULL also means a binned place cannot be added — it
        // would be invisible on the site and look like a bug in the ordering.
        $exists = $pid > 0
            ? qVal('SELECT id FROM places WHERE id = ? AND deleted_at IS NULL', [$pid])
            : null;

        if ($exists === null) {
            admin_back($back, 'Pick a place from the list.', 'bad');
        }

        $already = (int) qVal(
            'SELECT COUNT(*) FROM collection_places WHERE collection_id = ? AND place_id = ?',
            [$id, $pid]
        );
        if ($already > 0) {
            admin_back($back, 'That place is already in this collection.', 'warn');
        }

        $next = (int) qVal(
            'SELECT COALESCE(MAX(sort_order), 0) FROM collection_places WHERE collection_id = ?',
            [$id]
        ) + 10;

        try {
            q('INSERT INTO collection_places (collection_id, place_id, sort_order) VALUES (?, ?, ?)',
              [$id, $pid, $next]);
        } catch (Throwable $e) {
            error_log('collection add: ' . $e->getMessage());
            admin_back($back, 'The database refused that place.', 'bad');
        }

        log_activity('collection', 'place_add', 'collection', $id, null, ['place_id' => $pid]);
        admin_back($back, 'Added.');
    }

    // A row button carries "action:id" in one value: one submit button can only
    // contribute a single name=value pair.
    $rowAction = null;
    $rowPlace  = 0;
    if (preg_match('/^(up|down|remove):(\d+)$/', $do, $m)) {
        $rowAction = $m[1];
        $rowPlace  = (int) $m[2];
    }

    if ($do === 'save' || $rowAction !== null) {
        $order = col_posted_order($id);

        if ($rowAction === 'remove') {
            $n = q('DELETE FROM collection_places WHERE collection_id = ? AND place_id = ?',
                   [$id, $rowPlace])->rowCount();
            log_activity('collection', 'place_remove', 'collection', $id, ['place_id' => $rowPlace], null);
            admin_back($back, $n > 0
                ? 'Removed from this collection. The place itself is untouched.'
                : 'That place was not in this collection.',
                $n > 0 ? 'ok' : 'bad');
        }

        col_write_order($id, $order);

        if ($rowAction !== null) {
            $moved = col_move($id, $rowPlace, $rowAction === 'up' ? -1 : 1);
            admin_back($back, $moved ? 'Moved.' : 'That place is already at the end of the list.');
        }

        log_activity('collection', 'reorder_save', 'collection', $id, null, ['n' => count($order)]);
        admin_back($back, 'The order is saved.');
    }

    // A POST that named no action we know is a stale or hand-made form: fall
    // through and render rather than pretending something was saved.
}

// -------------------------------------------------------------------- reads
$rows   = col_places($id);
$ids    = array_map(static fn(array $r): int => (int) $r['place_id'], $rows);
$total  = count($rows);
$addOpt = col_add_options($ids);

$unpublished = 0;
$binned      = 0;
foreach ($rows as $r) {
    if ($r['deleted_at'] !== null) {
        $binned++;
    } elseif ((string) $r['status'] !== 'published') {
        $unpublished++;
    }
}

// ------------------------------------------------------------------- render
admin_head(['title' => 'Places · ' . (string) $col['title_en'], 'nav' => 'colls']);

ad_title(
    'What is in it',
    (string) $col['title_en'] . ' — ' . (string) $col['kind'] . ' · ' . (string) $col['status'],
    [
        ['href' => admin_url('collections.php', ['id' => $id]), 'label' => 'Collection details', 'kind' => 'ghost'],
        ['href' => admin_url('collections.php'), 'label' => 'All collections', 'kind' => 'ghost'],
    ]
);

if (!empty($col['deleted_at'])) {
    ad_note('This collection is in the bin. Its list of places is still stored, but nothing here is editable until it is restored.', 'warn');
    ad_panel_open();
    ?>
    <form class="ad-form" method="post" action="<?= e(admin_url('collection-edit.php', ['id' => $id])) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="<?= (int) $id ?>">
      <input type="hidden" name="do" value="restore">
      <div class="ad-form-act">
        <button class="ad-btn primary" type="submit">Restore this collection</button>
        <a class="ad-btn ghost" href="<?= e(admin_url('collections.php', ['bin' => 1])) ?>">Back to the bin</a>
      </div>
    </form>
    <?php
    ad_panel_close();
    admin_foot();
    exit;
}

if ((string) $col['kind'] === 'featured') {
    ad_note(
        'This is a <b>featured</b> collection — the kind meant for the row at the top of the home page. '
        . 'The home page does not read collections yet, so the order below is not what a visitor sees; it is '
        . 'what they will see once that row is wired up.',
        'info'
    );
} else {
    ad_note(
        'Nothing on the public site reads a collection yet. The list below is stored and shown here, but no '
        . 'page renders it — so treat this as the editorial work being ready ahead of the screen that uses it.',
        'info'
    );
}

if ($binned > 0) {
    ad_note(
        $binned . ' place' . ($binned === 1 ? ' is' : 's are') . ' in the bin. '
        . 'It stays in this collection so the order survives a restore, but the public list would skip it — '
        . 'worth removing if the place is not coming back.',
        'bad'
    );
} elseif ($unpublished > 0) {
    ad_note(
        $unpublished . ' place' . ($unpublished === 1 ? ' is' : 's are') . ' not published yet. '
        . 'They are skipped on the site, so a collection of five drafts would show as empty.',
        'warn'
    );
}
?>

<div class="ad-panel">
  <h2>In this collection <span class="ad-chip mute"><?= $total ?></span></h2>
  <p class="ad-help" style="margin-block-start:0">
    Drag a row by its handle to reorder it, or use ▲ ▼. Nothing is stored until you save — the buttons on
    the rows save the order first, so a move never loses an earlier one.
  </p>

  <?php if ($rows === []): ?>
    <?php ad_empty('Empty. A collection nobody has filled in is a heading with nothing under it.'); ?>
  <?php else: ?>
    <form class="ad-form" method="post" action="<?= e(admin_url('collection-edit.php', ['id' => $id])) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="<?= (int) $id ?>">

      <div class="ad-tablewrap">
        <table class="ad-table ad-stops" data-stops>
          <thead>
            <tr>
              <th class="ad-stophandle"><span class="ad-sr">Move</span></th>
              <th>Place</th>
              <th class="ad-check"><span class="ad-sr">Remove</span></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $r): ?>
              <?php
              $pid    = (int) $r['place_id'];
              $gone   = $r['deleted_at'] !== null;
              $unpub  = !$gone && (string) $r['status'] !== 'published';
              ?>
              <tr data-stop="<?= $pid ?>">
                <td class="ad-stophandle">
                  <span class="ad-grip" draggable="true" title="Drag to reorder">⠿</span>
                  <button class="ad-btn ghost ad-tiny" type="submit" name="do" value="up:<?= $pid ?>"
                          formnovalidate title="Move up">▲</button>
                  <button class="ad-btn ghost ad-tiny" type="submit" name="do" value="down:<?= $pid ?>"
                          formnovalidate title="Move down">▼</button>
                </td>

                <td>
                  <input type="hidden" name="place_id[]" value="<?= $pid ?>">
                  <a class="ad-rowlink" href="<?= e(admin_url('place-edit.php', ['id' => $pid])) ?>">
                    <?= e((string) $r['name_en']) ?>
                  </a>
                  <span class="ad-sub">
                    <?= e((string) ($r['cat_name'] ?? '')) ?>
                    <?php if ((string) ($r['mun_name'] ?? '') !== ''): ?>
                      · <?= e((string) $r['mun_name']) ?>
                    <?php endif; ?>
                  </span>
                  <?php if ($gone): ?>
                    <?= ad_chip('place is in the bin', 'bad') ?>
                  <?php elseif ($unpub): ?>
                    <?= ad_chip('place is ' . (string) $r['status'], 'warn') ?>
                  <?php endif; ?>
                  <?php if ((int) $r['is_featured']): ?>
                    <?= ad_chip('featured', 'info') ?>
                  <?php endif; ?>
                </td>

                <td class="ad-check">
                  <button class="ad-btn ghost danger" type="submit" name="do" value="remove:<?= $pid ?>"
                          formnovalidate
                          data-ad-confirm="Take this place out of the collection? The place itself is not touched."
                          title="Remove from this collection">✕</button>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <div class="ad-form-act">
        <button class="ad-btn primary" type="submit" name="do" value="save">Save the order</button>
        <span class="ad-muted"><?= $total ?> place<?= $total === 1 ? '' : 's' ?>.</span>
      </div>
    </form>
  <?php endif; ?>
</div>

<div class="ad-panel">
  <h2>Add a place</h2>
  <?php if (count($addOpt) <= 1): ?>
    <?php ad_empty('Every place that is not in the bin is already in this collection.'); ?>
  <?php else: ?>
    <p class="ad-help" style="margin-block-start:0">
      Places already in the collection are not on this list. Draft and archived places are marked — they can
      be added, but they will not show on the site until they are published.
    </p>
    <form class="ad-form" method="post" action="<?= e(admin_url('collection-edit.php', ['id' => $id])) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="<?= (int) $id ?>">
      <input type="hidden" name="do" value="add">
      <div class="ad-grid">
        <?php
        ad_field([
            'k' => 'place_id', 't' => 'Place', 'type' => 'select', 'wide' => true,
            'options' => $addOpt,
            'help' => 'Added to the end of the list. Drag it up once it is in.',
        ], '');
        ?>
      </div>
      <div class="ad-form-act">
        <button class="ad-btn primary" type="submit">Add it</button>
      </div>
    </form>
  <?php endif; ?>
</div>

<div class="ad-panel">
  <h2>This collection</h2>
  <dl class="ad-kv">
    <dt>Kind</dt><dd><?= e((string) $col['kind']) ?></dd>
    <dt>Status</dt><dd><?= ad_status_chip((string) $col['status']) ?></dd>
    <dt>Slug</dt><dd class="mono"><?= e((string) $col['slug']) ?></dd>
    <dt>Blurb</dt><dd><?= (string) $col['blurb_en'] === '' ? '<span class="ad-muted">none</span>' : e((string) $col['blurb_en']) ?></dd>
  </dl>

  <p class="ad-help" style="margin-block-start:12px">
    The collections table has no <code>verified_at</code> column, so unlike a place or an itinerary there is
    nothing here to mark as checked. The dashboard's staleness list therefore cannot see a collection.
  </p>

  <p style="margin-block:16px 0">
    <a class="ad-btn" href="<?= e(admin_url('collections.php', ['id' => $id])) ?>">Edit the title and blurb</a>
  </p>

  <form method="post" action="<?= e(admin_url('collection-edit.php', ['id' => $id])) ?>" style="margin-block-start:16px">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int) $id ?>">
    <input type="hidden" name="do" value="bin">
    <button class="ad-btn danger" type="submit"
            data-ad-confirm="Move this collection to the bin? Its list of places goes with it and it can be restored.">
      Move to the bin
    </button>
    <span class="ad-muted">Soft delete — the bin keeps it, and the places themselves are untouched.</span>
  </form>
</div>

<?php
admin_foot(['scripts' => '<script src="' . e(admin_asset('js/admin-stops.js')) . '" defer></script>']);
