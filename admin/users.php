<?php
/**
 * Admin users.
 *
 * There is no sign-up and no password-reset email on this site, so this screen
 * *is* the account system: an admin makes an account here, tells the person the
 * password out of band, and changes it here if it is forgotten.
 *
 * Two rules are enforced that the form does not let you talk your way around,
 * because both of them end with nobody able to sign in:
 *
 *   - You cannot switch off or demote your own account.
 *   - You cannot remove the last active admin, whoever is doing the removing.
 *
 * Accounts are never deleted. The activity log points at them with ON DELETE SET
 * NULL, so a delete would quietly strip the "who" from the audit trail — and the
 * useful action is "switch this person off", which keeps the history intact.
 */

declare(strict_types=1);

require_once __DIR__ . '/inc/panel.php';
// The spec lives beside the screen that owns the columns, so setup/admin.php can
// create the first account through exactly the same validation and hashing.
require_once __DIR__ . '/inc/user-def.php';

require_admin('users.manage');
admin_require_csrf();

$myId = admin_id();

/** How many active admins there are, not counting one particular id. */
function admins_left_without(int $exceptId): int
{
    try {
        return (int) qVal(
            "SELECT COUNT(*) FROM admin_users WHERE role = 'admin' AND is_active = 1 AND id <> ?",
            [$exceptId]
        );
    } catch (Throwable $e) {
        error_log('admins_left_without: ' . $e->getMessage());
        // Fail closed: if the count cannot be established, do not allow the
        // change that might remove the last way in.
        return 0;
    }
}

// ------------------------------------------------------------------ actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $def = user_spec();
    $id  = post_int('id');            // 0 = create
    $id  = $id > 0 ? $id : null;

    $existing = $id === null ? null : record_by_id('admin_users', $id);
    if ($id !== null && $existing === null) {
        admin_back(admin_url('users.php'), 'That account no longer exists.', 'bad');
    }

    $isSelf = $id !== null && $id === $myId;

    // --- the two guards ----------------------------------------------------
    $wantsActive = post_bool('is_active') === 1;
    $wasActive   = $existing === null ? true : (int) $existing['is_active'] === 1;
    $wantsRole   = post_str('role', 'editor');
    $wasRole     = $existing === null ? '' : (string) $existing['role'];

    if ($isSelf && (!$wantsActive || $wantsRole !== 'admin')) {
        admin_back(admin_url('users.php', ['id' => $myId]),
            'You cannot switch off or demote your own account — you would lose access immediately.', 'bad');
    }

    // Anyone (including yourself) may not remove the last route back in.
    $wouldStopAdmin = $wasRole === 'admin' && $wasActive && ($wantsRole !== 'admin' || !$wantsActive);
    if ($wouldStopAdmin && admins_left_without($id ?? 0) === 0) {
        admin_back(admin_url('users.php', $id === null ? [] : ['id' => $id]),
            'This is the last active admin. Make somebody else an admin first, or you will have no way back into the panel.', 'bad');
    }

    // --- write -------------------------------------------------------------
    $res = record_save($def, $_POST, $id);

    if (!$res['ok']) {
        // Errors are shown on the form rather than flashed, so the typed values
        // survive the round trip. Stashed in the session for one redirect.
        $_SESSION['ad_user_form']   = $_POST;
        $_SESSION['ad_user_errors'] = $res['errors'];
        $_SESSION['ad_user_edit']   = $id ?? 0;
        admin_back(admin_url('users.php', $id === null ? ['new' => 1] : ['id' => $id]),
            'Nothing was saved.', 'bad');
    }

    unset($_SESSION['ad_user_form'], $_SESSION['ad_user_errors'], $_SESSION['ad_user_edit']);
    $created = $id === null;

    // Creating an account that cannot sign in is almost always a slipped
    // checkbox rather than a decision, so say which it was.
    $leftOff = !$wantsActive;
    admin_back(
        admin_url('users.php', ['id' => $res['id']]),
        ($created ? 'Account created.' : 'Saved.')
        . ($leftOff ? ' It is switched off, so nobody can sign in with it yet.' : ''),
        $leftOff ? 'warn' : 'ok'
    );
}

// -------------------------------------------------------------------- reads
$editId  = get_int('id');
$isNew   = get_int('new') === 1;
$editRow = $editId > 0 ? record_by_id('admin_users', $editId) : null;

$sticky  = $_SESSION['ad_user_form'] ?? null;
$errors  = $_SESSION['ad_user_errors'] ?? [];
unset($_SESSION['ad_user_form'], $_SESSION['ad_user_errors'], $_SESSION['ad_user_edit']);

$users     = [];
$readError = '';
try {
    $users = qAll('SELECT id, email, name, role, is_active, totp_secret, last_login_at, created_at
                     FROM admin_users ORDER BY is_active DESC, role, email');
} catch (Throwable $e) {
    error_log('users.php: ' . $e->getMessage());
    $readError = $e->getMessage();
}

$activeAdmins = 0;
foreach ($users as $u) {
    if ($u['role'] === 'admin' && (int) $u['is_active']) {
        $activeAdmins++;
    }
}

/**
 * The value a field should show: what was typed, then what is stored, then a
 * sensible default. Keeps a rejected form from wiping what somebody wrote.
 *
 * Two cases need care. A password is never echoed back — a rejected create
 * would otherwise print the plaintext the person chose straight into the page
 * source. And an unchecked checkbox posts nothing at all, so for a bool field
 * the *absence* of the key is the answer, not a reason to fall through to the
 * stored value.
 */
function user_val(array $spec, ?array $row, ?array $posted): mixed
{
    $k    = $spec['k'];
    $type = $spec['type'] ?? 'text';

    if ($type === 'password') {
        return '';
    }
    if ($type === 'bool' && is_array($posted)) {
        return !empty($posted[$k]) ? 1 : 0;
    }
    if (is_array($posted) && array_key_exists($k, $posted)) {
        return $posted[$k];
    }
    if ($row !== null && array_key_exists($k, $row)) {
        return $row[$k];
    }
    return match ($k) {
        'role'      => 'editor',
        'is_active' => 1,
        default     => '',
    };
}

/** Render the user form. Used for both create and edit. */
function user_form(array $def, ?array $row, ?array $posted, array $errors, bool $isSelf): void
{
    $id = $row === null ? 0 : (int) $row['id'];

    ad_form_open(admin_url('users.php'), $row === null ? 'Add an account' : 'Edit account',
        $row === null
            ? 'Tell the person the password some other way — there is no email sent from this site.'
            : '', false);
    ?>
      <input type="hidden" name="id" value="<?= $id ?>">
      <div class="ad-grid">
        <?php foreach ($def['fields'] as $f): ?>
          <?php
          $spec = $f;
          if ($f['k'] === 'password_hash' && $row !== null) {
              $spec['required'] = false;
              $spec['ph'] = 'Leave blank to keep the current password';
          }
          ad_field($spec, user_val($f, $row, $posted));
          if (isset($errors[$f['k']])) {
              echo '<span class="ad-err">' . e($errors[$f['k']]) . '</span>';
          }
          ?>
        <?php endforeach; ?>
      </div>

      <?php if (isset($errors['_form'])): ?>
        <p class="ad-err"><?= e($errors['_form']) ?></p>
      <?php endif; ?>

      <?php if ($isSelf): ?>
        <p class="ad-help">
          This is your own account. The role and the active switch are locked while you are
          looking at it — changing either here would sign you out for good.
        </p>
      <?php endif; ?>
    <?php
    ad_form_close($row === null ? 'Create account' : 'Save', admin_url('users.php'));
}

admin_head(['title' => 'Admin users', 'nav' => 'users']);

ad_title(
    'Admin users',
    'The only logins on this site. Editors write content; admins also manage people, settings and backups.',
    $isNew || $editRow === null
        ? []
        : [['href' => admin_url('users.php', ['new' => 1]), 'label' => '+ Add an account', 'kind' => 'primary']]
);

if ($readError !== '') {
    ad_note('Could not read the accounts table: ' . e($readError) . '. Has <code>sql/schema.sql</code> been imported?', 'bad');
}

if ($activeAdmins === 1 && $users !== []) {
    ad_note(
        'There is <b>one active admin</b>. If that account is locked out, the way back in is a database edit. '
        . 'A second admin account — even one that is switched off — is worth having.',
        'warn'
    );
}

if ($users === []) {
    ad_note(
        'No accounts exist yet. This screen needs one to open, so create the first one from '
        . '<code>setup/admin.php</code> (and delete that file afterwards).',
        'warn'
    );
}
?>

<div class="ad-panel">
  <h2>Accounts</h2>
  <?php if ($users === []): ?>
    <?php ad_empty('Nobody yet.'); ?>
  <?php else: ?>
    <div class="ad-tablewrap">
      <table class="ad-table">
        <thead>
          <tr>
            <th>Who</th>
            <th style="inline-size:100px">Role</th>
            <th style="inline-size:90px">State</th>
            <th style="inline-size:90px">2FA</th>
            <th style="inline-size:130px">Last signed in</th>
            <th style="inline-size:90px"></th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($users as $u): ?>
          <tr>
            <td>
              <a class="ad-rowlink" href="<?= e(admin_url('users.php', ['id' => (int) $u['id']])) ?>">
                <?= e(trim((string) $u['name']) !== '' ? (string) $u['name'] : (string) $u['email']) ?>
              </a>
              <span class="ad-sub">
                <?= e((string) $u['email']) ?>
                <?php if ((int) $u['id'] === $myId): ?> · that’s you<?php endif; ?>
              </span>
            </td>
            <td><?= ad_chip((string) $u['role'], $u['role'] === 'admin' ? 'info' : '') ?></td>
            <td><?= (int) $u['is_active'] ? ad_chip('active', 'ok') : ad_chip('off', 'mute') ?></td>
            <td><?= (string) ($u['totp_secret'] ?? '') !== '' ? ad_chip('on', 'ok') : ad_chip('off', 'warn') ?></td>
            <td><?= e(ad_when($u['last_login_at'] === null ? null : (string) $u['last_login_at'])) ?></td>
            <td><a class="ad-btn ghost" href="<?= e(admin_url('users.php', ['id' => (int) $u['id']])) ?>">Edit</a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php
// One form on screen at a time: whichever of "new" or "edit" was asked for,
// otherwise a short list of what to do instead of an empty form.
if ($isNew || $users === []) {
    ad_panel_open();
    user_form(user_spec(), null, is_array($sticky) && get_int('new') === 1 ? $sticky : null, $errors, false);
    ad_panel_close();
} elseif ($editRow !== null) {
    $isSelf = (int) $editRow['id'] === $myId;

    ad_panel_open();

    // On your own account the two controls that would lock you out are shown
    // disabled rather than hidden — a switch that has silently vanished is
    // more confusing than one that explains why it will not move.
    if ($isSelf) {
        echo '<p class="ad-help" style="margin-block-start:0">'
           . 'You are editing your own account. '
           . (admin_is_admin()
                ? 'Your role and the active switch are fixed here; ask another admin if they need to change.'
                : 'Your role and the active switch are fixed here.')
           . '</p>';
    }

    user_form(
        user_spec(),
        $isSelf ? array_merge($editRow, ['role' => 'admin', 'is_active' => 1]) : $editRow,
        is_array($sticky) ? $sticky : null,
        $errors,
        $isSelf
    );

    if ($isSelf):
        // A disabled control posts nothing, so put the real values back by hand
        // or record_save would write an empty role. The guard above refuses a
        // change here anyway; this keeps the form honest about what it sent.
        ?>
        <script>
        (function () {
          var f = document.querySelector('.ad-form');
          if (!f) { return; }
          ['role', 'is_active'].forEach(function (name) {
            var el = f.querySelector('[name="' + name + '"]');
            if (!el) { return; }
            el.disabled = true;
            var hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = name;
            hidden.value = name === 'role' ? 'admin' : '1';
            f.appendChild(hidden);
          });
        })();
        </script>
        <?php
    endif;

    ad_panel_close();
} else {
    ad_panel_open();
    ad_empty($users === [] ? 'Add the first account above.' : 'Pick an account to edit, or add a new one.');
    ad_panel_close();
}

admin_foot();
