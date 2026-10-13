<?php
/**
 * Security — the sign-in trail, the lockout rules, and the optional second factor.
 *
 * Two things this screen deliberately does NOT do:
 *
 *   1. It does not draw a QR code by sending the secret to an image service.
 *      The TOTP secret is the second factor; posting it to a third-party chart
 *      API to get a square of pixels back would hand the whole scheme to that
 *      service. The secret is shown as text and as an otpauth:// link, and every
 *      authenticator app can be given either by hand.
 *   2. It does not let you switch 2FA off without proving who you are. Turning
 *      it off is exactly what someone with a stolen session would want to do
 *      first, so it asks for the account password again.
 *
 * There is no QR, but there is a link — `otpauth://` opens the authenticator app
 * directly on a phone that is reading this page, which is the case that matters.
 */

declare(strict_types=1);

require_once __DIR__ . '/inc/panel.php';

require_admin('users.manage');
admin_require_csrf();

$me      = admin_user();
$myId    = admin_id();

// ------------------------------------------------------------------ actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $do = post_str('do');

    // Step one of enrolment: make a secret and hold it in the session. Nothing
    // is written to the account until a code from the app has proved the app
    // actually has it — otherwise a mistyped secret locks the account out.
    if ($do === '2fa_start') {
        $fresh = totp_new_secret((string) ($me['email'] ?? ''));
        $_SESSION['twofa_pending'] = $fresh['secret'];
        admin_back(admin_url('security.php'), 'Scan or type the secret into your authenticator app, then enter the code it shows.');
    }

    if ($do === '2fa_confirm') {
        $pending = (string) ($_SESSION['twofa_pending'] ?? '');
        if ($pending === '') {
            admin_back(admin_url('security.php'), 'That setup ran out of time. Start again.', 'warn');
        }
        if (!totp_verify($pending, post_str('code'))) {
            admin_back(admin_url('security.php'), 'That code did not match. Check the app’s clock and try the next one.', 'bad');
        }

        try {
            $before = record_by_id('admin_users', $myId);
            q('UPDATE admin_users SET totp_secret = ? WHERE id = ?', [$pending, $myId]);
            unset($_SESSION['twofa_pending']);
            log_activity('security', '2fa_on', 'admin_user', $myId, $before, record_by_id('admin_users', $myId));
            admin_back(admin_url('security.php'), 'Two-factor is on. You will be asked for a code at your next sign-in.', 'ok');
        } catch (Throwable $e) {
            error_log('2fa_confirm: ' . $e->getMessage());
            admin_back(admin_url('security.php'), 'Could not save that.', 'bad');
        }
    }

    if ($do === '2fa_off') {
        // Re-checking the password here is the whole point of the branch: a
        // borrowed session must not be able to remove the second factor.
        $row = null;
        try {
            $row = qOne('SELECT password_hash FROM admin_users WHERE id = ?', [$myId]);
        } catch (Throwable $e) {
            error_log('2fa_off: ' . $e->getMessage());
        }
        if ($row === null || !password_verify(post_str('password'), (string) $row['password_hash'])) {
            log_activity('security', '2fa_off_refused', 'admin_user', $myId);
            admin_back(admin_url('security.php'), 'That password was not right. Two-factor is still on.', 'bad');
        }

        try {
            $before = record_by_id('admin_users', $myId);
            q('UPDATE admin_users SET totp_secret = NULL WHERE id = ?', [$myId]);
            log_activity('security', '2fa_off', 'admin_user', $myId, $before, record_by_id('admin_users', $myId));
            admin_back(admin_url('security.php'), 'Two-factor is off.', 'warn');
        } catch (Throwable $e) {
            error_log('2fa_off save: ' . $e->getMessage());
            admin_back(admin_url('security.php'), 'Could not save that.', 'bad');
        }
    }

    // Clearing the trail is a real delete and admin-only: the log is evidence,
    // and an editor should not be able to erase their own sign-in history.
    if ($do === 'purge_attempts' && admin_is_admin()) {
        try {
            $days = max(1, min(3650, post_int('older_than', 90)));
            q("DELETE FROM admin_login_attempts WHERE created_at < NOW() - INTERVAL {$days} DAY");
            log_activity('security', 'purge_attempts', 'admin_login_attempts', null, null, ['older_than_days' => $days]);
            admin_back(admin_url('security.php'), "Sign-in attempts older than {$days} days were cleared.", 'ok');
        } catch (Throwable $e) {
            error_log('purge_attempts: ' . $e->getMessage());
            admin_back(admin_url('security.php'), 'Could not clear the log.', 'bad');
        }
    }

    admin_back(admin_url('security.php'), 'Nothing to do.', 'warn');
}

// -------------------------------------------------------------------- reads
$pending   = (string) ($_SESSION['twofa_pending'] ?? '');
$has2fa    = (string) ($me['totp_secret'] ?? '') !== '';
$locked    = admin_login_locked();
$myFails   = admin_fail_count();
$attempts  = [];
$failCount = 0;
$readError = '';

try {
    $failCount = (int) qVal('SELECT COUNT(*) FROM admin_login_attempts WHERE ok = 0');
    $attempts  = qAll('SELECT * FROM admin_login_attempts ORDER BY created_at DESC, id DESC LIMIT 60');
} catch (Throwable $e) {
    error_log('security.php: ' . $e->getMessage());
    $readError = $e->getMessage();
}

// Who else can get in, and how. An unverified second factor anywhere is worth
// knowing about — it is the same warning the dashboard gives, from the other end.
$others = [];
try {
    $others = qAll('SELECT id, email, name, role, is_active, totp_secret, last_login_at
                      FROM admin_users WHERE id <> ? ORDER BY role, email', [$myId]);
} catch (Throwable $e) {
    error_log('security.php others: ' . $e->getMessage());
}

admin_head(['title' => 'Security', 'nav' => 'security']);

ad_title('Security', 'Who has been signing in, what the rules are, and the second factor on your own account.');

if ($readError !== '') {
    ad_note('Could not read the sign-in log: ' . e($readError) . '. Has <code>sql/schema.sql</code> been imported?', 'bad');
}

if ($locked) {
    ad_note(
        '<b>This address is currently locked out.</b> ' . (int) $myFails . ' failed attempts in the last '
        . (int) ADMIN_LOGIN_LOCK_MINUTES . ' minutes. The lock lifts on its own; nothing needs doing. '
        . 'If these were not you, change the password on the account being tried.',
        'bad'
    );
}

?>

<div class="ad-stats" style="margin-block-end:18px">
  <div class="ad-stat<?= $failCount > 0 ? ' warn' : ' good' ?>">
    <b><?= number_format($failCount) ?></b><span>Failed sign-ins on record</span>
  </div>
  <div class="ad-stat"><b><?= (int) ADMIN_LOGIN_MAX_FAILS ?></b><span>Failures before a lockout</span></div>
  <div class="ad-stat"><b><?= (int) ADMIN_LOGIN_LOCK_MINUTES ?> min</b><span>Lockout length</span></div>
  <div class="ad-stat"><b><?= (int) ADMIN_IDLE_MINUTES ?> min</b><span>Idle session timeout</span></div>
  <div class="ad-stat<?= $has2fa ? ' good' : ' warn' ?>">
    <b><?= $has2fa ? 'On' : 'Off' ?></b><span>Your two-factor</span>
  </div>
</div>

<div class="ad-panel">
  <h2>Two-factor on your account</h2>

  <?php if ($has2fa): ?>
    <p>
      Two-factor is <b>on</b>. You are asked for a six-digit code from your authenticator app
      each time you sign in.
    </p>
    <p class="ad-help">
      Keep the app’s backup codes somewhere safe. If the phone is lost and there is no backup,
      the way back in is a database edit — there is no reset email on this site.
    </p>
    <form method="post" action="<?= e(admin_url('security.php')) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="do" value="2fa_off">
      <div class="ad-grid">
        <?php
        ad_field([
            'k' => 'password', 't' => 'Your password', 'type' => 'password', 'required' => true,
            'help' => 'Asked again because removing the second factor is the first thing someone with a stolen session would try.',
        ], '');
        ?>
      </div>
      <div class="ad-form-act">
        <button class="ad-btn danger" type="submit" data-ad-confirm="Turn two-factor off for your account?">Turn two-factor off</button>
      </div>
    </form>

  <?php elseif ($pending !== ''): ?>
    <p>Add this secret to your authenticator app — type it in, or open the link on the phone itself.</p>

    <div class="ad-grid-2">
      <div>
        <p class="ad-help" style="margin:0 0 4px">Secret (type this into the app)</p>
        <p class="mono" style="font-size:1.05rem;letter-spacing:.14em;word-break:break-all;margin:0 0 4px"><?= e($pending) ?></p>
        <p class="ad-help">Spaces and case do not matter — the app ignores both.</p>
      </div>
      <div>
        <p class="ad-help" style="margin:0 0 4px">Or open it directly</p>
        <p style="margin:0 0 10px;word-break:break-all">
          <a class="mono" style="font-size:12.5px" href="<?= e('otpauth://totp/' . rawurlencode(SITE_NAME . ':' . (string) ($me['email'] ?? '')) . '?secret=' . $pending . '&issuer=' . rawurlencode(SITE_NAME) . '&algorithm=SHA1&digits=6&period=30') ?>">otpauth:// link</a>
        </p>
        <p class="ad-help">On a phone reading this page, that link opens the app with everything filled in.</p>
      </div>
    </div>

    <form method="post" action="<?= e(admin_url('security.php')) ?>" style="margin-block-start:14px">
      <?= csrf_field() ?>
      <input type="hidden" name="do" value="2fa_confirm">
      <div class="ad-grid">
        <?php
        ad_field([
            'k' => 'code', 't' => 'The code the app shows now', 'required' => true, 'maxlen' => 6,
            'mono' => true, 'ph' => '123456',
            'help' => 'Nothing is saved until this matches, so a mistyped secret cannot lock you out of your own account.',
        ], '');
        ?>
      </div>
      <div class="ad-form-act">
        <button class="ad-btn primary" type="submit">Turn two-factor on</button>
        <a class="ad-btn ghost" href="<?= e(admin_url('security.php')) ?>">Cancel</a>
      </div>
    </form>

  <?php else: ?>
    <p>
      Two-factor is <b>off</b> on your account. Adding it means a password alone is no longer
      enough to get into this panel.
    </p>
    <p class="ad-help">
      It is optional and stays optional. Read this first, though: the panel can publish, delete
      and restore anything on the site, and the sign-in page is on the open internet — so the
      account that matters most here is the one that can do the most damage.
    </p>
    <form method="post" action="<?= e(admin_url('security.php')) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="do" value="2fa_start">
      <div class="ad-form-act" style="margin-block-start:0;padding-block-start:0;border:0">
        <button class="ad-btn primary" type="submit">Set up two-factor</button>
      </div>
    </form>
  <?php endif; ?>
</div>

<?php if ($others !== []): ?>
<div class="ad-panel">
  <h2>Other accounts</h2>
  <p class="ad-help" style="margin-block-start:0">
    Two-factor is per account. An admin without it is the weakest way in.
  </p>
  <div class="ad-tablewrap">
    <table class="ad-table">
      <thead><tr><th>Who</th><th>Role</th><th>Two-factor</th><th>State</th><th>Last signed in</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($others as $u): ?>
        <tr>
          <td>
            <?= e(trim((string) $u['name']) !== '' ? (string) $u['name'] : (string) $u['email']) ?>
            <?php if (trim((string) $u['name']) !== ''): ?>
              <span class="ad-sub"><?= e((string) $u['email']) ?></span>
            <?php endif; ?>
          </td>
          <td><?= ad_chip((string) $u['role'], $u['role'] === 'admin' ? 'info' : '') ?></td>
          <td><?= (string) ($u['totp_secret'] ?? '') !== '' ? ad_chip('on', 'ok') : ad_chip('off', 'warn') ?></td>
          <td><?= (int) $u['is_active'] ? ad_chip('active', 'ok') : ad_chip('switched off', 'mute') ?></td>
          <td><?= e(ad_when($u['last_login_at'] === null ? null : (string) $u['last_login_at'])) ?></td>
          <td><a class="ad-btn ghost" href="<?= e(admin_url('users.php', ['id' => (int) $u['id']])) ?>">Open</a></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<div class="ad-panel">
  <h2>Recent sign-in attempts</h2>
  <?php if ($attempts === []): ?>
    <?php ad_empty('No sign-in attempts recorded yet.'); ?>
  <?php else: ?>
    <p class="ad-help" style="margin-block-start:0">
      The address is stored only as an HMAC, never as an address — enough to tell
      whether two attempts came from the same place, not enough to say where that place is.
      The same eight characters always mean the same origin.
    </p>
    <div class="ad-tablewrap">
      <table class="ad-table">
        <thead>
          <tr>
            <th style="inline-size:150px">When</th>
            <th>Email tried</th>
            <th>From</th>
            <th style="inline-size:110px">Result</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($attempts as $a): ?>
          <tr>
            <td title="<?= e((string) $a['created_at']) ?>"><?= e(ad_when((string) $a['created_at'])) ?></td>
            <td class="mono" style="font-size:12.5px"><?= e((string) $a['email']) ?></td>
            <td class="mono" style="font-size:12.5px"><?= e(substr((string) $a['ip_hash'], 0, 8)) ?>…</td>
            <td>
              <?php if ((int) $a['ok']): ?>
                <?= ad_chip('signed in', 'ok') ?>
              <?php else: ?>
                <?= ad_chip(str_replace('_', ' ', (string) $a['reason']), 'bad') ?>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <?php if (admin_is_admin() && $failCount > 0): ?>
      <form method="post" action="<?= e(admin_url('security.php')) ?>" style="margin-block-start:14px">
        <?= csrf_field() ?>
        <input type="hidden" name="do" value="purge_attempts">
        <div class="ad-bulk">
          <label class="ad-sr" for="older_than">Older than</label>
          <select name="older_than" id="older_than">
            <option value="30">Older than 30 days</option>
            <option value="90" selected>Older than 90 days</option>
            <option value="365">Older than a year</option>
          </select>
          <button class="ad-btn" type="submit" data-ad-confirm="Delete those sign-in attempts for good?">Clear old entries</button>
          <span class="ad-muted">Clearing them also clears what they count towards, if any are recent.</span>
        </div>
      </form>
    <?php endif; ?>
  <?php endif; ?>
</div>

<?php
admin_foot();
