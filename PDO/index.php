<?php
require_once __DIR__ . '/includes/bootstrap.php';
if (current_user()) {
    redirect('dashboard.php');
}
$title = t('Welcome');
require __DIR__ . '/includes/header.php';
?>
<section class="welcome-hero">
    <div class="intro">
        <span class="eyebrow"><?= e(t('Money Lending Management System')) ?></span>
        <h1><?= e(t('Your next step,')) ?><br><?= e(t('all in one place.')) ?></h1>
        <p><?= e(t('A clear view of your lending journey. Request a loan, follow its progress, and keep your account information close.')) ?></p>
        <div class="button-group"><a class="button" href="<?= e(app_url('customerLogin.php')) ?>"><?= e(t('Customer sign in')) ?> <span aria-hidden="true">→</span></a><a class="button secondary" href="<?= e(app_url('login.php')) ?>"><?= e(t('Staff & firm sign in')) ?></a></div>
        <p class="help"><?= e(t('New customer? Ask your lending firm to register you and create your login.')) ?></p>
    </div>
    <aside class="card welcome-panel">
        <span class="eyebrow"><?= e(t('Your customer workspace')) ?></span>
        <h2><?= e(t('More possibility.')) ?><br><?= e(t('Less guesswork.')) ?></h2>
        <ol class="journey"><li><span class="step-number">01</span><div><h3><?= e(t('Get verified')) ?></h3><p><?= e(t('Upload your national ID and proof of income for your firm to review.')) ?></p></div></li><li><span class="step-number">02</span><div><h3><?= e(t('Find your loan')) ?></h3><p><?= e(t('Choose a product and follow your application through approval.')) ?></p></div></li><li><span class="step-number">03</span><div><h3><?= e(t('Make progress')) ?></h3><p><?= e(t('Track repayments, download receipts, and watch your balance come down.')) ?></p></div></li></ol>
    </aside>
</section>
<section class="welcome-features" aria-label="<?= e(t('Choose your workspace')) ?>">
    <article class="card"><span class="eyebrow"><?= e(t('For customers')) ?></span><h2><?= e(t('Your loans, clearly.')) ?></h2><p><?= e(t('One dashboard for your requests, account details, and lending firm.')) ?></p><a href="<?= e(app_url('customerLogin.php')) ?>"><?= e(t('Open customer portal →')) ?></a></article>
    <article class="card"><span class="eyebrow"><?= e(t('For lending firms')) ?></span><h2><?= e(t('Keep customers connected.')) ?></h2><p><?= e(t('Manage customer records and verification in your firm workspace.')) ?></p><a href="<?= e(app_url('registerFirm.php')) ?>"><?= e(t('Register your firm →')) ?></a></article>
    <article class="card"><span class="eyebrow"><?= e(t('For your team')) ?></span><h2><?= e(t('A shared workspace.')) ?></h2><p><?= e(t('Sign in with your assigned account to access your role’s dashboard.')) ?></p><a href="<?= e(app_url('login.php')) ?>"><?= e(t('Sign in to your workspace →')) ?></a></article>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
