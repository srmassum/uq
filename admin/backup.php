<?php
/**
 * Backup and export.
 *
 * There is no mysqldump call here, and that is a decision rather than a gap.
 * Shelling out to a binary means depending on where XAMPP put it, on exec() being
 * enabled, and on a path that survives being moved to a host with a different
 * layout. Instead the dump is written in PHP, from the same PDO connection the
 * panel already uses, and streamed straight to the browser as a download.
 *
 * Two honest limits, both stated on the page rather than discovered later:
 *
 *   - The dump is data only. Table structure is in sql/schema.sql, which is in
 *     the repository, so there is no CREATE TABLE and no DROP TABLE here. That
 *     also means importing this file into a live database cannot drop anything.
 *   - A database dump is not a backup of the site. Uploaded images live on disk
 *     in uploads/, and the database only knows their paths. The screen says so
 *     and lists the files involved.
 *
 * Tables are exported in dependency order, and any table larger than the row cap
 * says so inside the file — a dump that silently stops short is worse than one
 * that admits it.
 */

declare(strict_types=1);

require_once __DIR__ . '/inc/panel.php';

require_admin('settings.manage');
admin_require_csrf();

/** Rows per table before the dump gives up and says so. */
const BACKUP_MAX_ROWS = 20000;

/** Rows fetched per round trip while streaming. */
const BACKUP_CHUNK = 400;

/** The stem every download is named after, e.g. "qatar-unseen-backup-….sql". */
function backup_slug(): string
{
    $s = slugify(setting('site.name', SITE_NAME));
    return $s === '' ? 'site' : $s;
}

/**
 * The tables, in the order they must be inserted for the foreign keys to hold.
 *
 * group: 'content' is what an editor wrote by hand; 'people' is accounts and
 * configuration; 'visitors' is everything the public generated, which is the
 * part that grows without limit.
 *
 * @return array<string, array{label: string, group: string, note: string}>
 */
function backup_tables(): array
{
    return [
        'municipalities'   => ['label' => 'Municipalities', 'group' => 'content', 'note' => ''],
        'localities'       => ['label' => 'Localities', 'group' => 'content', 'note' => ''],
        'categories'       => ['label' => 'Categories', 'group' => 'content', 'note' => ''],
        'tags'             => ['label' => 'Tags', 'group' => 'content', 'note' => ''],
        'places'           => ['label' => 'Places', 'group' => 'content', 'note' => ''],
        'place_tag'        => ['label' => 'Place tags', 'group' => 'content', 'note' => ''],
        'place_images'     => ['label' => 'Image records', 'group' => 'content',
                               'note' => 'Paths only. The files themselves are in uploads/ and are not in this dump.'],
        'levels'           => ['label' => 'Levels', 'group' => 'content', 'note' => ''],
        'badges'           => ['label' => 'Badges', 'group' => 'content', 'note' => ''],
        'personalities'    => ['label' => 'Personalities', 'group' => 'content', 'note' => ''],
        'share_themes'     => ['label' => 'Card themes', 'group' => 'content', 'note' => ''],
        'itineraries'      => ['label' => 'Itineraries', 'group' => 'content', 'note' => ''],
        'itinerary_stops'  => ['label' => 'Itinerary stops', 'group' => 'content', 'note' => ''],
        'collections'      => ['label' => 'Collections', 'group' => 'content', 'note' => ''],
        'collection_places'=> ['label' => 'Collection places', 'group' => 'content', 'note' => ''],
        'guide_pages'      => ['label' => 'Visitor guide pages', 'group' => 'content', 'note' => ''],

        'admin_users'      => ['label' => 'Admin accounts', 'group' => 'people',
                               'note' => 'Includes password hashes and two-factor secrets. Treat this file as a secret.'],
        'settings'         => ['label' => 'Settings', 'group' => 'people', 'note' => ''],
        'translations'     => ['label' => 'Translations', 'group' => 'people', 'note' => ''],

        'saved_maps'       => ['label' => 'Saved maps', 'group' => 'visitors',
                               'note' => 'Each row carries a rendered PNG up to 400 KB, stored as hex here — so this table can dwarf everything else.'],
        'saved_map_items'  => ['label' => 'Saved map ticks', 'group' => 'visitors', 'note' => ''],
        'card_events'      => ['label' => 'Card events', 'group' => 'visitors', 'note' => ''],
        'feedback'         => ['label' => 'Feedback', 'group' => 'visitors', 'note' => ''],
        'admin_login_attempts' => ['label' => 'Sign-in attempts', 'group' => 'visitors',
                               'note' => 'Disposable. Losing it costs you a security history, nothing else.'],
        'activity_log'     => ['label' => 'Activity log', 'group' => 'visitors', 'note' => ''],
    ];
}

/** Column name => type, so binary columns can be written as hex. */
function backup_columns(string $table): array
{
    $cols = [];
    foreach (qAll('SHOW COLUMNS FROM `' . $table . '`') as $c) {
        $cols[(string) $c['Field']] = strtolower((string) $c['Type']);
    }
    return $cols;
}

function backup_is_binary(string $type): bool
{
    return str_contains($type, 'blob') || str_contains($type, 'binary');
}

/** One SQL literal for one value. */
function backup_literal(mixed $value, string $type): string
{
    if ($value === null) {
        return 'NULL';
    }
    if (backup_is_binary($type)) {
        // Hex rather than a quoted string: binary data through a text connection
        // can be mangled by character-set conversion, and hex cannot.
        return "X'" . bin2hex((string) $value) . "'";
    }
    if (preg_match('/^(tinyint|smallint|mediumint|int|bigint|decimal|float|double|bit)/', $type)) {
        return (string) $value;
    }
    // PDO's own quoting uses the live connection's charset, which is the only
    // correct escaper for the string we are about to hand back to MySQL.
    return db()->quote((string) $value);
}

// ------------------------------------------------------------------ exports
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array(post_str('do'), ['sql', 'csv'], true)) {
    // Nothing above this line has printed — bootstrap buffers, and the dump
    // functions below flush it — so the download headers are still ours to set.
    $do = post_str('do');

    if ($do === 'sql') {
        $wanted = $_POST['tables'] ?? [];
        $wanted = is_array($wanted) ? array_map('strval', $wanted) : [];
        $known  = backup_tables();
        $picked = array_values(array_filter(array_keys($known), static fn(string $t): bool => in_array($t, $wanted, true)));

        if ($picked === []) {
            admin_back(admin_url('backup.php'), 'Pick at least one group to export.', 'warn');
        }

        backup_stream_sql($picked, $known);
        exit;
    }

    backup_stream_csv();
    exit;
}

// -------------------------------------------------------------------- writes
/**
 * Stream a data-only SQL dump.
 *
 * @param array<int, string> $tables Already validated against backup_tables().
 */
function backup_stream_sql(array $tables, array $known): void
{
    // A dump of a large table takes longer than the default limit, and the user
    // is watching a download rather than waiting on a page render.
    set_time_limit(0);

    // bootstrap opened an output buffer; a dump must not be assembled in memory.
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    $stamp = date('Y-m-d-His');
    header('Content-Type: application/sql; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . backup_slug() . '-backup-' . $stamp . '.sql"');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');

    $out = static function (string $s): void {
        echo $s;
    };

    $out("-- " . setting('site.name', SITE_NAME) . " — data dump\n");
    $out("-- Written " . date('Y-m-d H:i:s') . " by " . admin_name() . " from the admin panel.\n");
    $out("--\n");
    $out("-- DATA ONLY. There are no CREATE TABLE and no DROP TABLE statements here, so\n");
    $out("-- importing this file cannot remove a table. Build the structure first from\n");
    $out("-- sql/schema.sql, then import this to put the rows back.\n");
    $out("--\n");
    $out("-- Not included, and not in any database dump: the uploaded image files in\n");
    $out("-- uploads/. The place_images rows below are paths pointing at them.\n");
    $out("--\n");
    $out("-- Tables: " . implode(', ', $tables) . "\n\n");
    $out("SET NAMES utf8mb4;\n");
    $out("SET FOREIGN_KEY_CHECKS = 0;\n\n");

    foreach ($tables as $table) {
        $cols = [];
        try {
            $cols = backup_columns($table);
        } catch (Throwable $e) {
            $out("-- $table: could not read its columns (" . $e->getMessage() . "), skipped.\n\n");
            continue;
        }
        if ($cols === []) {
            $out("-- $table: no columns, skipped.\n\n");
            continue;
        }

        $names  = array_keys($cols);
        $colSql = '`' . implode('`, `', $names) . '`';

        try {
            $total = (int) qVal('SELECT COUNT(*) FROM `' . $table . '`');
        } catch (Throwable $e) {
            $out("-- $table: could not be counted, skipped.\n\n");
            continue;
        }

        $out("-- ---------------------------------------------------------------- $table\n");
        $out("-- {$known[$table]['label']}: $total row" . ($total === 1 ? '' : 's') . "\n");

        if ($total === 0) {
            $out("-- (empty)\n\n");
            continue;
        }

        // A cap rather than a promise: the file says what it did, so nobody
        // restores a dump believing it was complete when it was not.
        $cap = min($total, BACKUP_MAX_ROWS);
        if ($total > BACKUP_MAX_ROWS) {
            $out("-- TRUNCATED: only the first " . number_format(BACKUP_MAX_ROWS) . " rows are in this\n");
            $out("-- file. This table is too big for a browser download — use mysqldump for\n");
            $out("-- a complete copy of it.\n");
        }

        $offset = 0;
        $written = 0;
        while ($offset < $cap) {
            $take = min(BACKUP_CHUNK, $cap - $offset);
            try {
                // Interpolated from ints: with emulated prepares off, a bound
                // value arrives as a string and MySQL refuses LIMIT '400'.
                $rows = qAll('SELECT * FROM `' . $table . '` LIMIT ' . $take . ' OFFSET ' . $offset);
            } catch (Throwable $e) {
                $out("-- stopped at row $offset: " . $e->getMessage() . "\n");
                break;
            }
            if ($rows === []) {
                break;
            }

            foreach ($rows as $row) {
                $vals = [];
                foreach ($names as $n) {
                    $vals[] = backup_literal($row[$n] ?? null, $cols[$n]);
                }
                $out("INSERT INTO `$table` ($colSql) VALUES (" . implode(', ', $vals) . ");\n");
                $written++;
            }
            $offset += count($rows);
        }

        $out("-- $written row" . ($written === 1 ? '' : 's') . " written\n\n");
    }

    $out("SET FOREIGN_KEY_CHECKS = 1;\n");
    $out("-- end of dump\n");
}

/** Places as a spreadsheet, for editing in bulk and re-importing by hand. */
function backup_stream_csv(): void
{
    set_time_limit(0);
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . backup_slug() . '-places-' . date('Y-m-d') . '.csv"');
    header('Cache-Control: no-store');

    $fh = fopen('php://output', 'w');
    if ($fh === false) {
        exit;
    }

    // Excel on Windows reads a UTF-8 CSV as Latin-1 unless the file opens with a
    // byte-order mark — and Arabic place names are the whole point here.
    fwrite($fh, "\xEF\xBB\xBF");

    fputcsv($fh, [
        'slug', 'name_en', 'name_ar', 'municipality', 'locality', 'category',
        'lat', 'lng', 'best_time_en', 'duration_mins', 'fee_level', 'fee_note_en',
        'is_featured', 'is_hidden_gem', 'family_friendly', 'metro_access', 'indoor',
        'season', 'map_url', 'official_url', 'verified_at', 'status',
        'summary_en', 'summary_ar',
    ], ',', '"', '');

    try {
        $rows = qAll(
            'SELECT p.*, m.name_en AS mun_name, l.name_en AS loc_name, c.slug AS cat_slug
               FROM places p
               JOIN municipalities m ON m.id = p.municipality_id
               LEFT JOIN localities l ON l.id = p.locality_id
               LEFT JOIN categories c ON c.id = p.category_id
              ORDER BY m.sort_order, p.name_en'
        );
    } catch (Throwable $e) {
        error_log('backup csv: ' . $e->getMessage());
        fputcsv($fh, ['could not read places: ' . $e->getMessage()], ',', '"', '');
        fclose($fh);
        return;
    }

    foreach ($rows as $r) {
        fputcsv($fh, [
            $r['slug'], $r['name_en'], $r['name_ar'], $r['mun_name'], $r['loc_name'], $r['cat_slug'],
            $r['lat'], $r['lng'], $r['best_time_en'], $r['duration_mins'], $r['fee_level'], $r['fee_note_en'],
            (int) $r['is_featured'], (int) $r['is_hidden_gem'], (int) $r['family_friendly'],
            (int) $r['metro_access'], (int) $r['indoor'], $r['season'],
            $r['map_url'], $r['official_url'], $r['verified_at'], $r['status'],
            $r['summary_en'], $r['summary_ar'],
        ], ',', '"', '');
    }

    fclose($fh);
}

// -------------------------------------------------------------------- reads
$tables = backup_tables();

$counts = [];
$sizes  = [];
foreach (array_keys($tables) as $t) {
    try {
        $counts[$t] = (int) qVal('SELECT COUNT(*) FROM `' . $t . '`');
    } catch (Throwable $e) {
        $counts[$t] = null;   // null renders as "—", so a missing table costs one row
    }
}

$dbBytes = null;
$uploads = ['files' => 0, 'bytes' => 0];
try {
    $dbBytes = (int) qVal(
        'SELECT COALESCE(SUM(data_length + index_length), 0)
           FROM information_schema.tables WHERE table_schema = ?',
        [DB_NAME]
    );
} catch (Throwable $e) {
    error_log('backup db size: ' . $e->getMessage());
}

$uploadRoot = dirname(__DIR__) . '/' . UPLOAD_DIR;
if (is_dir($uploadRoot)) {
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($uploadRoot, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($it as $f) {
        if ($f->isFile()) {
            $uploads['files']++;
            $uploads['bytes'] += $f->getSize();
        }
    }
}

$groupTotals = ['content' => 0, 'people' => 0, 'visitors' => 0];
foreach ($tables as $t => $info) {
    if ($counts[$t] !== null) {
        $groupTotals[$info['group']] += $counts[$t];
    }
}

admin_head(['title' => 'Backup', 'nav' => 'backup']);

ad_title('Backup', 'What to copy, where it goes, and what this file does not contain.');

ad_note(
    'A database dump is <b>half</b> a backup. The uploaded images are files on disk that the database '
    . 'only stores paths to, so a restore from SQL alone brings back every place with a broken picture. '
    . 'Copy <code>' . e(UPLOAD_DIR) . '/</code> as well — ' . number_format($uploads['files']) . ' file'
    . ($uploads['files'] === 1 ? '' : 's')
    . ($uploads['bytes'] > 0 ? ', ' . e(human_bytes($uploads['bytes'])) : '') . '.',
    'info'
);
?>

<div class="ad-stats" style="margin-block-end:18px">
  <div class="ad-stat">
    <b><?= $dbBytes === null ? '—' : e(human_bytes($dbBytes)) ?></b>
    <span>Database size</span>
  </div>
  <div class="ad-stat">
    <b><?= e(human_bytes($uploads['bytes'])) ?></b>
    <span>Uploaded images</span>
  </div>
  <div class="ad-stat">
    <b><?= number_format($counts['places'] ?? 0) ?></b>
    <span>Places</span>
  </div>
  <div class="ad-stat">
    <b><?= number_format($counts['saved_maps'] ?? 0) ?></b>
    <span>Saved maps</span>
  </div>
</div>

<form method="post" action="<?= e(admin_url('backup.php')) ?>">
  <?= csrf_field() ?>
  <input type="hidden" name="do" value="sql">

  <?php
  $groups = [
      'content'  => ['label' => 'Content', 'sub' => 'Everything written by hand: places, images, routes, the guide. Small, and the part that hurts to lose.'],
      'people'   => ['label' => 'Accounts and settings', 'sub' => 'Admin logins, the settings table, translations. The file will contain password hashes.'],
      'visitors' => ['label' => 'Visitor data', 'sub' => 'Saved maps, their rendered PNGs, tick lists, events, feedback, the logs. This is the part that grows without limit.'],
  ];

  foreach ($groups as $key => $g) {
      ad_panel_open();
      ?>
      <h2><?= e($g['label']) ?>
        <span class="ad-chip<?= $groupTotals[$key] === 0 ? ' mute' : '' ?>"><?= number_format($groupTotals[$key]) ?> rows</span>
      </h2>
      <p class="ad-help" style="margin-block-start:0"><?= e($g['sub']) ?></p>
      <div class="ad-grid">
        <?php foreach ($tables as $t => $info): ?>
          <?php if ($info['group'] !== $key) { continue; } ?>
          <div class="ad-field">
            <label class="ad-switch">
              <input type="checkbox" name="tables[]" value="<?= e($t) ?>"
                <?= $key === 'visitors' ? '' : ' checked' ?>>
              <span><?= e($info['label']) ?></span>
            </label>
            <span class="ad-help">
              <code><?= e($t) ?></code> · <?= $counts[$t] === null ? 'table missing' : number_format($counts[$t]) . ' rows' ?>
              <?php if ($info['note'] !== ''): ?><br><?= e($info['note']) ?><?php endif; ?>
            </span>
          </div>
        <?php endforeach; ?>
      </div>
      <?php
      ad_panel_close();
  }
  ?>

  <div class="ad-panel">
    <h2>Download</h2>
    <p class="ad-help" style="margin-block-start:0">
      The file arrives as <code>.sql</code> with <code>INSERT</code> statements only. No
      <code>CREATE TABLE</code>, no <code>DROP TABLE</code> — so importing it can never remove a table,
      and the structure always comes from <code>sql/schema.sql</code> in the repository.
    </p>
    <div class="ad-form-act" style="margin-block-start:0;padding-block-start:0;border:0">
      <button class="ad-btn primary" type="submit">Download SQL</button>
      <span class="ad-muted">Tables with more than <?= number_format(BACKUP_MAX_ROWS) ?> rows are cut short, and the file says so.</span>
    </div>
  </div>
</form>

<div class="ad-panel">
  <h2>Places as a spreadsheet</h2>
  <p class="ad-help" style="margin-block-start:0">
    One row per place with its municipality, category, coordinates and practical details. Useful for
    reading the whole map at once, spotting what is missing, or handing to somebody who would rather
    work in Excel than in this panel. It is an export, not a round trip — there is no matching import,
    so edits made here have to be typed back into the place editor.
  </p>
  <form method="post" action="<?= e(admin_url('backup.php')) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="do" value="csv">
    <div class="ad-form-act" style="margin-block-start:0;padding-block-start:0;border:0">
      <button class="ad-btn" type="submit">Download places as CSV</button>
      <span class="ad-muted">Opens in Excel with the Arabic column intact — the file carries a UTF-8 byte-order mark.</span>
    </div>
  </form>
</div>

<div class="ad-panel">
  <h2>Putting it back</h2>
  <ol style="margin:0;padding-inline-start:20px;line-height:1.7">
    <li>Import <code>sql/schema.sql</code> first. It creates the tables and the views.</li>
    <li>Import <code>sql/seed-core.sql</code> for the categories, tags, levels, badges and themes.</li>
    <li>Import the dump downloaded above. It disables foreign-key checks while it runs, so order within the file does not matter, and re-enables them at the end.</li>
    <li>Copy the <code><?= e(UPLOAD_DIR) ?>/</code> folder back to the same place it was. Image paths stored in the database are relative to it, so a different folder name breaks every picture.</li>
  </ol>
  <p class="ad-help" style="margin-block-end:0">
    There is no restore button in this panel on purpose. Restoring means running a file of arbitrary
    SQL, and a web form that does that is a far bigger risk than the convenience is worth —
    phpMyAdmin already does the job, over a connection you control.
  </p>
</div>

<div class="ad-panel">
  <h2>What is not in here</h2>
  <ul class="ad-list">
    <li><span>Uploaded images</span><span class="ad-muted">files in <code><?= e(UPLOAD_DIR) ?>/</code> — copy the folder</span></li>
    <li><span>Map geometry and locality coordinates</span><span class="ad-muted"><code>assets/data/*.json</code>, regenerated by <code>setup/prepare.php</code></span></li>
    <li><span>The code itself</span><span class="ad-muted">version control, hopefully</span></li>
    <li><span><code>inc/config.php</code></span><span class="ad-muted">database password and site constants — back it up somewhere private, never in this dump</span></li>
  </ul>
</div>

<?php
admin_foot();
