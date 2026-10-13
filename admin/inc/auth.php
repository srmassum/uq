<?php
/**
 * Admin authentication.
 *
 * There are no public accounts on this site — the only logins are staff — so
 * this is deliberately small: password hashes from password_hash(), a session
 * that carries the admin id, an idle timeout, a per-address throttle, and an
 * optional TOTP second factor for anyone who sets one up.
 *
 * Nothing here trusts the session cookie's contents beyond the admin id: the
 * role and the active flag are re-read from the database on every request, so
 * deactivating an account takes effect on that person's next click rather than
 * whenever their session happens to expire.
 */

declare(strict_types=1);

/** The signed-in admin's row, or null. Re-read once per request, then cached. */
function admin_user(): ?array
{
    static $user = false;   // false = not looked up yet, null = not signed in

    if ($user !== false) {
        return $user;
    }

    $id = $_SESSION['admin_id'] ?? null;
    if (!is_int($id) && !ctype_digit((string) $id)) {
        return $user = null;
    }

    try {
        $row = qOne(
            'SELECT id, email, name, role, totp_secret, is_active, last_login_at
               FROM admin_users WHERE id = ?',
            [(int) $id]
        );
    } catch (Throwable $e) {
        error_log('admin_user: ' . $e->getMessage());
        return $user = null;
    }

    if ($row === null || !(int) $row['is_active']) {
        admin_forget_session();
        return $user = null;
    }

    return $user = $row;
}

function admin_id(): int
{
    return (int) (admin_user()['id'] ?? 0);
}

function admin_name(): string
{
    $u = admin_user();
    return $u === null ? '' : (trim((string) $u['name']) !== '' ? (string) $u['name'] : (string) $u['email']);
}

function admin_role(): string
{
    return (string) (admin_user()['role'] ?? '');
}

/** Only a full admin may touch other admins, settings, or anything destructive. */
function admin_is_admin(): bool
{
    return admin_role() === 'admin';
}

/**
 * Capability check, so a template never has to compare role strings itself.
 *
 * Editors write content. Admins additionally manage people, settings and the
 * destructive operations. `delete` is admin-only on purpose: soft delete is
 * recoverable, but the restore-or-purge screen is where damage gets done.
 */
function admin_can(string $what): bool
{
    if (admin_user() === null) {
        return false;
    }
    if (admin_is_admin()) {
        return true;
    }

    return in_array($what, [
        'content.edit', 'content.publish', 'media.upload', 'feedback.triage',
        'itinerary.edit', 'collection.edit', 'guide.edit',
    ], true);
}

/** Stop the request unless someone is signed in (and optionally capable). */
function require_admin(?string $what = null): void
{
    if (admin_user() === null) {
        admin_redirect_login();
    }
    // Idle timeout. Checked here rather than in a cron because there is no cron:
    // the check has to happen on a request, and this is the only place every
    // admin request passes through.
    if (admin_idle_expired()) {
        admin_forget_session();
        admin_redirect_login('timeout');
    }

    $_SESSION['admin_seen'] = time();

    // The refusal page is its own file rather than markup inlined here, so this
    // file stays render-free and the panel chrome lives in exactly one place.
    if ($what !== null && !admin_can($what)) {
        header('Location: ' . APP_BASE . '/admin/denied.php?need=' . urlencode($what));
        exit;
    }
}

function admin_idle_expired(): bool
{
    $seen = $_SESSION['admin_seen'] ?? null;
    return is_int($seen) && (time() - $seen) > ADMIN_IDLE_MINUTES * 60;
}

function admin_redirect_login(string $why = ''): never
{
    $to = APP_BASE . '/admin/login.php' . ($why !== '' ? '?e=' . urlencode($why) : '');
    header('Location: ' . $to);
    exit;
}

/** Drop every trace of the session, then rotate the cookie. */
function admin_forget_session(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires'  => time() - 42000,
            'path'     => $p['path'],
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
    session_destroy();
}

// ---------------------------------------------------------------------------
// Signing in
// ---------------------------------------------------------------------------

/** Recent failures from this address, used for the lockout. */
function admin_fail_count(int $minutes = ADMIN_LOGIN_LOCK_MINUTES): int
{
    // The interval is interpolated from an int rather than bound as a
    // placeholder: with emulated prepares off, PDO sends the value as a string
    // and MySQL refuses a string as an INTERVAL quantity. Same idiom as
    // under_rate_limit() in inc/helpers.php.
    $minutes = max(1, $minutes);
    try {
        return (int) qVal(
            "SELECT COUNT(*) FROM admin_login_attempts
              WHERE ip_hash = ? AND ok = 0 AND created_at > NOW() - INTERVAL {$minutes} MINUTE",
            [ip_hash()]
        );
    } catch (Throwable $e) {
        // If the log table is missing we must not lock everyone out of the
        // panel — fail open on the throttle, closed on the password.
        error_log('admin_fail_count: ' . $e->getMessage());
        return 0;
    }
}

function admin_login_locked(): bool
{
    return admin_fail_count() >= ADMIN_LOGIN_MAX_FAILS;
}

function admin_log_attempt(string $email, bool $ok, string $reason = ''): void
{
    try {
        q('INSERT INTO admin_login_attempts (email, ip_hash, ok, reason) VALUES (?, ?, ?, ?)',
          [mb_substr($email, 0, 160), ip_hash(), $ok ? 1 : 0, $reason]);
    } catch (Throwable $e) {
        error_log('admin_log_attempt: ' . $e->getMessage());
    }
}

/**
 * Check an email and password. An account with 2FA stops half-way and reports
 * needs_2fa — the caller then collects a code for admin_attempt_2fa().
 *
 * @return array{ok: bool, error: string, needs_2fa: bool}
 */
function admin_attempt_login(string $email, string $password): array
{
    $fail = static fn(string $msg): array => ['ok' => false, 'error' => $msg, 'needs_2fa' => false];

    if (admin_login_locked()) {
        admin_log_attempt($email, false, 'locked');
        return $fail('Too many failed attempts from this address. Try again in '
                     . ADMIN_LOGIN_LOCK_MINUTES . ' minutes.');
    }

    $row = null;
    try {
        $row = qOne('SELECT * FROM admin_users WHERE email = ?', [mb_substr($email, 0, 160)]);
    } catch (Throwable $e) {
        error_log('admin_attempt_login: ' . $e->getMessage());
        return $fail('The accounts table is not reachable. Has schema.sql been imported?');
    }

    // One generic message for both wrong-email and wrong-password: telling them
    // apart is how an attacker enumerates which addresses have accounts.
    if ($row === null || !password_verify($password, (string) $row['password_hash'])) {
        admin_log_attempt($email, false, $row === null ? 'unknown_email' : 'bad_password');
        return $fail('Wrong email or password.');
    }

    if (!(int) $row['is_active']) {
        admin_log_attempt($email, false, 'inactive');
        return $fail('That account has been switched off.');
    }

    // Second factor. Whether 2FA is on is a property of the account, so a row
    // with a secret never finishes signing in from this function — it hands off
    // to admin_attempt_2fa(), which is the only place that completes it.
    if ((string) ($row['totp_secret'] ?? '') !== '') {
        session_regenerate_id(true);
        $_SESSION['twofa_id']   = (int) $row['id'];
        $_SESSION['twofa_when'] = time();
        return ['ok' => false, 'error' => '', 'needs_2fa' => true];
    }

    admin_finish_login($row);
    return ['ok' => true, 'error' => '', 'needs_2fa' => false];
}

/**
 * The second half of a two-factor sign-in.
 *
 * The account id comes from the session, never from the form: that is what
 * proves the password was already verified. Nothing a submitter can type here
 * can point the check at a different account.
 */
function admin_attempt_2fa(string $code): array
{
    $id   = (int) ($_SESSION['twofa_id'] ?? 0);
    $when = (int) ($_SESSION['twofa_when'] ?? 0);

    // The half-finished sign-in is good for five minutes: long enough to open
    // the authenticator app, short enough that a browser left open on a shared
    // machine is not a standing invitation.
    if ($id <= 0 || (time() - $when) > 300) {
        unset($_SESSION['twofa_id'], $_SESSION['twofa_when']);
        return ['ok' => false, 'error' => 'That took too long. Please sign in again.'];
    }

    if (admin_login_locked()) {
        return ['ok' => false, 'error' => 'Too many failed attempts from this address.'];
    }

    try {
        $row = qOne('SELECT id, email, name, role, totp_secret, is_active
                       FROM admin_users WHERE id = ?', [$id]);
    } catch (Throwable $e) {
        error_log('admin_attempt_2fa: ' . $e->getMessage());
        return ['ok' => false, 'error' => 'Could not reach the accounts table.'];
    }

    if ($row === null || !(int) $row['is_active'] || (string) $row['totp_secret'] === '') {
        unset($_SESSION['twofa_id'], $_SESSION['twofa_when']);
        return ['ok' => false, 'error' => 'Please sign in again.'];
    }

    if (!totp_verify((string) $row['totp_secret'], $code)) {
        admin_log_attempt((string) $row['email'], false, '2fa');
        return ['ok' => false, 'error' => 'That code is not right. Codes change every 30 seconds.'];
    }

    admin_finish_login($row);
    return ['ok' => true, 'error' => ''];
}

/** Establish the session for a fully verified account. */
function admin_finish_login(array $row): void
{
    session_regenerate_id(true);
    $_SESSION['admin_id']   = (int) $row['id'];
    $_SESSION['admin_seen'] = time();
    unset($_SESSION['twofa_id'], $_SESSION['twofa_when']);

    admin_log_attempt((string) ($row['email'] ?? ''), true, '');

    try {
        q('UPDATE admin_users SET last_login_at = NOW() WHERE id = ?', [(int) $row['id']]);
        // A successful sign-in clears this address's failures, so someone who
        // mistyped a few times is not left one mistake away from a lockout.
        q('DELETE FROM admin_login_attempts WHERE ip_hash = ? AND ok = 0', [ip_hash()]);
    } catch (Throwable $e) {
        error_log('admin_finish_login: ' . $e->getMessage());
    }
}

// ---------------------------------------------------------------------------
// TOTP (RFC 6238) — the optional second factor
// ---------------------------------------------------------------------------

/** Base32 decode, the encoding authenticator apps exchange secrets in. */
function base32_decode(string $s): string
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $s = strtoupper(preg_replace('/[^A-Z2-7]/i', '', $s) ?? '');
    if ($s === '') {
        return '';
    }

    $bits = '';
    for ($i = 0, $n = strlen($s); $i < $n; $i++) {
        $pos = strpos($alphabet, $s[$i]);
        if ($pos === false) {
            continue;
        }
        $bits .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
    }

    $out = '';
    foreach (str_split($bits, 8) as $byte) {
        if (strlen($byte) === 8) {
            $out .= chr((int) bindec($byte));
        }
    }
    return $out;
}

function base32_encode(string $bytes): string
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $bits = '';
    for ($i = 0, $n = strlen($bytes); $i < $n; $i++) {
        $bits .= str_pad(decbin(ord($bytes[$i])), 8, '0', STR_PAD_LEFT);
    }

    $out = '';
    foreach (str_split($bits, 5) as $chunk) {
        $out .= $alphabet[(int) bindec(str_pad($chunk, 5, '0', STR_PAD_RIGHT))];
    }
    return $out;
}

function totp_code(string $secret, ?int $time = null): string
{
    $key   = base32_decode($secret);
    $slice = intdiv($time ?? time(), 30);
    $hash  = hash_hmac('sha1', pack('N2', 0, $slice), $key, true);

    $offset = ord($hash[19]) & 0x0F;
    $part   = ((ord($hash[$offset]) & 0x7F) << 24)
            | ((ord($hash[$offset + 1]) & 0xFF) << 16)
            | ((ord($hash[$offset + 2]) & 0xFF) << 8)
            |  (ord($hash[$offset + 3]) & 0xFF);

    return str_pad((string) ($part % 1000000), 6, '0', STR_PAD_LEFT);
}

/**
 * Verify a code, allowing the step either side.
 *
 * The tolerance is not sloppiness: phone clocks drift, and a code that was
 * valid when it was read but expires in the second it takes to type it would
 * otherwise lock people out for no reason.
 */
function totp_verify(string $secret, string $code): bool
{
    $code = preg_replace('/\D/', '', $code) ?? '';
    if (strlen($code) !== 6) {
        return false;
    }
    $now = time();
    foreach ([-30, 0, 30] as $drift) {
        if (hash_equals(totp_code($secret, $now + $drift), $code)) {
            return true;
        }
    }
    return false;
}

/** A fresh secret plus the otpauth:// URL an authenticator app can scan. */
function totp_new_secret(string $email): array
{
    $secret = base32_encode(random_bytes(20));
    $label  = rawurlencode(SITE_NAME . ':' . $email);
    $url    = 'otpauth://totp/' . $label . '?secret=' . $secret
            . '&issuer=' . rawurlencode(SITE_NAME) . '&algorithm=SHA1&digits=6&period=30';
    return ['secret' => $secret, 'url' => $url];
}
