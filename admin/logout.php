<?php
/**
 * Sign out.
 *
 * POST only, and CSRF-checked: a GET sign-out can be triggered by an <img> tag
 * on any page, which turns "log everyone out" into a one-line prank.
 */

declare(strict_types=1);

require_once __DIR__ . '/inc/panel.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf'] ?? '';
    if (is_string($token) && csrf_ok($token)) {
        if (admin_user() !== null) {
            log_activity('auth', 'logout', 'admin_user', admin_id());
        }
        admin_forget_session();
    }
}

// The "Signed out." note the login screen shows comes from ?e=out rather than
// from a flash message: the session holding a flash message is the one this
// script just destroyed.
header('Location: ' . APP_BASE . '/admin/login.php?e=out');
exit;
