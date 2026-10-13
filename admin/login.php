<?php
/**
 * Staff sign-in.
 *
 * Deliberately not built on admin_head(): that renders the sidebar, and a
 * sidebar full of links that bounce you straight back here is a worse first
 * impression than a plain box.
 *
 * Two steps when the account has a second factor. The first step verifies the
 * password and leaves a five-minute marker in the session; the second collects
 * the six-digit code. The account being signed in is identified by that session
 * marker, never by anything the form posts.
 */

declare(strict_types=1);

require_once __DIR__ . '/inc/panel.php';
require_once __DIR__ . '/inc/log.php';

// Already signed in? Nothing to do here.
if (admin_user() !== null) {
    header('Location: ' . APP_BASE . '/admin/index.php');
    exit;
}

$error    = '';
$notice   = '';
$email    = '';
$await2fa = (int) ($_SESSION['twofa_id'] ?? 0) > 0;

// The reasons require_admin() redirects with.
$why = get_str('e');
if ($why === 'timeout') {
    $notice = 'You were signed out after ' . ADMIN_IDLE_MINUTES . ' minutes of not being used.';
} elseif ($why === 'out') {
    $notice = 'Signed out.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Checked here rather than through admin_require_csrf(), because that
    // helper's failure page is drawn with the panel chrome — which is exactly
    // what this screen exists to avoid.
    $token = $_POST['csrf'] ?? '';
    if (!is_string($token) || !csrf_ok($token)) {
        $error = 'That form had expired. Please try again.';
    } else {
        $step = post_str('step');

        if ($step === 'code') {
            $r = admin_attempt_2fa(post_str('code'));
            if ($r['ok']) {
                log_activity('auth', 'login', 'admin_user', admin_id());
                header('Location: ' . APP_BASE . '/admin/index.php');
                exit;
            }
            $error    = (string) $r['error'];
            $await2fa = (int) ($_SESSION['twofa_id'] ?? 0) > 0;
        } else {
            $email = post_str('email');
            $r = admin_attempt_login($email, (string) ($_POST['password'] ?? ''));

            if ($r['ok']) {
                log_activity('auth', 'login', 'admin_user', admin_id());
                admin_flash('Welcome back, ' . admin_name() . '.');
                header('Location: ' . APP_BASE . '/admin/index.php');
                exit;
            }

            if (!empty($r['needs_2fa'])) {
                $await2fa = true;
            } else {
                $error = (string) $r['error'];
            }
        }
    }
}

if ($why === 'timeout') { /* already noted above */ }
if (!$await2fa && $error === '' && admin_login_locked()) {
    $error = 'Too many failed attempts from this address. Try again in '
           . ADMIN_LOGIN_LOCK_MINUTES . ' minutes.';
}
?>
<!doctype html>
<html lang="en" dir="ltr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Sign in · <?= e(SITE_NAME) ?> admin</title>
<meta name="robots" content="noindex, nofollow">
<link rel="stylesheet" href="<?= e(admin_asset('css/admin.css')) ?>">
</head>
<body class="ad ad-login">

<main class="ad-login-box">
  <h1><?= e(SITE_NAME) ?> admin</h1>
  <p class="ad-lede">
    <?= $await2fa
        ? 'Enter the six-digit code from your authenticator app.'
        : 'Staff only. This panel is not linked from the public site.' ?>
  </p>

  <?php if ($notice !== ''): ?>
    <p class="ad-note info"><?= e($notice) ?></p>
  <?php endif; ?>
  <?php if ($error !== ''): ?>
    <p class="ad-note bad" role="alert"><?= e($error) ?></p>
  <?php endif; ?>

  <?php if ($await2fa): ?>
    <form method="post" action="<?= e(APP_BASE) ?>/admin/login.php">
      <?= csrf_field() ?>
      <input type="hidden" name="step" value="code">
      <div class="ad-grid">
        <?php
        ad_field([
            'k' => 'code', 't' => 'Authentication code', 'required' => true,
            'mono' => true, 'ph' => '123456',
            'help' => 'Codes rotate every 30 seconds. A code from the step either side is accepted.',
        ], '');
        ?>
      </div>
      <div class="ad-form-act">
        <button class="ad-btn primary" type="submit">Verify and sign in</button>
        <a class="ad-btn ghost" href="<?= e(APP_BASE) ?>/admin/login.php">Start again</a>
      </div>
    </form>
  <?php else: ?>
    <form method="post" action="<?= e(APP_BASE) ?>/admin/login.php">
      <?= csrf_field() ?>
      <div class="ad-grid">
        <?php
        ad_field(['k' => 'email', 't' => 'Email', 'type' => 'email', 'required' => true], $email);
        ad_field(['k' => 'password', 't' => 'Password', 'type' => 'password', 'required' => true], '');
        ?>
      </div>
      <div class="ad-form-act">
        <button class="ad-btn primary" type="submit">Sign in</button>
      </div>
    </form>
  <?php endif; ?>

  <p class="ad-login-foot">
    <a href="<?= e(APP_BASE) ?>/index.php">← Back to the site</a>
  </p>
</main>

<script src="<?= e(admin_asset('js/admin.js')) ?>" defer></script>
</body>
</html>
