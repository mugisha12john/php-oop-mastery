<?php
require_once __DIR__ . '/includes/bootstrap.php';
if (current_user()) {
    redirect('dashboard.php');
}
$state = take_form('login');
$input = $state['input'] ?? [];
$title = 'Sign in';
require __DIR__ . '/includes/header.php';
?>
<div class="auth-grid">
    <section class="intro">
        <span class="eyebrow">Your lending workspace</span>
        <h1>A clear view of your firm and customers.</h1>
        <p>Sign in with your account. Your role and firm approval status determine the workspace you see.</p>
        <ol class="steps"><li>Register your firm and account.</li><li>Track your super admin review.</li><li>Manage your customers after approval.</li></ol>
    </section>
    <section class="card">
        <h2>Welcome back</h2>
        <?php if (!empty($state['errors'])): ?>
            <div class="notice error" role="alert"><?php foreach ($state['errors'] as $error): ?><div><?= e($error) ?></div><?php endforeach; ?></div>
        <?php endif; ?>
        <form method="POST" action="<?= e(app_url('auth.php')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="login">
            <div class="field"><label for="email">Email address</label><input type="email" id="email" name="email" autocomplete="username" maxlength="190" value="<?= e($input['email'] ?? '') ?>" required></div>
            <div class="field"><label for="password">Password</label><input type="password" id="password" name="password" autocomplete="current-password" required></div>
            <button class="button wide" type="submit">Sign in</button>
        </form>
        <p class="help">Looking for your loans? <a href="<?= e(app_url('customerLogin.php')) ?>">Customer sign in</a></p>
        <p class="help">New to MLMS? <a href="<?= e(app_url('registerFirm.php')) ?>">Register your firm</a></p>
    </section>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
