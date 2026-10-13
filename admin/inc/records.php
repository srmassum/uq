<?php
/**
 * One validated write path for every content table.
 *
 * Twenty modules each hand-writing their own INSERT is twenty chances to forget
 * a whitelist, mistype a column, or bind a value in the wrong order. So instead
 * each module declares its columns once, as a spec, and this file turns that
 * spec into validation, SQL and an audit-log entry.
 *
 * The safety property that matters: **column names come from the spec, which is
 * PHP source, never from the request.** Only values are ever bound. A field the
 * spec does not mention cannot be written, whatever the form posts.
 *
 * Spec field types:
 *   text      string, trimmed, optional maxlen
 *   html      string kept as-is apart from a length cap (admin-only rich text)
 *   int       integer within min/max
 *   decimal   float within min/max, stored at the given scale
 *   bool      1 or 0 from a checkbox
 *   enum      must be one of `values`
 *   date      YYYY-MM-DD or empty → NULL
 *   json      must decode to an object/array; stored re-encoded
 *   slug      lower-case a-z0-9- only; generated from `from` when left blank
 *   color     #rrggbb
 *   email     a valid address
 *   url       http(s) or empty
 *   password  hashed with password_hash(); blank means "leave it alone"
 */

declare(strict_types=1);

/** Fetch a row for editing, or null. */
function record_by_id(string $table, int $id): ?array
{
    if ($id <= 0) {
        return null;
    }
    try {
        return qOne('SELECT * FROM `' . $table . '` WHERE id = ?', [$id]);
    } catch (Throwable $e) {
        error_log("record_by_id($table): " . $e->getMessage());
        return null;
    }
}

/**
 * Validate and write one record.
 *
 * @param array{table: string, module: string, entity: string, fields: array,
 *              soft?: bool, touch?: bool} $def
 * @param array<string, mixed> $src Normally $_POST.
 * @param int|null $id Null to create.
 * @return array{ok: bool, id: int, errors: array<string, string>}
 */
function record_save(array $def, array $src, ?int $id = null): array
{
    $table  = $def['table'];
    $fields = $def['fields'];

    $before = $id !== null ? record_by_id($table, $id) : null;
    if ($id !== null && $before === null) {
        return ['ok' => false, 'id' => 0, 'errors' => ['_form' => 'That record no longer exists.']];
    }

    $values = [];
    $errors = [];

    foreach ($fields as $f) {
        $k    = $f['k'];
        $type = $f['type'] ?? 'text';

        // A field the form legitimately omits (an unchecked box, a password box
        // left blank on an edit) must not be reported as missing.
        $present = array_key_exists($k, $src) || array_key_exists($k . '_text', $src);
        if (!$present && empty($f['required'])) {
            if ($type === 'bool') {
                $values[$k] = 0;
            }
            continue;
        }

        $raw = $src[$k] ?? '';
        if ($type === 'color' && isset($src[$k . '_text'])) {
            // The colour control posts both a swatch and a text box; the text box
            // is what survives a browser with no colour picker.
            $raw = $src[$k . '_text'];
        }
        $raw = is_string($raw) ? trim($raw) : '';

        $err = null;

        switch ($type) {
            case 'int':
                if ($raw === '' && empty($f['required'])) {
                    $values[$k] = null;
                    break;
                }
                if (!is_numeric($raw)) {
                    $err = 'Must be a number.';
                    break;
                }
                $n = (int) $raw;
                if (isset($f['min']) && $n < $f['min']) { $err = 'Must be at least ' . $f['min'] . '.'; break; }
                if (isset($f['max']) && $n > $f['max']) { $err = 'Must be at most ' . $f['max'] . '.'; break; }
                $values[$k] = $n;
                break;

            case 'decimal':
                if ($raw === '' && empty($f['required'])) {
                    $values[$k] = null;
                    break;
                }
                if (!is_numeric($raw)) {
                    $err = 'Must be a number.';
                    break;
                }
                $d = (float) $raw;
                if (isset($f['min']) && $d < $f['min']) { $err = 'Must be at least ' . $f['min'] . '.'; break; }
                if (isset($f['max']) && $d > $f['max']) { $err = 'Must be at most ' . $f['max'] . '.'; break; }
                $values[$k] = number_format($d, (int) ($f['scale'] ?? 6), '.', '');
                break;

            case 'bool':
                $values[$k] = ($raw === '1' || $raw === 'on') ? 1 : 0;
                break;

            case 'enum':
                if (!in_array($raw, $f['values'] ?? [], true)) {
                    if ($raw === '' && isset($f['default'])) {
                        $values[$k] = $f['default'];
                        break;
                    }
                    $err = 'Pick one of: ' . implode(', ', $f['values'] ?? []) . '.';
                    break;
                }
                $values[$k] = $raw;
                break;

            case 'date':
                if ($raw === '') {
                    $values[$k] = null;
                    break;
                }
                $dt = DateTime::createFromFormat('Y-m-d', $raw);
                if ($dt === false || $dt->format('Y-m-d') !== $raw) {
                    $err = 'Use the date picker (YYYY-MM-DD).';
                    break;
                }
                // A check date in the future means someone typo'd the year, and
                // it would silently suppress the "stale content" warning forever.
                if ($dt->getTimestamp() > time() + 86400) {
                    $err = 'That date is in the future.';
                    break;
                }
                $values[$k] = $raw;
                break;

            case 'json':
                if ($raw === '') {
                    if (!empty($f['required'])) { $err = 'Required.'; break; }
                    $values[$k] = null;
                    break;
                }
                $decoded = json_decode($raw, true);
                if (!is_array($decoded)) {
                    $err = 'That is not valid JSON: ' . json_last_error_msg() . '.';
                    break;
                }
                $values[$k] = json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                break;

            case 'slug':
                $s = slugify($raw);
                if ($s === '' && !empty($f['from'])) {
                    // Fall back to the name, so a slug box left blank still
                    // produces a URL rather than an empty unique key.
                    $s = slugify((string) ($src[$f['from']] ?? ''));
                }
                if ($s === '') {
                    $err = 'Needs a slug — letters, numbers and dashes.';
                    break;
                }
                if (mb_strlen($s) > (int) ($f['maxlen'] ?? 64)) {
                    $s = mb_substr($s, 0, (int) $f['maxlen']);
                    $s = rtrim($s, '-');
                }
                $values[$k] = $s;
                break;

            case 'color':
                if (!preg_match('/^#[0-9a-f]{6}$/i', $raw)) {
                    if ($raw === '' && isset($f['default'])) { $values[$k] = $f['default']; break; }
                    $err = 'Use a #rrggbb colour.';
                    break;
                }
                $values[$k] = strtolower($raw);
                break;

            case 'email':
                if (!filter_var($raw, FILTER_VALIDATE_EMAIL)) {
                    $err = 'That does not look like an email address.';
                    break;
                }
                $values[$k] = $raw;
                break;

            case 'url':
                if ($raw === '') { $values[$k] = ''; break; }
                if (!preg_match('#^https?://#i', $raw)) {
                    $err = 'Must start with http:// or https://';
                    break;
                }
                $values[$k] = mb_substr($raw, 0, (int) ($f['maxlen'] ?? 255));
                break;

            case 'password':
                if ($raw === '') {
                    // Blank on an edit means "keep the current password". On a
                    // create, a password is not optional.
                    if ($id === null) { $err = 'A password is required.'; break; }
                    break;
                }
                if (mb_strlen($raw) < 10) {
                    $err = 'Use at least 10 characters.';
                    break;
                }
                $values[$k] = password_hash($raw, PASSWORD_DEFAULT);
                break;

            case 'html':
            case 'text':
            default:
                $max = $f['maxlen'] ?? null;
                if ($max !== null && mb_strlen($raw) > $max) {
                    $err = 'Too long — ' . $max . ' characters maximum.';
                    break;
                }
                if ($raw === '' && !empty($f['required'])) {
                    $err = 'Required.';
                    break;
                }
                $values[$k] = $raw;
        }

        if ($err !== null) {
            $errors[$k] = $err;
        }
    }

    // The slug is a unique key, so a clash has to be caught here rather than
    // surfacing as a raw duplicate-key error from MySQL.
    foreach ($fields as $f) {
        if (($f['type'] ?? '') !== 'slug' || !isset($values[$f['k']])) {
            continue;
        }
        $sql = 'SELECT id FROM `' . $table . '` WHERE `' . $f['k'] . '` = ?';
        $bind = [$values[$f['k']]];
        if ($id !== null) {
            $sql .= ' AND id <> ?';
            $bind[] = $id;
        }
        if (qOne($sql, $bind) !== null) {
            $errors[$f['k']] = 'Another record already uses that slug.';
        }
    }

    if ($errors !== []) {
        return ['ok' => false, 'id' => $id ?? 0, 'errors' => $errors];
    }

    // Nothing to write: an edit form submitted untouched. Say so honestly rather
    // than bumping updated_at and writing an empty audit entry.
    if ($id !== null) {
        $changed = false;
        foreach ($values as $k => $v) {
            if ((string) ($before[$k] ?? '') !== (string) $v) { $changed = true; break; }
        }
        if (!$changed) {
            return ['ok' => true, 'id' => $id, 'errors' => []];
        }
    }

    try {
        if ($id === null) {
            $cols = array_keys($values);
            $sql  = 'INSERT INTO `' . $table . '` (`' . implode('`, `', $cols) . '`) VALUES ('
                  . implode(', ', array_fill(0, count($cols), '?')) . ')';
            q($sql, array_values($values));
            $id = (int) db()->lastInsertId();
        } else {
            $sets = [];
            foreach (array_keys($values) as $c) {
                $sets[] = '`' . $c . '` = ?';
            }
            if (!empty($def['touch'])) {
                $sets[] = '`updated_at` = NOW()';
            }
            q('UPDATE `' . $table . '` SET ' . implode(', ', $sets) . ' WHERE id = ?',
              [...array_values($values), $id]);
        }
    } catch (Throwable $e) {
        error_log("record_save($table): " . $e->getMessage());
        return ['ok' => false, 'id' => $id ?? 0,
                'errors' => ['_form' => 'The database refused that: ' . $e->getMessage()]];
    }

    $after = record_by_id($table, $id);
    log_activity(
        (string) $def['module'],
        $before === null ? 'create' : 'update',
        (string) $def['entity'],
        $id,
        $before,
        $after
    );

    return ['ok' => true, 'id' => $id, 'errors' => []];
}

// ---------------------------------------------------------------------------
// Soft delete, restore and purge
// ---------------------------------------------------------------------------

/**
 * Move a record to the bin. Soft-deleted rows stay out of every public query
 * (they all filter on deleted_at IS NULL) but keep their id, so nothing that
 * points at them breaks.
 */
function record_soft_delete(array $def, int $id): bool
{
    $before = record_by_id((string) $def['table'], $id);
    if ($before === null || !empty($def['no_soft'])) {
        return false;
    }
    try {
        q('UPDATE `' . $def['table'] . '` SET deleted_at = NOW() WHERE id = ?', [$id]);
    } catch (Throwable $e) {
        error_log('record_soft_delete: ' . $e->getMessage());
        return false;
    }
    log_activity((string) $def['module'], 'soft_delete', (string) $def['entity'], $id, $before, null);
    return true;
}

function record_restore(array $def, int $id): bool
{
    try {
        q('UPDATE `' . $def['table'] . '` SET deleted_at = NULL WHERE id = ?', [$id]);
    } catch (Throwable $e) {
        error_log('record_restore: ' . $e->getMessage());
        return false;
    }
    log_activity((string) $def['module'], 'restore', (string) $def['entity'], $id, null, record_by_id((string) $def['table'], $id));
    return true;
}

/** Permanent. Admin only, and only from the bin, where the intent is explicit. */
function record_purge(array $def, int $id): bool
{
    $before = record_by_id((string) $def['table'], $id);
    if ($before === null) {
        return false;
    }
    try {
        q('DELETE FROM `' . $def['table'] . '` WHERE id = ?', [$id]);
    } catch (Throwable $e) {
        error_log('record_purge: ' . $e->getMessage());
        return false;
    }
    log_activity((string) $def['module'], 'purge', (string) $def['entity'], $id, $before, null);
    return true;
}

// ---------------------------------------------------------------------------
// Bulk actions
// ---------------------------------------------------------------------------

/**
 * Apply one action to many rows.
 *
 * Every id is cast to an int and the action is checked against a fixed list, so
 * a crafted `bulk` value or a non-numeric id in the array cannot reach SQL.
 *
 * @param array<int, mixed> $ids Raw ids from the form.
 * @return array{done: int, skipped: int}
 */
function bulk_apply(array $def, array $ids, string $action): array
{
    $allowed = ['publish', 'unpublish', 'archive', 'delete', 'restore', 'feature', 'unfeature', 'purge'];
    if (!in_array($action, $allowed, true)) {
        return ['done' => 0, 'skipped' => count($ids)];
    }

    $table = (string) $def['table'];
    $done = 0;
    $skipped = 0;

    foreach ($ids as $raw) {
        $id = is_numeric($raw) ? (int) $raw : 0;
        if ($id <= 0) {
            $skipped++;
            continue;
        }

        try {
            switch ($action) {
                case 'publish':
                case 'unpublish':
                case 'archive':
                    $to = ['publish' => 'published', 'unpublish' => 'draft', 'archive' => 'archived'][$action];
                    q("UPDATE `$table` SET status = ? WHERE id = ?", [$to, $id]);
                    log_activity((string) $def['module'], $action, (string) $def['entity'], $id);
                    $done++;
                    break;

                case 'feature':
                case 'unfeature':
                    q("UPDATE `$table` SET is_featured = ? WHERE id = ?", [$action === 'feature' ? 1 : 0, $id]);
                    log_activity((string) $def['module'], $action, (string) $def['entity'], $id);
                    $done++;
                    break;

                case 'delete':
                    record_soft_delete($def, $id) ? $done++ : $skipped++;
                    break;

                case 'restore':
                    record_restore($def, $id) ? $done++ : $skipped++;
                    break;

                case 'purge':
                    record_purge($def, $id) ? $done++ : $skipped++;
                    break;
            }
        } catch (Throwable $e) {
            error_log("bulk_apply($action): " . $e->getMessage());
            $skipped++;
        }
    }

    return ['done' => $done, 'skipped' => $skipped];
}

/** Read the posted id list, rejecting anything that is not a scalar int. */
function posted_ids(): array
{
    $ids = $_POST['ids'] ?? [];
    if (!is_array($ids)) {
        return [];
    }
    return array_values(array_filter($ids, static fn($v): bool => is_scalar($v) && is_numeric($v)));
}

// ---------------------------------------------------------------------------
// Shared ordering helpers for list screens
// ---------------------------------------------------------------------------

/**
 * ORDER BY for a list screen: a named key mapped to a fixed clause.
 * A sort parameter is never concatenated — it only ever selects from this map.
 */
function admin_order(array $map, string $key, string $fallback): string
{
    return $map[$key] ?? $map[$fallback];
}

/**
 * Escape a LIKE needle.
 *
 * inc/places.php has places_like_escape(), but bootstrap does not load that file
 * and the panel has no other reason to want it — so rather than pull in the
 * whole public places layer for one str_replace, the two lines live here too.
 */
function admin_like_escape(string $s): string
{
    return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $s);
}

/** Build a WHERE fragment from a set of equality filters, safely. */
function admin_filter_sql(array $filters, array &$bind): string
{
    $w = [];
    foreach ($filters as $col => $val) {
        if ($val === null || $val === '') {
            continue;
        }
        if (!preg_match('/^[a-z_]+$/', $col)) {
            continue;
        }
        $w[] = $col . ' = ?';
        $bind[] = $val;
    }
    return $w === [] ? '' : ' WHERE ' . implode(' AND ', $w);
}
