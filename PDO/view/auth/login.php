<?php
require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
if (current_user()) {
    redirect('dashboard.php');
}
$state = take_form('login');
$input = $state['input'] ?? [];
$title = t('Sign in');
require dirname(__DIR__, 2) . '/includes/header.php';
?>
<div class="auth-grid">
    <section class="intro">
        <span class="eyebrow"><?= e(t('Your lending workspace')) ?></span>
        <h1><?= e(t('A clear view of your firm and customers.')) ?></h1>
        <p><?= e(t('Sign in with your account. Your role and firm approval status determine the workspace you see.')) ?></p>
        <ol class="steps"><li><?= e(t('Register your firm and account.')) ?></li><li><?= e(t('Track your super admin review.')) ?></li><li><?= e(t('Manage your customers after approval.')) ?></li></ol>
    </section>
    <section class="card">
        <h2><?= e(t('Welcome back')) ?></h2>
        <?php if (!empty($state['errors'])): ?>
            <div class="notice error" role="alert"><?php foreach ($state['errors'] as $error): ?><div><?= e(t($error)) ?></div><?php endforeach; ?></div>
        <?php endif; ?>
        <form method="POST" action="<?= e(app_url('auth.php')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="login">
            <div class="field"><label for="email"><?= e(t('Email address')) ?></label><input type="email" id="email" name="email" autocomplete="username" maxlength="190" value="<?= e($input['email'] ?? '') ?>" required></div>
            <div class="field"><label for="password"><?= e(t('Password')) ?></label><input type="password" id="password" name="password" autocomplete="current-password" required></div>
            <button class="button wide" type="submit"><?= e(t('Sign in')) ?></button>
        </form>
        <p class="help"><?= e(t('Looking for your loans?')) ?> <a href="<?= e(app_url('customerLogin.php')) ?>"><?= e(t('Customer sign in')) ?></a></p>
        <p class="help"><?= e(t('New to MLMS?')) ?> <a href="<?= e(app_url('registerFirm.php')) ?>"><?= e(t('Register your firm')) ?></a></p>
    </section>
</div>
<?php require dirname(__DIR__, 2) . '/includes/footer.php'; ?>
