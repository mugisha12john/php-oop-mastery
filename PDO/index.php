<?php
require_once __DIR__ . '/includes/bootstrap.php';
if (current_user()) {
    redirect('dashboard.php');
}
$title = 'Welcome';
require __DIR__ . '/includes/header.php';
?>
<section class="welcome-hero">
    <div class="intro">
        <span class="eyebrow">Money Lending Management System</span>
        <h1>Your next step,<br>all in one place.</h1>
        <p>A clear view of your lending journey. Request a loan, follow its progress, and keep your account information close.</p>
        <div class="button-group"><a class="button" href="<?= e(app_url('customerLogin.php')) ?>">Customer sign in <span aria-hidden="true">→</span></a><a class="button secondary" href="<?= e(app_url('login.php')) ?>">Staff &amp; firm sign in</a></div>
        <p class="help">New customer? Ask your lending firm to register you and create your login.</p>
    </div>
    <aside class="card welcome-panel">
        <span class="eyebrow">Your customer workspace</span>
        <h2>From request to decision.</h2>
        <ol class="journey"><li><span class="step-number">01</span><div><h3>Request a loan</h3><p>Choose a product, amount, and repayment term.</p></div></li><li><span class="step-number">02</span><div><h3>Follow your application</h3><p>Check your request status and decision details.</p></div></li><li><span class="step-number">03</span><div><h3>Stay informed</h3><p>View your loan terms, balance, and account information.</p></div></li></ol>
    </aside>
</section>
<section class="welcome-features" aria-label="Choose your workspace">
    <article class="card"><span class="eyebrow">For customers</span><h2>Your loans, clearly.</h2><p>One dashboard for your requests, account details, and lending firm.</p><a href="<?= e(app_url('customerLogin.php')) ?>">Open customer portal →</a></article>
    <article class="card"><span class="eyebrow">For lending firms</span><h2>Keep customers connected.</h2><p>Manage customer records and verification in your firm workspace.</p><a href="<?= e(app_url('registerFirm.php')) ?>">Register your firm →</a></article>
    <article class="card"><span class="eyebrow">For your team</span><h2>A shared workspace.</h2><p>Sign in with your assigned account to access your role’s dashboard.</p><a href="<?= e(app_url('login.php')) ?>">Sign in to your workspace →</a></article>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
