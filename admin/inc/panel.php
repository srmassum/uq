<?php
/**
 * The admin panel's single entry point. Every screen in admin/ starts with:
 *
 *     require __DIR__ . '/inc/panel.php';
 *
 * Loaded in one place so the order is fixed and no screen has to remember which
 * four files it needs. auth.php before ui.php, because the chrome asks who is
 * signed in; records.php last, because it logs through log.php.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../inc/bootstrap.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/log.php';
require_once __DIR__ . '/ui.php';
require_once __DIR__ . '/records.php';
// simple.php builds a whole list-and-edit screen from a declaration; seven of
// the modules are nothing but such a declaration.
require_once __DIR__ . '/simple.php';
