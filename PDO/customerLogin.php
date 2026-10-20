<?php
require_once __DIR__ . '/includes/bootstrap.php';
if (current_user()) {
    redirect('dashboard.php');
}
$state = take_form('customer_login');
$input = $state['input'] ?? [];
$title = 'Customer sign in';
require __DIR__ . '/includes/header.php';
?>
<div class="auth-grid">
    <section class="intro">
        <span class="eyebrow">Customer portal</span>
        <h1>Your lending journey, at a glance.</h1>
        <p>Sign in to request a loan and follow your applications from submission to decision.</p>
        <ol class="steps"><li>Explore available loan products.</li><li>Submit a request that fits your needs.</li><li>View application updates and loan details.</li></ol>
        <a href="<?= e(app_url('index.php')) ?>">← Back to welcome</a>
    </section>
    <section class="card">
        <h2>Welcome back</h2>
        <p class="muted">Use the account created for you by your lending firm.</p>
        <?php if (!empty($state['errors'])): ?><div class="notice error" role="alert"><?php foreach ($state['errors'] as $error): ?><div><?= e($error) ?></div><?php endforeach; ?></div><?php endif; ?>
        <form method="POST" action="<?= e(app_url('auth.php')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="customer_login">
            <div class="field"><label for="email">Email address</label><input type="email" id="email" name="email" autocomplete="username" maxlength="190" value="<?= e($input['email'] ?? '') ?>" required></div>
            <div class="field"><label for="password">Password</label><input type="password" id="password" name="password" autocomplete="current-password" required></div>
            <button class="button wide" type="submit">Sign in to your dashboard</button>
        </form>
        <p class="help">Need an account or help signing in? Contact your lending firm.</p>
        <p class="help">Firm owner or staff? <a href="<?= e(app_url('login.php')) ?>">Sign in here</a></p>
    </section>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
