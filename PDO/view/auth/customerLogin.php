<?php
require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
if (current_user()) {
    redirect('dashboard.php');
}
$state = take_form('customer_login');
$input = $state['input'] ?? [];
$title = t('Customer sign in');
require dirname(__DIR__, 2) . '/includes/header.php';
?>
<div class="auth-grid">
    <section class="intro">
        <span class="eyebrow"><?= e(t('Customer portal')) ?></span>
        <h1><?= e(t('Your lending journey, at a glance.')) ?></h1>
        <p><?= e(t('Sign in to request a loan and follow your applications from submission to decision.')) ?></p>
        <ol class="steps"><li><?= e(t('Explore available loan products.')) ?></li><li><?= e(t('Submit a request that fits your needs.')) ?></li><li><?= e(t('View application updates and loan details.')) ?></li></ol>
        <a href="<?= e(app_url('index.php')) ?>"><?= e(t('← Back to welcome')) ?></a>
    </section>
    <section class="card">
        <h2><?= e(t('Welcome back')) ?></h2>
        <p class="muted"><?= e(t('Use the account created for you by your lending firm.')) ?></p>
        <?php if (!empty($state['errors'])): ?><div class="notice error" role="alert"><?php foreach ($state['errors'] as $error): ?><div><?= e(t($error)) ?></div><?php endforeach; ?></div><?php endif; ?>
        <form method="POST" action="<?= e(app_url('auth.php')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="customer_login">
            <div class="field"><label for="email"><?= e(t('Email address')) ?></label><input type="email" id="email" name="email" autocomplete="username" maxlength="190" value="<?= e($input['email'] ?? '') ?>" required></div>
            <div class="field"><label for="password"><?= e(t('Password')) ?></label><input type="password" id="password" name="password" autocomplete="current-password" required></div>
            <button class="button wide" type="submit"><?= e(t('Sign in to your dashboard')) ?></button>
        </form>
        <p class="help"><?= e(t('Need an account or help signing in? Contact your lending firm.')) ?></p>
        <p class="help"><?= e(t('Firm owner or staff?')) ?> <a href="<?= e(app_url('login.php')) ?>"><?= e(t('Sign in here')) ?></a></p>
    </section>
</div>
<?php require dirname(__DIR__, 2) . '/includes/footer.php'; ?>
