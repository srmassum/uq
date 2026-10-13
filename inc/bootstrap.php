<?php
/**
 * Every page starts here. Loads config, database, helpers and translations, then
 * opens the session.
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/settings.php';
require_once __DIR__ . '/i18n.php';

// Output buffering means current_lang() can still send its cookie when it is
// first called from somewhere deep inside a page, after markup has started.
ob_start();

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'path'     => APP_BASE . '/',
    ]);
    session_start();
}

// Baseline security headers. No CSP here on purpose — the map page inlines a
// JSON payload and the export code builds a canvas from it, so a meaningful CSP
// needs its own pass rather than a token one bolted on now.
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('X-Frame-Options: SAMEORIGIN');
