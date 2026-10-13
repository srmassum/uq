<?php
/**
 * Tiny translation layer.
 *
 * UI strings live in inc/lang/<code>.php as plain arrays. Content strings
 * (place names, descriptions) come from the database's _en/_ar column pairs, so
 * this layer only ever handles chrome.
 */

declare(strict_types=1);

function current_lang(): string
{
    static $lang = null;
    if ($lang !== null) {
        return $lang;
    }

    // ?lang=xx wins, and sticks via a cookie so links stay clean afterwards.
    if (isset($_GET['lang']) && in_array($_GET['lang'], SUPPORTED_LANGS, true)) {
        $lang = (string) $_GET['lang'];
        if (!headers_sent()) {
            setcookie('lang', $lang, [
                'expires'  => time() + 31536000,
                'path'     => APP_BASE . '/',
                'samesite' => 'Lax',
            ]);
        }
        return $lang;
    }

    if (isset($_COOKIE['lang']) && in_array($_COOKIE['lang'], SUPPORTED_LANGS, true)) {
        $lang = (string) $_COOKIE['lang'];
        return $lang;
    }

    // What the Settings screen says the site's first language is, falling back to
    // the constant when the setting is blank or names a language that has no
    // inc/lang file.
    $pref = setting('site.default_lang');
    $lang = in_array($pref, SUPPORTED_LANGS, true) ? $pref : DEFAULT_LANG;

    return $lang;
}

function is_rtl(): bool
{
    return in_array(current_lang(), RTL_LANGS, true);
}

function html_dir(): string
{
    return is_rtl() ? 'rtl' : 'ltr';
}

/**
 * All loaded UI strings for the current language.
 *
 * Two sources, in order: the language file that ships with the code, then any
 * rows in the translations table for this language. The database layer is what
 * makes the Translations screen real — a translator can fix a phrase, or supply
 * a whole language, without a file ever being uploaded.
 *
 * It also means a language with no file at all still works: t() falls back to
 * English for any key it cannot find, so a half-translated language reads as
 * English in the gaps rather than as blank space or as the raw key.
 *
 * The cost is one small indexed query per request, on top of the one
 * settings_all() already makes. Both are primary-key lookups on tiny tables.
 */
function strings(): array
{
    static $cache = [];
    $lang = current_lang();

    if (isset($cache[$lang])) {
        return $cache[$lang];
    }

    $file = __DIR__ . '/lang/' . $lang . '.php';
    $out  = is_file($file) ? (array) require $file : [];

    try {
        foreach (qAll(
            "SELECT field, value FROM translations
              WHERE entity_type = 'ui' AND entity_id = 0 AND lang = ?",
            [$lang]
        ) as $row) {
            $key = (string) $row['field'];
            // A blank row means "no translation yet", not "blank this string" —
            // otherwise clearing a box in the panel would erase a phrase.
            if ($key !== '' && (string) $row['value'] !== '') {
                $out[$key] = (string) $row['value'];
            }
        }
    } catch (Throwable $e) {
        // No translations table yet: the shipped file is a complete language.
        error_log('strings: ' . $e->getMessage());
    }

    return $cache[$lang] = $out;
}

/**
 * Translate a key. Missing keys fall back to English, then to the key itself,
 * so a half-finished translation degrades to English rather than to blank space.
 */
function t(string $key, array $repl = []): string
{
    $all = strings();
    $s = $all[$key] ?? null;

    if ($s === null) {
        $en = __DIR__ . '/lang/en.php';
        static $fallback = null;
        if ($fallback === null) {
            $fallback = is_file($en) ? (array) require $en : [];
        }
        $s = $fallback[$key] ?? $key;
    }

    // Call sites write both forms — ['n' => 3] and [':n' => 3] — and both have
    // to work. Only the bare form ever did: this loop prepends the colon itself,
    // so a caller passing ':n' had it look for '::n' and the placeholder went to
    // the page untouched. Normalising here fixes the whole class of bug once,
    // instead of at every call site. Longest key first, so :total is replaced
    // before a shorter :t could eat the front of it.
    $keys = array_keys($repl);
    usort($keys, static fn($a, $b): int => strlen((string) $b) <=> strlen((string) $a));

    foreach ($keys as $k) {
        $s = str_replace(':' . ltrim((string) $k, ':'), (string) $repl[$k], $s);
    }

    return $s;
}

/** Pick the right column for the current language from a row with _en/_ar fields. */
function pick(array $row, string $base): string
{
    $lang = current_lang();
    $val = $row[$base . '_' . $lang] ?? '';
    if ($val === '' || $val === null) {
        $val = $row[$base . '_en'] ?? '';
    }
    return (string) $val;
}

/** Numerals: Arabic-Indic digits when reading Arabic. */
function num(int|float|string $n): string
{
    $s = (string) $n;
    if (current_lang() !== 'ar') {
        return $s;
    }
    return strtr($s, ['0'=>'٠','1'=>'١','2'=>'٢','3'=>'٣','4'=>'٤','5'=>'٥','6'=>'٦','7'=>'٧','8'=>'٨','9'=>'٩']);
}

/** Language switcher links, preserving the current query string. */
function lang_switch_url(string $code): string
{
    $q = $_GET;
    $q['lang'] = $code;
    return strtok($_SERVER['REQUEST_URI'] ?? '/', '?') . '?' . http_build_query($q);
}

/**
 * The current request as a path relative to the app, with the query stripped.
 *
 * REQUEST_URI carries APP_BASE, and every URL this site builds is relative to
 * it, so the two have to be reconciled somewhere. This is that place.
 */
function current_path(): string
{
    $path = (string) strtok((string) ($_SERVER['REQUEST_URI'] ?? '/'), '?');

    if (APP_BASE !== '' && str_starts_with($path, APP_BASE)) {
        $path = substr($path, strlen(APP_BASE));
    }

    return ltrim($path, '/');
}

/**
 * One page, in one language, as an absolute URL.
 *
 * The language is not part of the path on this site — it is a cookie, with
 * ?lang= as the way to set it — so English and Arabic would otherwise share a
 * single URL. That is fine for a person and useless for a crawler: it arrives
 * with no cookie, gets the default language, and the other language is then
 * invisible to search no matter how good the translation is. Writing the
 * language into the URL is what makes the second one indexable at all, and it
 * is what makes the hreflang links in page_head true rather than decorative.
 *
 * Takes a relative URL — 'place.php?slug=x' — and returns it absolute with lang
 * pinned first. The remaining parameters are sorted so that two spellings of
 * the same query cannot produce two canonicals for one page.
 */
function lang_url(string $code, string $rel): string
{
    $path  = (string) strtok($rel, '?');
    $tail  = str_contains($rel, '?') ? substr($rel, strpos($rel, '?') + 1) : '';
    $query = [];
    parse_str($tail, $query);

    unset($query['lang']);
    ksort($query);

    // Union rather than assignment: the left operand keeps its position, so lang
    // is always the first parameter.
    return site_url($path) . '?' . http_build_query(['lang' => $code] + $query);
}
