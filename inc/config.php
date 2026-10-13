<?php
/**
 * Site configuration.
 *
 * These are XAMPP defaults. If you put this online, change the database
 * credentials here and — importantly — move this file out of the web root or
 * block it in .htaccess, since it holds the DB password.
 */

declare(strict_types=1);

// ---------------------------------------------------------------------------
// Database
// ---------------------------------------------------------------------------
const DB_HOST = 'localhost';
const DB_PORT = 3306;
const DB_NAME = 'explore_qatar';
const DB_USER = 'massum';
const DB_PASS = 'MasumAhmed@10';          // XAMPP default is an empty root password
const DB_CHARSET = 'utf8mb4';

// ---------------------------------------------------------------------------
// Site
// ---------------------------------------------------------------------------
const SITE_NAME = 'Qatar Unseen';
const SITE_TAGLINE = 'How much of Qatar have you actually seen?';

/** Languages the UI ships with. Adding one means adding inc/lang/<code>.php. */
const SUPPORTED_LANGS = ['en', 'ar'];
const DEFAULT_LANG = 'en';
const RTL_LANGS = ['ar'];

/** Where the app lives relative to the web root, e.g. '/eq'. */
const APP_BASE = '/eq';

/** Public origin, used to build absolute URLs for og:image and share links. */
function site_origin(): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return ($https ? 'https://' : 'http://') . $host;
}

function site_url(string $path = ''): string
{
    return site_origin() . APP_BASE . ($path === '' ? '/' : '/' . ltrim($path, '/'));
}

// ---------------------------------------------------------------------------
// Limits and safety rails
// ---------------------------------------------------------------------------
const MAX_SNAPSHOT_BYTES   = 409600;   // 400 KB per saved card
const MAX_NAME_LEN         = 48;
const MAP_SAVES_PER_HOUR   = 10;
const SHORT_CODE_LEN       = 7;

/** Secret for HMAC-ing IP addresses before they touch the database. */
const IP_HASH_SECRET = 'change-this-to-a-long-random-string';

// ---------------------------------------------------------------------------
// Admin panel
// ---------------------------------------------------------------------------

/** Idle minutes before an admin session is thrown away and login is asked for. */
const ADMIN_IDLE_MINUTES = 120;

/** Failed sign-ins from one address before it is locked out. */
const ADMIN_LOGIN_MAX_FAILS = 5;
const ADMIN_LOGIN_LOCK_MINUTES = 15;

/** Where uploaded images land, relative to the web root of the app. */
const UPLOAD_DIR = 'uploads';
const MAX_UPLOAD_BYTES = 4194304;   // 4 MB per image, before resizing
const MAX_IMAGE_EDGE = 1600;        // longest edge kept on the stored copy

// ---------------------------------------------------------------------------
// Errors — show them while developing, hide them in production.
// ---------------------------------------------------------------------------
const DEBUG = true;

if (DEBUG) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(E_ALL & ~E_DEPRECATED);
}

date_default_timezone_set('Asia/Qatar');
mb_internal_encoding('UTF-8');
