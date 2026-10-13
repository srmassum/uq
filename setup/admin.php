<?php
/**
 * First run: create the one account that can open the panel.
 *
 * Not part of admin/ because it has to work before any account exists — the
 * panel refuses everyone until there is somebody to sign in as. It also refuses
 * to do anything the moment admin_users has a row, so it is not a second door
 * into the site afterwards: it is a door that closes itself.
 *
 * What it is defending against is the window between putting the site online and
 * creating the first account. In that window anyone who found this URL could
 * make themselves an admin, and a CSRF token is no help — there is no session to
 * tie it to and nobody to be signed in as. So the page asks for a key that only
 * exists in the server's filesystem, which is what real installers do: they tell
 * you a path, and you go and read it.
 *
 * The key is written to the system temp directory, NOT next to this file. A key
 * sitting in setup/ would be handed to anyone who requested its URL, which would
 * make the whole check decorative.
 *
 * Delete this file — and setup/ with it — when you are done. The panel keeps
 * saying so until you do.
 */

declare(strict_types=1);

require_once __DIR__ . '/../admin/inc/panel.php';
require_once __DIR__ . '/../admin/inc/user-def.php';

header('X-Robots-Tag: noindex, nofollow');

/**
 * The setup key, generated the first time this page is opened.
 *
 * @return array{path: string, key: string, error: string}
 */
function setup_key(): array
{
    $path = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'eq-setup-key.txt';

    if (!is_file($path)) {
        $key = bin2hex(random_bytes(16));
        if (@file_put_contents($path, $key . "\n") === false) {
            return ['path' => $path, 'key' => '', 'error' => 'The key file could not be written'];
        }
        // Everyone on the machine can read a temp file. Narrowing it to the
        // owner is the most this can do, and on Windows chmod is a no-op — the
        // @ is there for that, not because the result is unimportant.
        @chmod($path, 0600);
        return ['path' => $path, 'key' => $key, 'error' => ''];
    }

    $key = trim((string) @file_get_contents($path));
    if ($key === '') {
        return ['path' => $path, 'key' => '', 'error' => 'The key file exists but is empty'];
    }

    return ['path' => $path, 'key' => $key, 'error' => ''];
}

/**
 * Why this password should not open the account that can do anything.
 *
 * Not a strength meter — a short list of the passwords that would be tried
 * first, plus the one test that catches most of the rest. This account can read
 * every message people send, change the site's copy, and take a copy of the
 * database; the commonest way such an account is lost is someone choosing one of
 * these.
 *
 * @return string '' when the password is fine.
 */
function setup_password_complaint(string $pass, string $email): string
{
    if (mb_strlen($pass) < 10) {
        return 'Use at least 10 characters.';
    }

    $lower = mb_strtolower($pass);
    $weak  = ['password', 'password1', 'password123', '1234567890', 'qwertyuiop',
              'admin12345', 'letmein123', 'qatar12345', 'welcome123', 'changeme12'];

    if (in_array($lower, $weak, true)) {
        return 'That is one of the first passwords anybody would try. Pick something else.';
    }

    $at    = strpos($email, '@');
    $local = mb_strtolower($at === false ? $email : substr($email, 0, $at));
    if ($local !== '' && mb_strlen($local) >= 4 && str_contains($lower, $local)) {
        return 'That contains the email address. Pick something unrelated to it.';
    }

    return '';
}

// ---------------------------------------------------------------------------
// State
// ---------------------------------------------------------------------------

$dbError = '';
$count   = 0;
try {
    $count = (int) qVal('SELECT COUNT(*) FROM admin_users');
} catch (Throwable $e) {
    error_log('setup/admin: ' . $e->getMessage());
    $dbError = $e->getMessage();
}

$key    = setup_key();
$errors = [];
$done   = false;

if ($dbError === '' && $count === 0 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf'] ?? '';

    if (!is_string($token) || !csrf_ok($token)) {
        $errors['_form'] = 'That form had expired. Please try again.';
    } elseif ($key['key'] === '' || !hash_equals($key['key'], post_str('key'))) {
        $errors['key'] = 'That is not the key in the file. Copy the whole line, with no spaces around it.';
    } else {
        // The spec names columns, not form fields, so the password box posts as
        // password_hash — the name of the column it ends up in.
        $pass  = (string) ($_POST['password_hash'] ?? '');
        $email = post_str('email');
        $pass2 = (string) ($_POST['password2'] ?? '');
        $moan  = setup_password_complaint($pass, $email);

        if ($pass !== $pass2) {
            $errors['password2'] = 'The two passwords are not the same.';
        } elseif ($moan !== '') {
            $errors['password_hash'] = $moan;
        } else {
            // role and is_active are posted as hidden fields on purpose:
            // record_save() writes 0 for a bool the form does not mention, which
            // would produce an admin account that cannot sign in.
            $res = record_save(user_spec(), $_POST, null);

            if ($res['ok']) {
                $done = true;
                log_activity('setup', 'admin_create', 'admin_user', (int) $res['id'], null,
                    ['note' => 'first account, created by setup/admin.php']);
            } else {
                $errors = $res['errors'];
            }
        }
    }
}

$keyPath = $key['path'];
?>
<!doctype html>
<html lang="en" dir="ltr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>First run · <?= e(SITE_NAME) ?> admin</title>
<meta name="robots" content="noindex, nofollow">
<link rel="stylesheet" href="<?= e(admin_asset('css/admin.css')) ?>">
</head>
<body class="ad ad-login">

<main class="ad-login-box">
  <h1>First run</h1>

<?php if ($done): ?>

  <p class="ad-lede">The account exists. Nothing else on this page can run again.</p>

  <p class="ad-note ok">
    Created. <a href="<?= e(APP_BASE) ?>/admin/login.php">Sign in</a> with the email and password you just set.
  </p>

  <h2 style="font-size:1rem;margin-block-start:22px">Now delete this file</h2>
  <p>
    <code>setup/admin.php</code> cannot create a second account — it checks that the accounts table is
    empty — but it has no reason to stay on the server, and neither has the rest of <code>setup/</code>.
    Delete the whole folder.
  </p>
  <p>
    Then delete the key file too, since nothing will ask for it again:
  </p>
  <p><code style="overflow-wrap:anywhere"><?= e($keyPath) ?></code></p>

  <h2 style="font-size:1rem;margin-block-start:22px">Three things worth doing before you publish</h2>
  <ul class="ad-list">
    <li>
      <span>Change the database password</span>
      <span class="ad-muted"><code>inc/config.php</code> ships with a placeholder; so does <code>IP_HASH_SECRET</code></span>
    </li>
    <li>
      <span>Turn off <code>DEBUG</code></span>
      <span class="ad-muted">the same file — it prints PHP errors into the page while it is on</span>
    </li>
    <li>
      <span>Add a second admin</span>
      <span class="ad-muted">one account is one forgotten password away from a database edit</span>
    </li>
  </ul>

<?php elseif ($dbError !== ''): ?>

  <p class="ad-lede">The database is not ready.</p>
  <p class="ad-note bad"><?= e($dbError) ?></p>
  <p>
    Import <code>sql/schema.sql</code> (then the seed files) from phpMyAdmin, and check that the
    database name, user and password in <code>inc/config.php</code> match what you created.
  </p>

<?php elseif ($count > 0): ?>

  <p class="ad-lede">This site has already been set up.</p>
  <p class="ad-note info">
    There is at least one admin account, so this page will not create another — a page that adds admins on
    request would be a way in for anybody who found the URL.
  </p>
  <p>
    Add more accounts from <a href="<?= e(APP_BASE) ?>/admin/users.php">Admin users</a> once you are
    signed in. And if you have not already, delete <code>setup/</code>.
  </p>
  <p style="margin-block-start:20px">
    <a class="ad-btn primary" href="<?= e(APP_BASE) ?>/admin/login.php">Go to sign in</a>
  </p>

<?php else: ?>

  <p class="ad-lede">
    Create the first admin account. This is the only thing this page can do, and it will refuse to do it
    again afterwards.
  </p>

  <?php if (isset($errors['_form'])): ?>
    <p class="ad-note bad" role="alert"><?= e($errors['_form']) ?></p>
  <?php endif; ?>
  <?php if (isset($errors['_email']) || isset($errors['email'])): ?>
    <p class="ad-note bad" role="alert"><?= e($errors['email'] ?? $errors['_email']) ?></p>
  <?php endif; ?>

  <div class="ad-note warn">
    <b>The key.</b> A random key has been written to this file on the server:
    <br>
    <code style="overflow-wrap:anywhere"><?= e($keyPath) ?></code>
    <br><br>
    <?php if ($key['error'] !== ''): ?>
      <?= e($key['error']) ?> — create it yourself with any long random line in it, then reload this page.
    <?php else: ?>
      Open it (Notepad will do), copy the line inside, and paste it below. This is what stops somebody who
      guesses this URL from making themselves an admin before you get here.
      <br><br>
      On a server you share with other people, replace the line in that file with one of your own first —
      a temp folder is readable by the other accounts on the machine.
    <?php endif; ?>
  </div>

  <form method="post" action="<?= e(APP_BASE) ?>/setup/admin.php">
    <?= csrf_field() ?>
    <input type="hidden" name="role" value="admin">
    <input type="hidden" name="is_active" value="1">
    <div class="ad-grid">
      <?php
      ad_field([
          'k' => 'email', 't' => 'Email', 'type' => 'email', 'required' => true, 'wide' => true,
          'help' => 'The sign-in name. There is no password-reset email on this site, so keep it somewhere you will find it again.',
      ], post_str('email'));

      ad_field(['k' => 'name', 't' => 'Your name', 'maxlen' => 96, 'wide' => true], post_str('name'));

      ad_field([
          'k' => 'key', 't' => 'Setup key', 'required' => true, 'wide' => true, 'mono' => true,
          'help' => 'The line from the file above. Once an account exists this is never asked for again.',
      ], '');

      ad_field([
          'k' => 'password_hash', 't' => 'Password', 'type' => 'password', 'required' => true,
          'help' => 'At least 10 characters. The field is named after the column it is stored in, not after itself.',
      ], '');
      ad_field([
          'k' => 'password2', 't' => 'Password again', 'type' => 'password', 'required' => true,
      ], '');
      ?>
    </div>

    <?php foreach (['key', 'password_hash', 'password2'] as $f): ?>
      <?php if (isset($errors[$f])): ?>
        <p class="ad-err"><?= e($errors[$f]) ?></p>
      <?php endif; ?>
    <?php endforeach; ?>

    <div class="ad-form-act">
      <button class="ad-btn primary" type="submit">Create the account</button>
      <a class="ad-btn ghost" href="<?= e(APP_BASE) ?>/index.php">Back to the site</a>
    </div>
  </form>

  <p class="ad-login-foot">
    Admin users are never created from the public site. This page exists only because the panel needs one
    account before it can open.
  </p>

<?php endif; ?>

</main>

<script src="<?= e(admin_asset('js/admin.js')) ?>" defer></script>
</body>
</html>
