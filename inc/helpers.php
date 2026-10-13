<?php
/**
 * Small shared helpers: escaping, JSON responses, CSRF, short codes, rate limits.
 */

declare(strict_types=1);

// ---------------------------------------------------------------------------
// Output escaping
// ---------------------------------------------------------------------------

/**
 * Escape for HTML output. Use this on anything that came from a user or the
 * database — the shared-map name is the one that matters most, since it is
 * displayed publicly.
 */
function e(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Escape for use inside a JS string literal in an inline <script>. */
function js(mixed $v): string
{
    return json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP);
}

// ---------------------------------------------------------------------------
// JSON responses
// ---------------------------------------------------------------------------

function json_out(mixed $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function json_fail(string $message, int $status = 400, array $extra = []): never
{
    json_out(['ok' => false, 'error' => $message] + $extra, $status);
}

/** Decode a JSON request body. Returns [] for an empty body. */
function json_input(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') {
        return [];
    }
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        json_fail('Request body is not valid JSON.', 400);
    }
    return $data;
}

// ---------------------------------------------------------------------------
// CSRF
// ---------------------------------------------------------------------------

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

/**
 * Constant-time CSRF check. Accepts the token from a JSON body field or a
 * posted form field, so both the API and plain form posts work.
 */
function csrf_ok(?string $token): bool
{
    $expected = $_SESSION['csrf'] ?? '';
    return is_string($token) && $expected !== '' && hash_equals($expected, $token);
}

// ---------------------------------------------------------------------------
// Short codes
// ---------------------------------------------------------------------------

/**
 * Alphabet for share codes.
 *
 * Deliberately not full base62: I, L, O, U, 0 and 1 are left out because people
 * read these codes aloud and retype them from screenshots. 31^7 is still about
 * 27 billion combinations, which is far more than this site will ever need.
 */
const CODE_ALPHABET = '23456789ABCDEFGHJKMNPQRSTVWXYZ';

function make_code(int $len = SHORT_CODE_LEN): string
{
    $max = strlen(CODE_ALPHABET) - 1;
    $out = '';
    for ($i = 0; $i < $len; $i++) {
        $out .= CODE_ALPHABET[random_int(0, $max)];
    }
    return $out;
}

/** Generate a code that is not already taken. */
function make_unique_code(): string
{
    for ($attempt = 0; $attempt < 12; $attempt++) {
        $code = make_code();
        if (qVal('SELECT 1 FROM saved_maps WHERE code = ?', [$code]) === null) {
            return $code;
        }
    }
    // Astronomically unlikely; if it happens the table is almost certainly full
    // or the RNG is broken, and either way we should not loop forever.
    throw new RuntimeException('Could not allocate a unique short code.');
}

// ---------------------------------------------------------------------------
// Visitor identity and rate limiting
// ---------------------------------------------------------------------------

/**
 * The visitor's IP, as seen by the web server.
 *
 * Only REMOTE_ADDR is trusted. X-Forwarded-For is set by the client and can say
 * anything, so it is ignored — if this ever runs behind a proxy, that decision
 * needs revisiting along with the proxy's own header handling.
 */
function client_ip(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/** HMAC the IP so the raw address never lands in the database. */
function ip_hash(): string
{
    return hash_hmac('sha256', client_ip(), IP_HASH_SECRET);
}

/** True if this visitor is still under the save limit. */
function under_rate_limit(int $max = MAP_SAVES_PER_HOUR, int $minutes = 60): bool
{
    $minutes = max(1, $minutes);
    $n = (int) qVal(
        "SELECT COUNT(*) FROM saved_maps
          WHERE ip_hash = ? AND created_at > NOW() - INTERVAL {$minutes} MINUTE",
        [ip_hash()]
    );
    return $n < $max;
}

// ---------------------------------------------------------------------------
// Small utilities
// ---------------------------------------------------------------------------

/** Trim a filename-ish string into a URL slug. Handles Arabic by falling back. */
function slugify(string $s): string
{
    $s = trim(mb_strtolower($s));
    if (function_exists('iconv')) {
        $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
        if ($t !== false && $t !== '') {
            $s = $t;
        }
    }
    $s = preg_replace('/[^a-z0-9]+/u', '-', $s) ?? '';
    return trim($s, '-');
}

/**
 * Clean a user-supplied display name.
 *
 * Strips control characters and zero-width tricks (which are used to make one
 * name render as another, or to hide text from a moderator), collapses
 * whitespace, and caps the length. The result is still untrusted and must go
 * through e() on output.
 */
function clean_display_name(?string $raw): string
{
    if ($raw === null) {
        return '';
    }
    $s = (string) $raw;
    // Control chars, and the bidi/zero-width set: ZWSP, ZWNJ, ZWJ, LRM, RLM,
    // LRE/RLE/PDF/LRO/RLO and the isolates.
    $s = preg_replace('/[\x00-\x1F\x7F]|\x{200B}-\x{200F}|\x{202A}-\x{202E}|\x{2066}-\x{2069}/u', '', $s) ?? '';
    $s = preg_replace('/\s+/u', ' ', $s) ?? '';
    $s = trim($s);
    if (mb_strlen($s) > MAX_NAME_LEN) {
        $s = mb_substr($s, 0, MAX_NAME_LEN);
    }
    return $s;
}

/** True if the bytes start with the PNG signature. */
function looks_like_an_image(string $bytes): bool
{
    return str_starts_with($bytes, "\x89PNG\r\n\x1a\n");
}

/** Human-readable byte size. */
function human_bytes(int $n): string
{
    if ($n < 1024) return $n . ' B';
    if ($n < 1048576) return round($n / 1024, 1) . ' KB';
    return round($n / 1048576, 1) . ' MB';
}

/** Cache-busting asset URL based on file mtime. */
function asset(string $path): string
{
    $path = ltrim($path, '/');
    $full = dirname(__DIR__) . '/' . $path;
    $v = is_file($full) ? filemtime($full) : 0;
    return APP_BASE . '/' . $path . ($v ? '?v=' . $v : '');
}

// ---------------------------------------------------------------------------
// Appearance
// ---------------------------------------------------------------------------

/**
 * The localStorage key holding an explicit light/dark choice.
 *
 * Not the same thing as the card theme on the map, which is a row in
 * share_themes and decides what colour the exported map is drawn in. This one
 * decides what colour the site itself is drawn in, and the two never read each
 * other.
 *
 * No cookie and no server side: the choice belongs to the browser, so the page
 * stays cacheable and the server never learns anything about a visitor.
 */
const THEME_KEY = 'qatar-theme-v1';

/** The two values the attribute and the stored key are allowed to take. */
const THEME_CHOICES = ['light', 'dark'];

