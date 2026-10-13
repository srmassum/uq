<?php
/**
 * "You do not have permission for that."
 *
 * Its own file because auth.php has to be able to send people here, and auth.php
 * deliberately renders nothing — that was the fix for admin_head() being called
 * before ui.php had been loaded.
 */

declare(strict_types=1);

require_once __DIR__ . '/inc/panel.php';

require_admin();   // signed in, just not for whatever was asked for

$need = get_str('need');

// Plain English for the capability strings, so the page says what was refused
// rather than echoing an identifier at someone.
$plain = [
    'users.manage'    => 'manage admin accounts',
    'settings.manage' => 'change site settings',
    'content.edit'    => 'edit content',
    'content.publish' => 'publish content',
    'media.upload'    => 'upload images',
    'feedback.triage' => 'handle feedback',
    'itinerary.edit'  => 'edit itineraries',
    'collection.edit' => 'edit collections',
    'guide.edit'      => 'edit the visitor guide',
][$need] ?? 'do that';

admin_head(['title' => 'Not permitted', 'nav' => '']);
?>
<div class="ad-panel narrow">
  <h1>Not permitted</h1>
  <p class="ad-lede">
    Your account is signed in as an <b><?= e(admin_role()) ?></b>, and that role
    cannot <?= e($plain) ?>. Nothing was changed.
  </p>
  <p class="ad-muted">
    If you need this, ask a full admin to either change your role on the
    Admin&nbsp;users screen or make the change for you.
  </p>
  <p style="margin-block-start:20px">
    <a class="ad-btn primary" href="<?= e(APP_BASE) ?>/admin/index.php">Back to the dashboard</a>
  </p>
</div>
<?php
admin_foot();
