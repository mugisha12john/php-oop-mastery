<?php
require_once __DIR__ . '/includes/bootstrap.php';
if (current_user()) {
    redirect('dashboard.php');
}
$state = take_form('register');
$input = $state['input'] ?? [];
$title = 'Register your firm';
require __DIR__ . '/includes/header.php';
?>
<div class="auth-grid">
    <section class="intro">
        <span class="eyebrow">Start with your firm</span>
        <h1>One workspace.<br>Your customers.</h1>
        <p>Create your firm and owner account together. A super admin will review your registration before customer access becomes available.</p>
        <ol class="steps"><li>Submit your business details.</li><li>Sign in to check your review status.</li><li>Once approved, register and manage customers.</li></ol>
    </section>
    <section class="card">
        <h2>Firm registration</h2>
        <?php if (!empty($state['errors'])): ?>
            <div class="notice error" role="alert"><ul><?php foreach ($state['errors'] as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div>
        <?php endif; ?>
        <form method="POST" action="<?= e(app_url('auth.php')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="register">
            <div class="field"><label for="firm_name">Firm name</label><input id="firm_name" name="firm_name" maxlength="120" value="<?= e($input['firm_name'] ?? '') ?>" required></div>
            <div class="field"><label for="registration_number">Business registration number</label><input id="registration_number" name="registration_number" maxlength="50" value="<?= e($input['registration_number'] ?? '') ?>" required></div>
            <div class="field"><label for="full_name">Account owner's full name</label><input id="full_name" name="full_name" autocomplete="name" maxlength="120" value="<?= e($input['full_name'] ?? '') ?>" required></div>
            <div class="row">
                <div class="field"><label for="email">Email / sign-in address</label><input type="email" id="email" name="email" autocomplete="username" maxlength="190" value="<?= e($input['email'] ?? '') ?>" required></div>
                <div class="field"><label for="phone">Phone number</label><input type="tel" id="phone" name="phone" autocomplete="tel" maxlength="20" value="<?= e($input['phone'] ?? '') ?>" required></div>
            </div>
            <div class="field"><label for="address">Business address</label><textarea id="address" name="address" autocomplete="street-address" maxlength="255" required><?= e($input['address'] ?? '') ?></textarea></div>
            <div class="field"><label for="password">Password</label><input type="password" id="password" name="password" autocomplete="new-password" minlength="12" maxlength="72" aria-describedby="password-help" required><div class="help" id="password-help">Use 12–72 characters. Some symbols use more than one byte.</div></div>
            <div class="field"><label for="password_confirmation">Confirm password</label><input type="password" id="password_confirmation" name="password_confirmation" autocomplete="new-password" minlength="12" maxlength="72" required></div>
            <button class="button wide" type="submit">Submit firm registration</button>
        </form>
        <p class="help">Already registered? <a href="<?= e(app_url('login.php')) ?>">Sign in</a></p>
    </section>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
