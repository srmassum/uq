<?php
/**
 * One itinerary's stops — the route itself.
 *
 * The trip's own columns (title, length, audience, budget, status) are edited on
 * itineraries.php, because those are a row of fields. A stop list is a different
 * kind of editing: it is ordered, it is grouped by day, and every row carries its
 * own time notes. So it gets its own screen.
 *
 * Two things about the order, both deliberate:
 *
 *  1. The order is never a number the browser sends. Rows are moved in the DOM
 *     and the form posts its inputs in DOM order, so the sequence that arrives
 *     IS the new order — and sort_order is renumbered from that sequence here.
 *     A hidden "sort_order" field would be a number a submitter could set.
 *
 *  2. The row buttons (▲ ▼ ✕) save the whole board first. A button can only
 *     contribute one name=value pair, so it cannot carry both "move row 12" and
 *     the text typed into the other five rows; without the save-first step,
 *     pressing ▲ would quietly throw away a time note someone had just written.
 *
 * Written to be honest about one thing: nothing public reads itinerary_stops
 * yet. The panel says so rather than letting an editor assume the route is live.
 */

declare(strict_types=1);

require_once __DIR__ . '/inc/panel.php';

require_admin('itinerary.edit');
admin_require_csrf();

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/**
 * The minimum shape record_soft_delete()/record_restore() need for this table.
 *
 * Both go through the generic functions in records.php rather than a hand-written
 * UPDATE, so the bin, the audit entry and the deleted_at handling stay in one
 * place. The full field spec lives on itineraries.php, where the columns are
 * actually edited.
 */
function itin_def(): array
{
    return ['table' => 'itineraries', 'module' => 'itinerary', 'entity' => 'itinerary'];
}

/** "Day 2" for a trip, "Leg 2" for a stopover: one column, two readings. */
function itin_unit(array $itin): string
{
    return ((string) ($itin['duration_type'] ?? 'days')) === 'hours' ? 'Leg' : 'Day';
}

/**
 * The highest day/leg number the day box will offer.
 *
 * For a trip that is simply how many days it lasts. For an hours-long stopover
 * it is not: eight hours is one afternoon, and how many legs that is broken into
 * is an editorial decision, not a number the trip already carries. So an hours
 * trip gets the current maximum plus room to grow.
 *
 * @param int $currentMax The highest day number already used, so nothing that
 *                        exists is ever hidden from the control.
 */
function itin_max_day(array $itin, int $currentMax = 0): int
{
    $isDays = ((string) ($itin['duration_type'] ?? 'days')) === 'days';
    $cap    = $isDays ? max(1, (int) ($itin['duration_value'] ?? 1)) : 6;

    return max(1, min(30, max($cap, $currentMax)));
}

/** Every stop on the itinerary, in the order the page shows them. */
function itin_stops(int $itineraryId): array
{
    try {
        return qAll(
            'SELECT s.id, s.place_id, s.day_number, s.sort_order,
                    s.time_note_en, s.time_note_ar, s.note_en, s.note_ar,
                    p.name_en AS place_name, p.slug AS place_slug, p.status AS place_status,
                    p.duration_mins, p.deleted_at AS place_deleted,
                    m.name_en AS mun_name
               FROM itinerary_stops s
               LEFT JOIN places p        ON p.id = s.place_id
               LEFT JOIN municipalities m ON m.id = p.municipality_id
              WHERE s.itinerary_id = ?
              ORDER BY s.day_number, s.sort_order, s.id',
            [$itineraryId]
        );
    } catch (Throwable $e) {
        error_log('itin_stops: ' . $e->getMessage());
        return [];
    }
}

/** The highest day number in use, from rows already read. */
function itin_highest_day(array $rows): int
{
    $max = 0;
    foreach ($rows as $r) {
        $max = max($max, (int) $r['day_number']);
    }
    return $max;
}

/** 95 → "1h 35m". Empty string for nothing, so it can be concatenated. */
function itin_mins(int $mins): string
{
    if ($mins <= 0) {
        return '';
    }
    $h = intdiv($mins, 60);
    $m = $mins % 60;
    return trim(($h > 0 ? $h . 'h ' : '') . ($m > 0 ? $m . 'm' : ''));
}

/** Trim and cap to the column's width, so a long paste cannot be refused by MySQL. */
function itin_clip(mixed $raw, int $max): string
{
    return mb_substr(is_string($raw) ? trim($raw) : '', 0, $max);
}

/**
 * Renumber sort_order from a list of stop ids in display order.
 *
 * The numbers are 10, 20, 30… within each day rather than 1, 2, 3 so there is
 * room to slot a row between two others later. Nothing depends on the gaps.
 */
function itin_write_order(int $itineraryId, array $idsInOrder): void
{
    $dayOf = [];
    foreach (itin_stops($itineraryId) as $r) {
        $dayOf[(int) $r['id']] = (int) $r['day_number'];
    }

    $n = [];
    try {
        foreach ($idsInOrder as $raw) {
            $sid = (int) $raw;
            if (!isset($dayOf[$sid])) {
                continue;
            }
            $d = $dayOf[$sid];
            $n[$d] = ($n[$d] ?? 0) + 10;
            q('UPDATE itinerary_stops SET sort_order = ? WHERE id = ?', [$n[$d], $sid]);
        }
    } catch (Throwable $e) {
        error_log('itin_write_order: ' . $e->getMessage());
    }
}

/**
 * Move one stop one place within its own day.
 *
 * Confined to the day on purpose: a stop that jumped to another day when someone
 * pressed ▲ once would be a surprise, and the day box on the row is how that is
 * meant to happen.
 */
function itin_move(int $itineraryId, int $stopId, int $delta): bool
{
    $rows = itin_stops($itineraryId);
    $ids  = array_map(static fn(array $r): int => (int) $r['id'], $rows);
    $pos  = array_search($stopId, $ids, true);

    if ($pos === false) {
        return false;
    }

    $swap = $pos + $delta;
    if (!isset($ids[$swap]) || (int) $rows[$swap]['day_number'] !== (int) $rows[$pos]['day_number']) {
        return false;
    }

    [$ids[$pos], $ids[$swap]] = [$ids[$swap], $ids[$pos]];
    itin_write_order($itineraryId, $ids);

    log_activity('itinerary', 'stop_reorder', 'itinerary', $itineraryId, null, ['stop_id' => $stopId]);
    return true;
}

/** Reordering or renaming stops is a change to the trip, so the list's "changed" column moves. */
function itin_touch(int $itineraryId): void
{
    try {
        q('UPDATE itineraries SET updated_at = NOW() WHERE id = ?', [$itineraryId]);
    } catch (Throwable $e) {
        error_log('itin_touch: ' . $e->getMessage());
    }
}

/** Every place a stop can point at. Unpublished ones are marked rather than hidden. */
function itin_place_options(): array
{
    $out = [0 => '— not a listed place (free text) —'];
    try {
        foreach (qAll(
            'SELECT p.id, p.name_en, p.status, m.name_en AS mun_name
               FROM places p
               LEFT JOIN municipalities m ON m.id = p.municipality_id
              WHERE p.deleted_at IS NULL
              ORDER BY p.name_en'
        ) as $r) {
            $label = (string) $r['name_en'];
            if ((string) ($r['mun_name'] ?? '') !== '') {
                $label .= ' · ' . (string) $r['mun_name'];
            }
            if ((string) $r['status'] !== 'published') {
                $label .= '  (' . (string) $r['status'] . ')';
            }
            $out[(int) $r['id']] = $label;
        }
    } catch (Throwable $e) {
        error_log('itin_place_options: ' . $e->getMessage());
    }
    return $out;
}

/**
 * Write every posted row back, and return the stop ids in posted order.
 *
 * Kept separate from the action handler because the row buttons need the save
 * half without the action half — see the note at the top of this file.
 *
 * @param int $changed Filled with how many rows actually differed.
 * @return array<int, int>
 */
function itin_apply_posted(int $itineraryId, array $itin, int &$changed): array
{
    $changed = 0;
    $stored  = [];
    foreach (itin_stops($itineraryId) as $r) {
        $stored[(int) $r['id']] = $r;
    }

    $ids    = $_POST['stop_id'] ?? [];
    $days   = $_POST['day']     ?? [];
    $timeEn = $_POST['time_en'] ?? [];
    $timeAr = $_POST['time_ar'] ?? [];
    $noteEn = $_POST['note_en'] ?? [];
    $noteAr = $_POST['note_ar'] ?? [];

    if (!is_array($ids))    { $ids = []; }
    if (!is_array($days))   { $days = []; }
    if (!is_array($timeEn)) { $timeEn = []; }
    if (!is_array($timeAr)) { $timeAr = []; }
    if (!is_array($noteEn)) { $noteEn = []; }
    if (!is_array($noteAr)) { $noteAr = []; }

    $maxDay = itin_max_day($itin, itin_highest_day($stored));
    $order  = [];

    foreach ($ids as $raw) {
        if (!is_numeric($raw)) {
            continue;
        }
        $sid = (int) $raw;

        // The stop has to belong to this itinerary. Without that check a hidden
        // field on a hand-made form could point at any row in the table.
        if (!isset($stored[$sid])) {
            continue;
        }

        $day = isset($days[$sid]) && is_numeric($days[$sid])
            ? max(1, min($maxDay, (int) $days[$sid]))
            : (int) $stored[$sid]['day_number'];

        $v = [
            'day_number'   => $day,
            'time_note_en' => itin_clip($timeEn[$sid] ?? null, 120),
            'time_note_ar' => itin_clip($timeAr[$sid] ?? null, 120),
            'note_en'      => itin_clip($noteEn[$sid] ?? null, 255),
            'note_ar'      => itin_clip($noteAr[$sid] ?? null, 255),
        ];

        $same = true;
        foreach ($v as $k => $val) {
            if ((string) $stored[$sid][$k] !== (string) $val) {
                $same = false;
                break;
            }
        }

        if (!$same) {
            try {
                q('UPDATE itinerary_stops
                      SET day_number = ?, time_note_en = ?, time_note_ar = ?, note_en = ?, note_ar = ?
                    WHERE id = ? AND itinerary_id = ?',
                  [...array_values($v), $sid, $itineraryId]);
                $changed++;
            } catch (Throwable $e) {
                error_log('itin_apply_posted: ' . $e->getMessage());
            }
        }

        $order[] = $sid;
    }

    // A stop the form did not mention keeps a place at the end rather than being
    // stranded on a sort_order the renumber never reached.
    foreach (array_keys($stored) as $sid) {
        if (!in_array($sid, $order, true)) {
            $order[] = $sid;
        }
    }

    return $order;
}

// ---------------------------------------------------------------------------
// Which itinerary
// ---------------------------------------------------------------------------

$id   = $_SERVER['REQUEST_METHOD'] === 'POST' ? post_int('id') : get_int('id');
$itin = $id > 0 ? record_by_id('itineraries', $id) : null;

if ($id > 0 && $itin === null) {
    admin_head(['title' => 'Not found', 'nav' => 'itin']);
    ad_title('That itinerary no longer exists');
    ad_panel_open();
    ad_empty('It may have been deleted. Check the bin on the Itineraries screen.',
             '<a class="ad-btn" href="' . e(admin_url('itineraries.php', ['bin' => 1])) . '">Open the bin</a>');
    ad_panel_close();
    admin_foot();
    exit;
}

if ($itin === null) {
    admin_back(admin_url('itineraries.php'), 'Pick an itinerary to edit its stops.', 'bad');
}

// ------------------------------------------------------------------- actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $back = admin_url('itinerary-edit.php', ['id' => $id]);

    if (post_str('do') === 'restore') {
        $ok = record_restore(itin_def(), $id);
        admin_back($back, $ok ? 'Restored. The stops are still there.' : 'Could not restore that itinerary.',
                   $ok ? 'ok' : 'bad');
    }

    // A binned trip keeps its stops, but editing them would suggest the route is
    // on its way back to the site when nobody has said that.
    if (!empty($itin['deleted_at'])) {
        admin_back(admin_url('itineraries.php', ['bin' => 1]),
            'That itinerary is in the bin. Restore it before editing its stops.', 'bad');
    }

    $do = post_str('do');

    if ($do === 'bin') {
        $ok = record_soft_delete(itin_def(), $id);
        admin_back(admin_url('itineraries.php'),
            $ok ? 'Moved to the bin, stops and all. Restore it from the bin if that was a mistake.'
                : 'Could not move that to the bin.',
            $ok ? 'ok' : 'bad');
    }

    if ($do === 'add') {
        $placeId = post_int('place_id');
        $day     = max(1, min(itin_max_day($itin, itin_highest_day(itin_stops($id))), post_int('day', 1) ?: 1));

        // A place id that does not exist would be refused by the foreign key, as
        // a raw database error on a page. Checking here turns that into a stop
        // with no place, which is a thing the editor can see and fix. The
        // deleted_at test matches the dropdown, which never offers a binned place
        // — a stop pointing at one would be invisible on the site and look like a
        // bug in the route.
        if ($placeId > 0 && qVal('SELECT id FROM places WHERE id = ? AND deleted_at IS NULL', [$placeId]) === null) {
            $placeId = 0;
        }

        $next = (int) qVal(
            'SELECT COALESCE(MAX(sort_order), 0) FROM itinerary_stops
              WHERE itinerary_id = ? AND day_number = ?',
            [$id, $day]
        ) + 10;

        try {
            q('INSERT INTO itinerary_stops
                   (itinerary_id, place_id, day_number, sort_order,
                    time_note_en, time_note_ar, note_en, note_ar)
               VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
              [
                  $id,
                  $placeId > 0 ? $placeId : null,
                  $day,
                  $next,
                  itin_clip(post_str('time_en'), 120),
                  itin_clip(post_str('time_ar'), 120),
                  itin_clip(post_str('note_en'), 255),
                  itin_clip(post_str('note_ar'), 255),
              ]);
        } catch (Throwable $e) {
            error_log('itin add: ' . $e->getMessage());
            admin_back($back, 'The database refused that stop.', 'bad');
        }

        itin_touch($id);
        log_activity('itinerary', 'stop_add', 'itinerary', $id, null, ['place_id' => $placeId ?: null]);

        admin_back($back, 'Stop added to ' . itin_unit($itin) . ' ' . $day . '.');
    }

    // A row button carries "action:id" in one value, because a submit button can
    // only contribute a single name=value pair.
    $rowAction = null;
    $rowStop   = 0;
    if (preg_match('/^(up|down|remove):(\d+)$/', $do, $m)) {
        $rowAction = $m[1];
        $rowStop   = (int) $m[2];
    }

    if ($do === 'save' || $rowAction !== null) {
        $changed = 0;
        $order   = itin_apply_posted($id, $itin, $changed);

        if ($rowAction === 'remove') {
            // Scoped by itinerary_id as well as by id, so a stop belonging to
            // another trip cannot be deleted by editing this form's hidden field.
            $n = q('DELETE FROM itinerary_stops WHERE id = ? AND itinerary_id = ?', [$rowStop, $id])->rowCount();
            itin_touch($id);
            log_activity('itinerary', 'stop_remove', 'itinerary', $id, ['stop_id' => $rowStop], null);
            admin_back($back, $n > 0 ? 'Stop removed.' : 'That stop was already gone.', $n > 0 ? 'ok' : 'bad');
        }

        itin_write_order($id, $order);

        if ($rowAction !== null) {
            $moved = itin_move($id, $rowStop, $rowAction === 'up' ? -1 : 1);
            itin_touch($id);
            admin_back($back, $moved
                ? 'Moved, and the notes on the other rows were saved too.'
                : 'That stop is already at the end of its ' . strtolower(itin_unit($itin)) . '.');
        }

        itin_touch($id);
        log_activity('itinerary', 'stops_save', 'itinerary', $id, null, ['changed' => $changed]);

        admin_back($back, $changed === 0
            ? 'Nothing had changed.'
            : $changed . ' stop' . ($changed === 1 ? '' : 's') . ' saved.');
    }

    // A POST that named no action we know is a stale or hand-made form: fall
    // through and render, rather than pretending something was saved.
}

// -------------------------------------------------------------------- reads
$rows   = itin_stops($id);
$maxDay = itin_max_day($itin, itin_highest_day($rows));
$unit   = itin_unit($itin);

$byDay = [];
foreach ($rows as $r) {
    $byDay[(int) $r['day_number']][] = $r;
}
ksort($byDay);

$places = itin_place_options();
$total  = count($rows);

// ------------------------------------------------------------------- render
admin_head(['title' => 'Stops · ' . (string) $itin['title_en'], 'nav' => 'itin']);

$binned = !empty($itin['deleted_at']);

ad_title(
    'Stops',
    (string) $itin['title_en'] . ' — ' . (int) $itin['duration_value'] . ' ' . (string) $itin['duration_type']
        . ' · ' . (string) $itin['audience'] . ' · ' . (string) $itin['budget_level'],
    array_filter([
        ['href' => admin_url('itineraries.php', ['id' => $id]), 'label' => 'Trip details', 'kind' => 'ghost'],
        ['href' => admin_url('itineraries.php'), 'label' => 'All itineraries', 'kind' => 'ghost'],
    ])
);

if ($binned) {
    ad_note('This itinerary is in the bin. Its stops are still stored, but nothing here is editable until it is restored.', 'warn');
    ad_panel_open();
    ?>
    <form class="ad-form" method="post" action="<?= e(admin_url('itinerary-edit.php', ['id' => $id])) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="<?= (int) $id ?>">
      <input type="hidden" name="do" value="restore">
      <div class="ad-form-act">
        <button class="ad-btn primary" type="submit">Restore this itinerary</button>
        <a class="ad-btn ghost" href="<?= e(admin_url('itineraries.php', ['bin' => 1])) ?>">Back to the bin</a>
      </div>
    </form>
    <?php
    ad_panel_close();
    admin_foot();
    exit;
}

ad_note(
    'Nothing on the public site reads these stops yet. They are stored and shown here, but no page '
    . 'renders a route — so treat this as the editorial work being ready ahead of the screen that will use it.',
    'info'
);
?>

<div class="ad-panel">
  <h2>The route <span class="ad-chip mute"><?= $total ?></span></h2>
  <p class="ad-help" style="margin-block-start:0">
    Drag a row by its handle to reorder it inside its <?= e(strtolower($unit)) ?>, or use ▲ ▼. The day box
    moves a stop to another <?= e(strtolower($unit)) ?>. Nothing is stored until you press save — the
    buttons on the rows save first, so a note typed on another row is never lost.
  </p>

  <?php if ($rows === []): ?>
    <?php ad_empty('No stops yet. Add the first one below — a route with no stops is a title and a promise.'); ?>
  <?php else: ?>
    <form class="ad-form" method="post" action="<?= e(admin_url('itinerary-edit.php', ['id' => $id])) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="<?= (int) $id ?>">

      <div class="ad-tablewrap">
        <table class="ad-table ad-stops" data-stops>
          <thead>
            <tr>
              <th class="ad-stophandle"><span class="ad-sr">Move</span></th>
              <th style="inline-size:24%">Stop</th>
              <th style="inline-size:96px"><?= e($unit) ?></th>
              <th style="inline-size:20%">Time note</th>
              <th>Note / what happens here</th>
              <th class="ad-check"><span class="ad-sr">Remove</span></th>
            </tr>
          </thead>

          <?php foreach ($byDay as $day => $dayRows): ?>
            <?php
            // Visit time is summed from the places' own duration_mins — a number
            // that already exists — rather than estimated here. Stops without a
            // place, or without a duration, are counted separately and said out
            // loud, because a silent undercount reads as "this day is fine".
            $mins   = 0;
            $timed  = 0;
            $untimed = 0;
            foreach ($dayRows as $r) {
                if ((int) ($r['duration_mins'] ?? 0) > 0) {
                    $mins += (int) $r['duration_mins'];
                    $timed++;
                } else {
                    $untimed++;
                }
            }
            ?>
            <tbody>
              <tr class="ad-stopday">
                <td colspan="6">
                  <b><?= e($unit) ?> <?= (int) $day ?></b>
                  <span class="ad-muted">
                    <?= count($dayRows) ?> stop<?= count($dayRows) === 1 ? '' : 's' ?>
                    <?php if ($mins > 0): ?>
                      · ≈<?= e(itin_mins($mins)) ?> of visits<?= $timed < count($dayRows) ? ' (some stops have no time)' : '' ?>
                    <?php endif; ?>
                  </span>
                  <?php if ($mins > 600): ?>
                    <?= ad_chip('over 10 hours of stops', 'warn') ?>
                  <?php endif; ?>
                  <?php if ($untimed > 0 && $mins > 0): ?>
                    <?= ad_chip($untimed . ' without a time', 'mute') ?>
                  <?php endif; ?>
                </td>
              </tr>

              <?php foreach ($dayRows as $r): ?>
                <?php
                $sid     = (int) $r['id'];
                $free    = $r['place_id'] === null;
                $gone    = !$free && $r['place_deleted'] !== null;
                $unpub   = !$free && (string) $r['place_status'] !== 'published';
                ?>
                <tr data-stop="<?= $sid ?>">
                  <td class="ad-stophandle">
                    <span class="ad-grip" draggable="true" title="Drag to reorder">⠿</span>
                    <button class="ad-btn ghost ad-tiny" type="submit" name="do" value="up:<?= $sid ?>"
                            formnovalidate title="Move up">▲</button>
                    <button class="ad-btn ghost ad-tiny" type="submit" name="do" value="down:<?= $sid ?>"
                            formnovalidate title="Move down">▼</button>
                  </td>

                  <td>
                    <input type="hidden" name="stop_id[]" value="<?= $sid ?>">
                    <?php if ($free): ?>
                      <span class="ad-chip mute">free text</span>
                      <?php if ((string) $r['note_en'] === ''): ?>
                        <?= ad_chip('no label', 'bad') ?>
                      <?php endif; ?>
                    <?php else: ?>
                      <a class="ad-rowlink" href="<?= e(admin_url('place-edit.php', ['id' => (int) $r['place_id']])) ?>">
                        <?= e((string) $r['place_name']) ?>
                      </a>
                      <span class="ad-sub">
                        <?= e((string) ($r['mun_name'] ?? '')) ?>
                        <?php if ((int) ($r['duration_mins'] ?? 0) > 0): ?>
                          · <?= e(itin_mins((int) $r['duration_mins'])) ?> to see
                        <?php endif; ?>
                      </span>
                      <?php if ($gone): ?>
                        <?= ad_chip('place is in the bin', 'bad') ?>
                      <?php elseif ($unpub): ?>
                        <?= ad_chip('place is ' . (string) $r['place_status'], 'warn') ?>
                      <?php endif; ?>
                    <?php endif; ?>
                  </td>

                  <td>
                    <label class="ad-sr" for="d_<?= $sid ?>"><?= e($unit) ?></label>
                    <select id="d_<?= $sid ?>" name="day[<?= $sid ?>]">
                      <?php for ($d = 1; $d <= $maxDay; $d++): ?>
                        <option value="<?= $d ?>"<?= $d === (int) $r['day_number'] ? ' selected' : '' ?>>
                          <?= $d ?>
                        </option>
                      <?php endfor; ?>
                    </select>
                  </td>

                  <td>
                    <label class="ad-sr" for="te_<?= $sid ?>">Time note (English)</label>
                    <input type="text" id="te_<?= $sid ?>" name="time_en[<?= $sid ?>]" dir="auto" maxlength="120"
                           value="<?= e((string) $r['time_note_en']) ?>" placeholder="arrive before 4pm">
                    <label class="ad-sr" for="ta_<?= $sid ?>">Time note (Arabic)</label>
                    <input type="text" id="ta_<?= $sid ?>" name="time_ar[<?= $sid ?>]" dir="auto" maxlength="120"
                           value="<?= e((string) $r['time_note_ar']) ?>" class="ad-rtl">
                  </td>

                  <td>
                    <label class="ad-sr" for="ne_<?= $sid ?>">Note (English)</label>
                    <input type="text" id="ne_<?= $sid ?>" name="note_en[<?= $sid ?>]" dir="auto" maxlength="255"
                           value="<?= e((string) $r['note_en']) ?>">
                    <label class="ad-sr" for="na_<?= $sid ?>">Note (Arabic)</label>
                    <input type="text" id="na_<?= $sid ?>" name="note_ar[<?= $sid ?>]" dir="auto" maxlength="255"
                           value="<?= e((string) $r['note_ar']) ?>" class="ad-rtl">
                  </td>

                  <td class="ad-check">
                    <button class="ad-btn ghost danger" type="submit" name="do" value="remove:<?= $sid ?>"
                            formnovalidate data-ad-confirm="Remove this stop from the route? The place itself is not touched."
                            title="Remove this stop">✕</button>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          <?php endforeach; ?>
        </table>
      </div>

      <div class="ad-form-act">
        <button class="ad-btn primary" type="submit" name="do" value="save">Save the order and notes</button>
        <span class="ad-muted"><?= $total ?> stop<?= $total === 1 ? '' : 's' ?> in <?= count($byDay) ?> <?= e(strtolower($unit)) ?><?= count($byDay) === 1 ? '' : 's' ?>.</span>
      </div>
    </form>
  <?php endif; ?>
</div>

<div class="ad-panel">
  <h2>Add a stop</h2>
  <p class="ad-help" style="margin-block-start:0">
    A stop that is not one of the listed places is shown by its note — there is no separate title column —
    so write the name there. A museum with a café in it is one stop with a note, not two.
  </p>

  <form class="ad-form" method="post" action="<?= e(admin_url('itinerary-edit.php', ['id' => $id])) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int) $id ?>">
    <input type="hidden" name="do" value="add">
    <div class="ad-grid">
      <?php
      ad_field([
          'k' => 'place_id', 't' => 'Place', 'type' => 'select', 'wide' => true,
          'options' => $places,
          'help' => 'Draft and archived places are marked, and can be picked — a route is often planned before every stop on it is published.',
      ], 0);

      ad_field([
          'k' => 'day', 't' => $unit, 'type' => 'select',
          'options' => array_combine(range(1, $maxDay), range(1, $maxDay)),
          'help' => 'Added to the end of that ' . strtolower($unit) . '.',
      ], 1);

      ad_field(['k' => 'time_en', 't' => 'Time note (English)', 'maxlen' => 120,
                'ph' => 'arrive before 4pm'], '');
      ad_field(['k' => 'time_ar', 't' => 'Time note (Arabic)', 'maxlen' => 120], '');
      ad_field(['k' => 'note_en', 't' => 'Note (English)', 'maxlen' => 255, 'wide' => true], '');
      ad_field(['k' => 'note_ar', 't' => 'Note (Arabic)', 'maxlen' => 255, 'wide' => true], '');
      ?>
    </div>
    <div class="ad-form-act">
      <button class="ad-btn primary" type="submit">Add the stop</button>
    </div>
  </form>
</div>

<div class="ad-panel">
  <h2>This itinerary</h2>
  <dl class="ad-kv">
    <dt>Length</dt><dd><?= (int) $itin['duration_value'] ?> <?= e((string) $itin['duration_type']) ?></dd>
    <dt>Audience</dt><dd><?= e((string) $itin['audience']) ?></dd>
    <dt>Budget</dt><dd><?= e((string) $itin['budget_level']) ?></dd>
    <dt>Status</dt><dd><?= ad_status_chip((string) $itin['status']) ?></dd>
    <dt>Details checked</dt><dd><?= $itin['verified_at'] === null ? 'never' : e(ad_when((string) $itin['verified_at'], false)) ?></dd>
    <dt>Last changed</dt><dd><?= e(ad_when((string) $itin['updated_at'])) ?></dd>
  </dl>

  <p style="margin-block:16px 0">
    <a class="ad-btn" href="<?= e(admin_url('itineraries.php', ['id' => $id])) ?>">Edit the title and the rest</a>
  </p>

  <form method="post" action="<?= e(admin_url('itinerary-edit.php', ['id' => $id])) ?>" style="margin-block-start:16px">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int) $id ?>">
    <input type="hidden" name="do" value="bin">
    <button class="ad-btn danger" type="submit"
            data-ad-confirm="Move this itinerary to the bin? The stops go with it and it can be restored.">
      Move to the bin
    </button>
    <span class="ad-muted">Soft delete — the bin keeps it, and the stops are untouched.</span>
  </form>
</div>

<?php
admin_foot(['scripts' => '<script src="' . e(admin_asset('js/admin-stops.js')) . '" defer></script>']);
