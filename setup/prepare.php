<?php
/**
 * One-time build step. Reads the source Qatar SVG, works out the lat/lng -> pixel
 * projection from the three calibration circles baked into it, then writes the
 * runtime map files and the SQL seed.
 *
 * Run it from the browser (http://localhost/eq/setup/prepare.php) or the CLI
 * (php setup/prepare.php). Re-running is safe — everything is overwritten.
 *
 * Nothing here runs at request time; the site only reads the generated JSON.
 */

declare(strict_types=1);

// %f in sprintf is locale-aware, so a non-C LC_NUMERIC would emit "25,300000"
// and quietly produce SQL that does not parse. Nothing below sets the locale,
// but pinning it here costs one line and removes the whole class of problem.
setlocale(LC_NUMERIC, 'C');

$ROOT = dirname(__DIR__);
$SRC_SVG = $ROOT . '/qatar Geo map/qa (1).svg';

// ---------------------------------------------------------------------------
// Reporting
// ---------------------------------------------------------------------------

$isCli = PHP_SAPI === 'cli';
$log = [];

function say(string $line = ''): void
{
    global $log, $isCli;
    $log[] = $line;
    if ($isCli) {
        echo $line . PHP_EOL;
    }
}

function fail(string $msg): never
{
    global $isCli;
    if ($isCli) {
        fwrite(STDERR, "ERROR: $msg\n");
    } else {
        echo '<pre style="font:14px/1.5 monospace;color:#b00">ERROR: ' . htmlspecialchars($msg) . '</pre>';
    }
    exit(1);
}

/**
 * Write a JSON file, refusing to write a truncated one.
 *
 * json_encode returns false rather than throwing when it meets invalid UTF-8,
 * which is exactly what a mistyped Arabic name in data/*.php produces. Without
 * this check that failure surfaces much later as an empty map with no error
 * anywhere, so it is worth catching at the source.
 */
function write_json(string $path, mixed $value): void
{
    $json = json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        fail('json_encode failed for ' . basename($path) . ': ' . json_last_error_msg()
           . ' — check data/*.php for text that is not valid UTF-8.');
    }
    if (file_put_contents($path, $json) === false) {
        fail('Could not write ' . $path);
    }
}

// ---------------------------------------------------------------------------
// 1. Parse the source SVG
// ---------------------------------------------------------------------------

if (!is_file($SRC_SVG)) {
    fail("Source SVG not found at: $SRC_SVG");
}
$svg = file_get_contents($SRC_SVG);
if ($svg === false) {
    fail('Could not read the source SVG.');
}

/** Pull one attribute out of a tag's attribute string. */
function attr(string $attrs, string $name): ?string
{
    return preg_match('/\b' . preg_quote($name, '/') . '\s*=\s*"([^"]*)"/i', $attrs, $m)
        ? $m[1]
        : null;
}

$muniPaths = [];   // code => ['d' => ..., 'class' => ...]
$calib     = [];   // [ ['lat'=>, 'lng'=>, 'x'=>, 'y'=>], ... ]
$labelPts  = [];   // code => ['x'=>, 'y'=>, 'name'=>]

if (!preg_match_all('/<path\b([^>]*?)\/?>/i', $svg, $pm, PREG_SET_ORDER)) {
    fail('No <path> elements found — is this the right SVG?');
}
foreach ($pm as $p) {
    $a  = $p[1];
    $d  = attr($a, 'd');
    $id = attr($a, 'id');
    if ($d === null || $id === null || !preg_match('/^QA[A-Z]{2}$/', $id)) {
        continue;   // the `<g id="features">` wrapper and friends
    }
    $muniPaths[$id] = ['d' => $d];
}

if (!preg_match_all('/<circle\b([^>]*?)\/?>/i', $svg, $cm, PREG_SET_ORDER)) {
    fail('No <circle> elements found — the calibration points are missing.');
}
foreach ($cm as $c) {
    $a     = $c[1];
    $class = attr($a, 'class') ?? '';
    $id    = attr($a, 'id') ?? '';
    $x     = attr($a, 'cx');
    $y     = attr($a, 'cy');
    if ($x === null || $y === null) {
        continue;
    }
    if (preg_match('/^(-?[\d.]+)\|(-?[\d.]+)$/', $class, $ll)) {
        // class="lat|lng" — a calibration point
        $calib[] = ['lat' => (float) $ll[1], 'lng' => (float) $ll[2], 'x' => (float) $x, 'y' => (float) $y];
    } elseif (preg_match('/^QA[A-Z]{2}$/', $id)) {
        // class="Ad Dawhah" — the label anchor for a municipality
        $labelPts[$id] = ['x' => (float) $x, 'y' => (float) $y, 'name' => $class];
    }
}

say('Source SVG   : ' . count($muniPaths) . ' municipality paths, '
    . count($calib) . ' calibration points, ' . count($labelPts) . ' label anchors');

if (count($muniPaths) !== 8) {
    fail('Expected 8 municipality paths, found ' . count($muniPaths) . '.');
}
if (count($calib) < 3) {
    fail('Need at least 3 calibration points to solve the projection, found ' . count($calib) . '.');
}

// ---------------------------------------------------------------------------
// 2. Solve the projection
//
// The SVG uses Web Mercator, so:
//     x = ax * lng + bx
//     y = ay * mercY(lat) + by      where mercY(lat) = ln(tan(pi/4 + lat*pi/360))
//
// Checked against the source: fitting mercY gives slopes agreeing to ~0.04% across
// all three point pairs, versus ~0.6% if you fit raw latitude — so Mercator it is.
// ---------------------------------------------------------------------------

function mercY(float $lat): float
{
    return log(tan(M_PI / 4 + $lat * M_PI / 360));
}

/** Ordinary least squares y = m*x + c. Returns [m, c, maxResidual]. */
function fitLinear(array $xs, array $ys): array
{
    $n = count($xs);
    if ($n !== count($ys) || $n < 2) {
        fail('fitLinear needs matching arrays of at least 2 points.');
    }
    $mx = array_sum($xs) / $n;
    $my = array_sum($ys) / $n;
    $sxy = $sxx = 0.0;
    for ($i = 0; $i < $n; $i++) {
        $dx = $xs[$i] - $mx;
        $sxy += $dx * ($ys[$i] - $my);
        $sxx += $dx * $dx;
    }
    if (abs($sxx) < 1e-15) {
        fail('Calibration points are degenerate (all the same longitude?).');
    }
    $m = $sxy / $sxx;
    $c = $my - $m * $mx;

    $res = 0.0;
    for ($i = 0; $i < $n; $i++) {
        $res = max($res, abs($ys[$i] - ($m * $xs[$i] + $c)));
    }
    return [$m, $c, $res];
}

$lngs  = array_column($calib, 'lng');
$mercs = array_map(fn($p) => mercY($p['lat']), $calib);
$px    = array_column($calib, 'x');
$py    = array_column($calib, 'y');

[$ax, $bx, $resX] = fitLinear($lngs, $px);
[$ay, $by, $resY] = fitLinear($mercs, $py);

say(sprintf('Projection   : x = %.6f*lng + %.3f   (max residual %.2f px)', $ax, $bx, $resX));
say(sprintf('               y = %.6f*mercY + %.3f (max residual %.2f px)', $ay, $by, $resY));

if ($resX > 2.0 || $resY > 2.0) {
    say('  !! Residuals are larger than expected — locality dots may sit slightly off.');
}

/** lat/lng -> SVG pixel coordinates. */
function project(float $lat, float $lng): array
{
    global $ax, $bx, $ay, $by;
    return [$ax * $lng + $bx, $ay * mercY($lat) + $by];
}

// Self-check: a projection that cannot reproduce its own calibration points is useless.
foreach ($calib as $i => $p) {
    [$cx, $cy] = project($p['lat'], $p['lng']);
    $dx = abs($cx - $p['x']);
    $dy = abs($cy - $p['y']);
    if ($dx > 3.0 || $dy > 3.0) {
        fail(sprintf('Calibration point %d reproduces %.1f px off — projection is wrong.', $i, max($dx, $dy)));
    }
}
say('Self-check   : all calibration points reproduce within tolerance');

// ---------------------------------------------------------------------------
// 3. Turn the SVG path data into polygons
//
// Every path in this file uses only M/m/L/l/H/h/V/v/Z/z — verified against the
// source. Curves are rejected loudly rather than silently flattened, so if a
// future map file does contain them we find out immediately.
// ---------------------------------------------------------------------------

/** @return array<int, array<int, array{0:float,1:float}>> list of rings of [x,y] */
function parse_path_d(string $d): array
{
    preg_match_all(
        '/([MmLlHhVvZzCcSsQqTtAa])|(-?(?:\d+\.?\d*|\.\d+)(?:[eE][-+]?\d+)?)/',
        $d,
        $m,
        PREG_SET_ORDER
    );

    $rings   = [];
    $ring    = [];
    $cmd     = null;
    $x = $y  = 0.0;      // current point
    $sx = $sy = 0.0;     // subpath start
    $args    = [];
    $expectingMove = true;

    $flush = function () use (&$rings, &$ring) {
        if (count($ring) >= 3) {
            $rings[] = $ring;
        }
        $ring = [];
    };

    $apply = function (string $c, array $a) use (&$x, &$y, &$sx, &$sy, &$ring, &$expectingMove) {
        $rel = ctype_lower($c);
        $up  = strtoupper($c);
        if ($up === 'M') {
            $nx = $rel ? $x + $a[0] : $a[0];
            $ny = $rel ? $y + $a[1] : $a[1];
            $x = $nx; $y = $ny; $sx = $nx; $sy = $ny;
            $ring[] = [$x, $y];
        } elseif ($up === 'L') {
            $x = $rel ? $x + $a[0] : $a[0];
            $y = $rel ? $y + $a[1] : $a[1];
            $ring[] = [$x, $y];
        } elseif ($up === 'H') {
            $x = $rel ? $x + $a[0] : $a[0];
            $ring[] = [$x, $y];
        } elseif ($up === 'V') {
            $y = $rel ? $y + $a[0] : $a[0];
            $ring[] = [$x, $y];
        }
        $expectingMove = false;
    };

    $arity = ['M' => 2, 'L' => 2, 'H' => 1, 'V' => 1, 'C' => 6, 'S' => 4,
              'Q' => 4, 'T' => 2, 'A' => 7];

    foreach ($m as $tok) {
        $letter = $tok[1] ?? '';
        $num    = $tok[2] ?? null;

        if ($letter !== '') {
            $up = strtoupper($letter);
            if ($up === 'Z') {
                $flush();
                $x = $sx; $y = $sy;
                $expectingMove = true;
                $cmd = null;
                continue;
            }
            if (!isset($arity[$up])) {
                fail("Unsupported SVG path command '$letter'.");
            }
            if (in_array($up, ['C', 'S', 'Q', 'T', 'A'], true)) {
                fail("Path uses curve command '$letter'. This script only handles straight "
                   . "line polygons; the map would need flattening first.");
            }
            $cmd = $letter;
            $args = [];
            continue;
        }

        if ($cmd === null) {
            fail('Path data has numbers before any command letter.');
        }
        $args[] = (float) $num;
        $need = $arity[strtoupper($cmd)];

        if (count($args) === $need) {
            $apply($cmd, $args);
            $args = [];
            // A moveto followed by more coordinate pairs means implicit lineto.
            if (strtoupper($cmd) === 'M') {
                $cmd = ctype_lower($cmd) ? 'l' : 'L';
            }
        } elseif (count($args) === 0) {
            // handled above
        }
    }
    $flush();

    if (!$rings) {
        fail('A path produced no rings — parsing went wrong.');
    }
    return $rings;
}

/** Even-odd / ray-casting point-in-polygon over a set of rings. */
function point_in_rings(float $px, float $py, array $rings): bool
{
    $inside = false;
    foreach ($rings as $ring) {
        $n = count($ring);
        for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
            [$xi, $yi] = $ring[$i];
            [$xj, $yj] = $ring[$j];
            if ((($yi > $py) !== ($yj > $py))
                && ($px < ($xj - $xi) * ($py - $yi) / ($yj - $yi) + $xi)) {
                $inside = !$inside;
            }
        }
    }
    return $inside;
}

function rings_bbox(array $rings): array
{
    $minX = $minY = INF;
    $maxX = $maxY = -INF;
    foreach ($rings as $ring) {
        foreach ($ring as [$x, $y]) {
            $minX = min($minX, $x); $maxX = max($maxX, $x);
            $minY = min($minY, $y); $maxY = max($maxY, $y);
        }
    }
    return [$minX, $minY, $maxX, $maxY];
}

$geo = [];
foreach ($muniPaths as $code => $p) {
    $rings = parse_path_d($p['d']);
    $geo[$code] = [
        'd'     => $p['d'],
        'rings' => $rings,
        'bbox'  => rings_bbox($rings),
    ];
}

$totalRings = array_sum(array_map(fn($g) => count($g['rings']), $geo));
say('Geometry     : parsed ' . $totalRings . ' rings across 8 municipalities');

// ---------------------------------------------------------------------------
// 4. Place the localities, deriving each one's municipality from the geometry
// ---------------------------------------------------------------------------

$municipalities = require $ROOT . '/data/municipalities.php';
$muniByCode = [];
foreach ($municipalities as $m) {
    $muniByCode[$m['code']] = $m;
}

// Every code in the geometry needs content, and every content row needs
// geometry: a mismatch below would otherwise fail as an undefined-key warning
// halfway through writing files, leaving a half-built assets/data behind.
$missingContent = array_diff(array_keys($geo), array_keys($muniByCode));
$missingShape   = array_diff(array_keys($muniByCode), array_keys($geo));
if ($missingContent || $missingShape) {
    fail('Code mismatch between the SVG and data/municipalities.php'
       . ($missingContent ? ' — no content for: ' . implode(', ', $missingContent) : '')
       . ($missingShape   ? ' — no geometry for: ' . implode(', ', $missingShape) : ''));
}

$localities = require $ROOT . '/data/localities.php';

$out = [];
$warn = 0;
$outside = [];

foreach ($localities as $loc) {
    [$x, $y] = project($loc['lat'], $loc['lng']);

    $found = null;
    foreach ($geo as $code => $g) {
        if (point_in_rings($x, $y, $g['rings'])) {
            $found = $code;
            break;
        }
    }

    if ($found === null) {
        $outside[] = sprintf('%s (%.4f, %.4f -> %.1f, %.1f)', $loc['name_en'], $loc['lat'], $loc['lng'], $x, $y);
        // Fall back to the hand-written hint so one stray point cannot stop the
        // build — but only if the hint names a real municipality. Silently
        // inventing a code here would produce a locality no checklist can show.
        $hint = $loc['mun_hint'] ?? null;
        if ($hint === null || !isset($geo[$hint])) {
            fail(sprintf('%s (%.4f, %.4f) falls outside every municipality and its '
                       . 'mun_hint %s is not a valid code. Fix the coordinates or the hint.',
                $loc['name_en'], $loc['lat'], $loc['lng'], var_export($hint, true)));
        }
        $found = $hint;
    } elseif (!empty($loc['mun_hint']) && $loc['mun_hint'] !== $found) {
        say(sprintf('  note: %-22s hint %s but geometry says %s -> using %s',
            $loc['name_en'], $loc['mun_hint'], $found, $found));
        $warn++;
    }

    $out[] = [
        'slug'     => $loc['slug'],
        'name_en'  => $loc['name_en'],
        'name_ar'  => $loc['name_ar'],
        'mun'      => $found,
        'lat'      => round($loc['lat'], 6),
        'lng'      => round($loc['lng'], 6),
        'x'        => round($x, 1),
        'y'        => round($y, 1),
        'kind'     => $loc['kind'],
    ];
}

$byMuni = array_fill_keys(array_keys($geo), 0);
foreach ($out as $o) {
    $byMuni[$o['mun']]++;
}
say('Localities   : ' . count($out) . ' placed, ' . $warn . ' hint(s) corrected by geometry');
foreach ($byMuni as $code => $n) {
    say(sprintf('               %-5s %-14s %2d', $code, $muniByCode[$code]['name_en'], $n));
}

if ($outside) {
    say();
    say('  !! These fell outside every municipality boundary and were forced onto their hint:');
    foreach ($outside as $o) {
        say('     - ' . $o);
    }
    say('     Check their lat/lng — a coastal or border point can legitimately fall just outside,');
    say('     but if several do, the coordinates are wrong.');
}

// ---------------------------------------------------------------------------
// 5. Write the runtime files
// ---------------------------------------------------------------------------

$dataDir = $ROOT . '/assets/data';
if (!is_dir($dataDir) && !mkdir($dataDir, 0777, true) && !is_dir($dataDir)) {
    fail("Could not create $dataDir");
}

// --- geo.json ---------------------------------------------------------------
$geoOut = [
    'viewBox'  => '0 0 1000 1000',
    'source'   => 'Simplemaps.com — free for commercial use, attribution appreciated',
    'projection' => ['ax' => $ax, 'bx' => $bx, 'ay' => $ay, 'by' => $by],
    'municipalities' => [],
];

foreach ($geo as $code => $g) {
    $m = $muniByCode[$code];
    [$minX, $minY, $maxX, $maxY] = $g['bbox'];
    $geoOut['municipalities'][$code] = [
        'code'     => $code,
        'slug'     => $m['slug'],
        'name_en'  => $m['name_en'],
        'name_ar'  => $m['name_ar'],
        'alt_en'   => $m['alt_en'],
        'blurb_en' => $m['blurb_en'],
        'blurb_ar' => $m['blurb_ar'],
        'd'        => $g['d'],
        'bbox'     => [round($minX, 1), round($minY, 1), round($maxX, 1), round($maxY, 1)],
        'label'    => isset($labelPts[$code])
            ? ['x' => $labelPts[$code]['x'], 'y' => $labelPts[$code]['y']]
            : ['x' => round(($minX + $maxX) / 2, 1), 'y' => round(($minY + $maxY) / 2, 1)],
        'locality_count' => $byMuni[$code],
    ];
}

write_json($dataDir . '/geo.json', $geoOut);
say();
say('Wrote assets/data/geo.json');

// --- localities.json --------------------------------------------------------
write_json($dataDir . '/localities.json', $out);
say('Wrote assets/data/localities.json');

// --- qatar.svg (a clean, unstyled copy for anyone who wants the raw shapes) --
$svgOut  = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1000 1000" width="1000" height="1000">' . "\n";
$svgOut .= "  <!-- Boundaries from Simplemaps.com - free for commercial use. https://simplemaps.com -->\n";
$svgOut .= '  <g id="municipalities" fill="none" stroke="#ffffff" stroke-width="1">' . "\n";
foreach ($geo as $code => $g) {
    $svgOut .= sprintf(
        '    <path id="%s" data-code="%s" d="%s"/>' . "\n",
        $code,
        $code,
        $g['d']
    );
}
$svgOut .= "  </g>\n</svg>\n";
file_put_contents($dataDir . '/qatar.svg', $svgOut);
say('Wrote assets/data/qatar.svg');

// --- sql/seed.sql -----------------------------------------------------------
$sqlDir = $ROOT . '/sql';
if (!is_dir($sqlDir) && !mkdir($sqlDir, 0777, true) && !is_dir($sqlDir)) {
    fail("Could not create $sqlDir");
}

/**
 * Quote a value for the seed file.
 *
 * MySQL reads a backslash inside a string literal as an escape character, so
 * escaping only the quote would let a value ending in "\" swallow the closing
 * quote and corrupt every row after it. Both need doubling.
 */
$q = fn(?string $s): string => $s === null
    ? 'NULL'
    : "'" . str_replace(['\\', "'"], ['\\\\', "''"], $s) . "'";

// Explicit ids rather than a subquery per row: the ids are ours to hand out
// (the table was just emptied), and an INSERT whose VALUES carry subqueries is
// the kind of thing that imports fine in one MySQL build and not another.
$muniId = [];
foreach ($municipalities as $idx => $m) {
    $muniId[$m['code']] = $idx + 1;
}

$sql  = "-- Generated by setup/prepare.php on " . date('Y-m-d H:i:s') . "\n";
$sql .= "-- Do not edit by hand: edit data/*.php and re-run the script.\n";
$sql .= "-- Replaces every row in municipalities and localities. Saved maps and\n";
$sql .= "-- places are left alone; foreign key checks are off only for the two\n";
$sql .= "-- DELETEs, because places rows point at the municipalities being replaced.\n\n";
$sql .= "SET NAMES utf8mb4;\n";
$sql .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";

$sql .= "DELETE FROM localities;\nDELETE FROM municipalities;\n\n";

// Rows are re-inserted in the same order every time, so resetting the counters
// makes the ids stable across re-seeds. Without this a second run renumbers
// every locality, and saved_map_items rows written against the old ids would
// silently point at the wrong place.
$sql .= "ALTER TABLE municipalities AUTO_INCREMENT = 1;\n";
$sql .= "ALTER TABLE localities AUTO_INCREMENT = 1;\n\n";

$sql .= "INSERT INTO municipalities (id, code, slug, name_en, name_ar, alt_en, blurb_en, blurb_ar, sort_order) VALUES\n";
$rows = [];
foreach ($municipalities as $m) {
    $rows[] = sprintf('  (%d, %s, %s, %s, %s, %s, %s, %s, %d)',
        $muniId[$m['code']], $q($m['code']), $q($m['slug']), $q($m['name_en']), $q($m['name_ar']),
        $q($m['alt_en']), $q($m['blurb_en']), $q($m['blurb_ar']), $m['sort_order']);
}
$sql .= implode(",\n", $rows) . ";\n\n";

$sql .= "INSERT INTO localities (municipality_id, slug, name_en, name_ar, lat, lng, kind, sort_order) VALUES\n";
$rows = [];
$i = 0;
foreach ($out as $o) {
    $i += 10;
    $rows[] = sprintf(
        '  (%d, %s, %s, %s, %.6f, %.6f, %s, %d)',
        $muniId[$o['mun']], $q($o['slug']), $q($o['name_en']), $q($o['name_ar']),
        $o['lat'], $o['lng'], $q($o['kind']), $i
    );
}
$sql .= implode(",\n", $rows) . ";\n\n";

$sql .= "SET FOREIGN_KEY_CHECKS = 1;\n";

file_put_contents($sqlDir . '/seed.sql', $sql);
say('Wrote sql/seed.sql');

// ---------------------------------------------------------------------------
// 6. Places — the guide content
//
// A place names a locality, never a municipality. Its municipality is read back
// from the locality that the geometry already assigned, so the guide page and
// the map can never disagree about which municipality something is in — which
// is the whole reason the locality slug is mandatory here.
//
// Category is resolved by slug in a separate UPDATE rather than by id, so this
// file does not depend on which numbers seed-core.sql happens to hand out, and
// the IDs come out in a stable order so re-seeding does not renumber the guide.
// ---------------------------------------------------------------------------

$places = require $ROOT . '/data/places.php';

$locId  = [];   // locality slug => id, mirroring the insert order above
$locMun = [];   // locality slug => municipality code
foreach ($out as $i => $o) {
    $locId[$o['slug']]  = $i + 1;
    $locMun[$o['slug']] = $o['mun'];
}

$KNOWN_CATS    = ['heritage', 'museum', 'culture', 'nature', 'beach', 'desert', 'park',
                  'souq', 'mall', 'food', 'family', 'sport', 'landmark'];
$KNOWN_FEES    = ['free', 'low', 'medium', 'high'];
$KNOWN_SEASONS = ['all', 'winter', 'summer'];

$SOURCE_NOTE = 'Compiled from public listings; fees and hours change - verify before publishing.';

// The seed deliberately does not set verified_at. Nothing here was checked
// against a primary source, and a false "details verified" badge is worse than
// no badge at all. Fill it in per place once someone has actually checked.
$PLACE_COLS = 'id, municipality_id, locality_id, category_id, slug, name_en, name_ar, '
    . 'summary_en, summary_ar, description_en, description_ar, tips_en, tips_ar, lat, lng, '
    . 'fee_level, duration_mins, best_time_en, best_time_ar, is_hidden_gem, is_featured, '
    . 'family_friendly, metro_access, indoor, season, map_url, official_url, status, '
    . 'source_note, sort_order';

$seenPlace = [];
$placeRows = [];
$byCat     = [];
$byMuniPlace = array_fill_keys(array_keys($geo), 0);
$placeId   = [];   // place slug => id, for the itinerary stops further down
$pid = 0;

foreach ($places as $pl) {
    $pid++;
    $slug = (string) ($pl['slug'] ?? '');

    if ($slug === '') {
        fail('A place in data/places.php has no slug.');
    }
    if (isset($seenPlace[$slug])) {
        fail("Duplicate place slug '$slug' in data/places.php.");
    }
    $seenPlace[$slug] = true;
    $placeId[$slug]   = $pid;

    $locSlug = (string) ($pl['locality'] ?? '');
    if (!isset($locId[$locSlug])) {
        fail("Place '$slug' points at locality '$locSlug', which is not in "
           . 'data/localities.php. Check the spelling.');
    }

    $cat = (string) ($pl['category'] ?? '');
    if (!in_array($cat, $KNOWN_CATS, true)) {
        fail("Place '$slug' has category '$cat'; expected one of: " . implode(', ', $KNOWN_CATS) . '.');
    }

    $fee = (string) ($pl['fee'] ?? 'free');
    if (!in_array($fee, $KNOWN_FEES, true)) {
        fail("Place '$slug' has fee '$fee'; expected one of: " . implode(', ', $KNOWN_FEES) . '.');
    }

    $season = (string) ($pl['season'] ?? 'all');
    if (!in_array($season, $KNOWN_SEASONS, true)) {
        fail("Place '$slug' has season '$season'; expected one of: " . implode(', ', $KNOWN_SEASONS) . '.');
    }

    foreach (['name_en', 'name_ar', 'summary_en', 'summary_ar', 'lat', 'lng'] as $must) {
        if (!isset($pl[$must]) || $pl[$must] === '') {
            fail("Place '$slug' is missing '$must'.");
        }
    }

    $munCode = $locMun[$locSlug];
    $byMuniPlace[$munCode]++;
    $byCat[$cat] = ($byCat[$cat] ?? 0) + 1;

    // The Google Maps link is built from the coordinates rather than written by
    // hand, so it stays correct even when the prose around it goes stale.
    $mapUrl = sprintf('https://www.google.com/maps/search/?api=1&query=%.6f,%.6f',
        $pl['lat'], $pl['lng']);

    $placeRows[] = sprintf(
        '  (%d, %d, %d, NULL, %s, %s, %s, %s, %s, %s, %s, %s, %s, %.6f, %.6f, '
        . '%s, %s, %s, %s, %d, %d, %d, %d, %d, %s, %s, %s, %s, %s, %d)',
        $pid,
        $muniId[$munCode],
        $locId[$locSlug],
        $q($slug), $q($pl['name_en']), $q($pl['name_ar']),
        $q($pl['summary_en']), $q($pl['summary_ar']),
        $q($pl['desc_en'] ?? null), $q($pl['desc_ar'] ?? null),
        $q($pl['tips_en'] ?? null), $q($pl['tips_ar'] ?? null),
        $pl['lat'], $pl['lng'],
        $q($fee), isset($pl['mins']) ? (string) (int) $pl['mins'] : 'NULL',
        $q($pl['best_en'] ?? ''), $q($pl['best_ar'] ?? ''),
        !empty($pl['hidden']) ? 1 : 0,
        0,
        isset($pl['family']) ? (!empty($pl['family']) ? 1 : 0) : 1,
        !empty($pl['metro']) ? 1 : 0,
        !empty($pl['indoor']) ? 1 : 0,
        $q($season),
        $q($mapUrl), $q($pl['site'] ?? ''), $q('published'), $q($SOURCE_NOTE), $pid * 10
    );
}

$psql  = "-- Generated by setup/prepare.php on " . date('Y-m-d H:i:s') . "\n";
$psql .= "-- Do not edit by hand: edit data/places.php and re-run the script.\n";
$psql .= "--\n";
$psql .= "-- IMPORT ORDER MATTERS. The category links at the bottom join against\n";
$psql .= "-- the categories table, so this file must be imported after seed-core.sql:\n";
$psql .= "--\n";
$psql .= "--   1. sql/schema.sql\n";
$psql .= "--   2. sql/seed-core.sql    (categories, tags, levels, badges, themes)\n";
$psql .= "--   3. sql/seed.sql         (municipalities, localities)\n";
$psql .= "--   4. sql/seed-places.sql  (this file)\n";
$psql .= "--   5. sql/seed-itineraries.sql (ready-made plans)\n\n";
$psql .= "SET NAMES utf8mb4;\n";
$psql .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";

// Places cascade to place_tag and place_images, both of which are empty until
// the tagging UI exists, so a straight DELETE is safe here.
$psql .= "DELETE FROM places;\n";
$psql .= "ALTER TABLE places AUTO_INCREMENT = 1;\n";
$psql .= "SET FOREIGN_KEY_CHECKS = 1;\n\n";

$psql .= "INSERT INTO places (" . $PLACE_COLS . ") VALUES\n";
$psql .= implode(",\n", $placeRows) . ";\n\n";

// One UPDATE per category keeps this readable and avoids 60 scalar subqueries.
foreach ($byCat as $cat => $_) {
    $slugs = [];
    foreach ($places as $pl) {
        if ($pl['category'] === $cat) {
            $slugs[] = $q($pl['slug']);
        }
    }
    $psql .= "UPDATE places p JOIN categories c ON c.slug = " . $q($cat) . "\n"
           . "  SET p.category_id = c.id\n"
           . "  WHERE p.slug IN (" . implode(', ', $slugs) . ");\n";
}

$psql .= "\n-- Verified against a primary source? Set verified_at so the page can say so:\n";
$psql .= "-- UPDATE places SET verified_at = CURRENT_DATE WHERE slug = 'museum-islamic-art';\n";

if (file_put_contents($sqlDir . '/seed-places.sql', $psql) === false) {
    fail('Could not write sql/seed-places.sql');
}

say('Wrote sql/seed-places.sql');
say('Places       : ' . count($places) . ' across ' . count($byCat) . ' categories');
foreach ($byMuniPlace as $code => $n) {
    say(sprintf('               %-5s %-14s %2d', $code, $muniByCode[$code]['name_en'], $n));
}

// ---------------------------------------------------------------------------
// 7. Ready-made plans — the itineraries behind স্তর ৩ item ৮
//
// A stop names a place by slug and the id is looked up in $placeId, which was
// built in the same order the places were inserted. So the file can be read and
// corrected by hand without counting ids, and a plan can never point at a place
// that does not exist — the build stops instead.
//
// A stop that carries `text` rather than `place` is not a destination at all
// ("lunch in Al Khor"). It is stored with place_id NULL, which is exactly what
// the panel's own free-text stop does, and the plan page prints it as prose.
//
// Unlike seed-places.sql this file does not turn foreign key checks off: it
// deletes nothing that places depend on, so a mistyped place_id should fail at
// import rather than be written as a broken row.
// ---------------------------------------------------------------------------

$itineraries = require $ROOT . '/data/itineraries.php';

$KNOWN_WHO    = ['family', 'couple', 'budget', 'adventure', 'solo', 'any'];
$KNOWN_BUDGET = ['free', 'low', 'medium', 'high'];

$seenItin  = [];
$itinRows  = [];
$stopRows  = [];
$stopId    = 0;
$byWho     = [];

foreach ($itineraries as $i => $plan) {
    $id   = $i + 1;
    $slug = (string) ($plan['slug'] ?? '');

    if ($slug === '') {
        fail('A plan in data/itineraries.php has no slug.');
    }
    if (isset($seenItin[$slug])) {
        fail("Duplicate plan slug '$slug' in data/itineraries.php.");
    }
    $seenItin[$slug] = true;

    $type  = (string) ($plan['type'] ?? '');
    $value = (int) ($plan['value'] ?? 0);
    if (!in_array($type, ['hours', 'days'], true)) {
        fail("Plan '$slug' has type '$type'; expected 'hours' or 'days'.");
    }
    // The column is TINYINT UNSIGNED and the filter key is a single digit, so a
    // plan longer than 30 of anything could not be linked to anyway.
    if ($value < 1 || $value > 30) {
        fail("Plan '$slug' has value $value; expected 1 to 30.");
    }

    $who = (string) ($plan['who'] ?? 'any');
    if (!in_array($who, $KNOWN_WHO, true)) {
        fail("Plan '$slug' is for '$who'; expected one of: " . implode(', ', $KNOWN_WHO) . '.');
    }

    $budget = (string) ($plan['budget'] ?? 'medium');
    if (!in_array($budget, $KNOWN_BUDGET, true)) {
        fail("Plan '$slug' has budget '$budget'; expected one of: "
           . implode(', ', $KNOWN_BUDGET) . '.');
    }

    foreach (['title_en', 'title_ar', 'intro_en', 'intro_ar'] as $must) {
        if (!isset($plan[$must]) || trim((string) $plan[$must]) === '') {
            fail("Plan '$slug' is missing '$must'. A plan with no title or no intro "
               . 'renders as an empty card, which is worse than not publishing it.');
        }
    }

    $stops = $plan['stops'] ?? null;
    if (!is_array($stops) || $stops === []) {
        fail("Plan '$slug' has no stops. A published plan with an empty route is a "
           . 'card that leads to a page saying so.');
    }

    // sort_order runs across the whole plan rather than restarting each day, so
    // the stored order is a total order and nothing depends on day_number to
    // break a tie.
    $n      = 0;
    $maxDay = 1;

    foreach ($stops as $k => $stop) {
        if (!is_array($stop)) {
            fail("Plan '$slug' stop #" . ($k + 1) . ' is not an array.');
        }
        $n++;

        $day = (int) ($stop['day'] ?? 1);
        if ($day < 1 || $day > 30) {
            fail("Plan '$slug' stop #$n has day $day; expected 1 to 30.");
        }
        if ($type === 'days' && $day > $value) {
            fail("Plan '$slug' runs for $value day(s) but stop #$n is on day $day.");
        }
        $maxDay = max($maxDay, $day);

        $hasPlace = isset($stop['place']) && $stop['place'] !== '';
        $hasText  = isset($stop['text_en']) && trim((string) $stop['text_en']) !== '';

        if ($hasPlace === $hasText) {
            fail("Plan '$slug' stop #$n needs exactly one of 'place' or 'text_en'.");
        }

        if ($hasPlace) {
            $placeSlug = (string) $stop['place'];
            if (!isset($placeId[$placeSlug])) {
                fail("Plan '$slug' stop #$n points at place '$placeSlug', which is not "
                   . 'in data/places.php. Check the spelling.');
            }
            $place = $placeId[$placeSlug];
        } else {
            $place = null;
            if (!isset($stop['text_ar']) || trim((string) $stop['text_ar']) === '') {
                fail("Plan '$slug' stop #$n is a text stop with no 'text_ar'.");
            }
        }

        // A text stop has exactly one prose field to live in, because that is
        // all the panel stores and all the plan page prints — the stop is the
        // sentence. The source file is allowed to split it into a short label
        // and a longer remark, so they are joined here rather than one of them
        // being quietly dropped. Two sentences, so the label has to end with a
        // full stop for the result to read properly.
        $noteEn = '';
        $noteAr = '';
        if ($hasText) {
            $noteEn = trim((string) $stop['text_en']);
            $noteAr = trim((string) $stop['text_ar']);
            $extraEn = trim((string) ($stop['note_en'] ?? ''));
            $extraAr = trim((string) ($stop['note_ar'] ?? ''));
            if ($extraEn !== '') { $noteEn .= ' ' . $extraEn; }
            if ($extraAr !== '') { $noteAr .= ' ' . $extraAr; }
        } else {
            $noteEn = trim((string) ($stop['note_en'] ?? ''));
            $noteAr = trim((string) ($stop['note_ar'] ?? ''));
        }

        $stopId++;
        $stopRows[] = sprintf(
            '  (%d, %d, %s, %d, %d, %s, %s, %s, %s)',
            $stopId,
            $id,
            $place === null ? 'NULL' : (string) $place,
            $day,
            $n * 10,
            $q((string) ($stop['time_en'] ?? '')), $q((string) ($stop['time_ar'] ?? '')),
            $q($noteEn), $q($noteAr)
        );
    }

    if ($type === 'days' && $maxDay !== $value) {
        say("  note: plan '$slug' says $value days but its stops run to day $maxDay.");
    }

    $byWho[$who] = ($byWho[$who] ?? 0) + 1;

    $itinRows[] = sprintf(
        '  (%d, %s, %s, %s, %s, %s, %s, %d, %s, %s, %s, %d)',
        $id,
        $q($slug), $q((string) $plan['title_en']), $q((string) $plan['title_ar']),
        $q((string) $plan['intro_en']), $q((string) $plan['intro_ar']),
        $q($type), $value, $q($who), $q($budget),
        // Published, because a plan written in this file is content, not a draft.
        // verified_at is deliberately absent for the same reason it is absent on
        // places: nobody has checked the hours, the fares or the driving times.
        $q('published'),
        // Spaced out so an editor can slot a plan between two of these without
        // renumbering the rest.
        $id * 10
    );
}

$isql  = "-- Generated by setup/prepare.php on " . date('Y-m-d H:i:s') . "\n";
$isql .= "-- Do not edit by hand: edit data/itineraries.php and re-run the script.\n";
$isql .= "--\n";
$isql .= "-- IMPORT ORDER MATTERS. Every stop resolves a place by id, so this file\n";
$isql .= "-- must be imported after seed-places.sql:\n";
$isql .= "--\n";
$isql .= "--   1. sql/schema.sql\n";
$isql .= "--   2. sql/seed-core.sql    (categories, tags, levels, badges, themes)\n";
$isql .= "--   3. sql/seed.sql         (municipalities, localities)\n";
$isql .= "--   4. sql/seed-places.sql  (places)\n";
$isql .= "--   5. sql/seed-itineraries.sql (this file)\n\n";
$isql .= "SET NAMES utf8mb4;\n\n";

// Stops first, then the plans. Both are replaced wholesale, so re-running the
// script is safe; saved maps and places are untouched. Foreign key checks stay
// on, so a place_id that does not exist fails here rather than at page render.
$isql .= "DELETE FROM itinerary_stops;\n";
$isql .= "DELETE FROM itineraries;\n";
$isql .= "ALTER TABLE itineraries AUTO_INCREMENT = 1;\n";
$isql .= "ALTER TABLE itinerary_stops AUTO_INCREMENT = 1;\n\n";

$isql .= "INSERT INTO itineraries (id, slug, title_en, title_ar, intro_en, intro_ar, "
       . "duration_type, duration_value, audience, budget_level, status, sort_order) VALUES\n";
$isql .= implode(",\n", $itinRows) . ";\n\n";

$isql .= "INSERT INTO itinerary_stops (id, itinerary_id, place_id, day_number, sort_order, "
       . "time_note_en, time_note_ar, note_en, note_ar) VALUES\n";
$isql .= implode(",\n", $stopRows) . ";\n\n";

$isql .= "-- Verified a route against a primary source? Set a date so the page can say so:\n";
$isql .= "-- UPDATE itineraries SET verified_at = CURRENT_DATE WHERE slug = 'doha-in-a-day';\n";

if (file_put_contents($sqlDir . '/seed-itineraries.sql', $isql) === false) {
    fail('Could not write sql/seed-itineraries.sql');
}

say('Wrote sql/seed-itineraries.sql');
say('Plans        : ' . count($itineraries) . ' planned, ' . $stopId . ' stops');
foreach ($byWho as $who => $cnt) {
    say(sprintf('               %-10s %2d', $who, $cnt));
}

// ---------------------------------------------------------------------------

say();
say('Done.');
say();
say('Next: import into MySQL, in this order —');
say('  1. sql/schema.sql   2. sql/seed-core.sql   3. sql/seed.sql');
say('  4. sql/seed-places.sql   5. sql/seed-itineraries.sql');

if (!$isCli) {
    echo '<pre style="font:13px/1.6 ui-monospace,Consolas,monospace;background:#0f1720;color:#d7e3ee;padding:20px;border-radius:10px">'
       . htmlspecialchars(implode("\n", $log))
       . '</pre><p style="font:14px system-ui;padding:0 20px">'
       . '<a href="../index.php">Go to the map &rarr;</a></p>';
}
