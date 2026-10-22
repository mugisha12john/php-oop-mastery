<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../app/Auth/PasswordService.php';
$user = require_role('CUSTOMER');
$errors = [];
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    require_post();
    try {
        $version = (new PasswordService(db(), $user))->change(post_string('current_password'), post_string('password'), post_string('password_confirmation'));
        session_regenerate_id(true);
        $_SESSION = ['user_id' => (int) $user['user_id'], 'password_version' => $version];
        csrf_token();
        flash('success', t('Your password has been changed. Use your new password the next time you sign in.'));
        redirect('dashboard.php');
    } catch (DomainException $error) {
        $errors[] = $error->getMessage();
    } catch (Throwable $error) {
        error_log('Password change failed: ' . $error->getMessage());
        $errors[] = t('Your password could not be changed. Please try again.');
    }
}
$title = t('Change password');
require __DIR__ . '/../includes/header.php';
?>
<div class="page-heading"><div><span class="eyebrow"><?= e(t('Your account')) ?></span><h1><?= $user['must_change_password'] ? t('Make this account yours.') : t('Change your password') ?></h1><p><?= $user['must_change_password'] ? t('Set a personal password before continuing to your customer portal.') : t('Choose a personal password to keep your account secure.') ?></p></div></div>
<section class="card status-panel"><h2><?= $user['must_change_password'] ? t('Set your new password') : t('Your new password') ?></h2>
<?php if ($errors): ?><div class="notice error" role="alert"><?php foreach ($errors as $error): ?><p><?= e(t($error)) ?></p><?php endforeach; ?></div><?php endif; ?>
<form method="POST" action="<?= e(app_url('portal/password.php')) ?>"><?= csrf_field() ?>
<div class="field"><label for="current-password"><?= e(t('Current password')) ?></label><input id="current-password" name="current_password" type="password" autocomplete="current-password" required></div>
<div class="field"><label for="new-password"><?= e(t('New password')) ?></label><input id="new-password" name="password" type="password" minlength="12" maxlength="72" autocomplete="new-password" required><p class="help"><?= e(t('Use a personal password of 12–72 bytes. The default password cannot be reused.')) ?></p></div>
<div class="field"><label for="confirmation"><?= e(t('Confirm new password')) ?></label><input id="confirmation" name="password_confirmation" type="password" minlength="12" maxlength="72" autocomplete="new-password" required></div>
<button class="button" type="submit"><?= e(t('Save new password →')) ?></button></form></section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
