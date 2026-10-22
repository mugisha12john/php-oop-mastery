<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_role('FIRM');
if (!defined('DASHBOARD_VIEW')) {
    redirect('dashboard.php');
}
?>
<div class="page-heading">
    <div><span class="eyebrow"><?= e(t('Firm workspace')) ?></span><h1><?= e($user['firm_name'] ?? t('Your firm')) ?></h1><p><?= e(t('Welcome,')) ?> <?= e($user['full_name']) ?>.</p></div>
    <span class="badge <?= e(strtolower($user['firm_status'] ?? 'pending')) ?>"><?= e(isset($user['firm_status']) ? status_label($user['firm_status']) : t('Account needs attention')) ?></span>
</div>
<?php if ($stats !== null): ?>
    <div class="metrics">
        <?php foreach (['total' => t('Your customers'), 'verified' => t('Verified customers'), 'pending' => t('Pending KYC')] as $key => $label): ?>
            <div class="card metric"><span class="label"><?= e(t($label)) ?></span><div class="number"><?= e($stats[$key]) ?></div></div>
        <?php endforeach; ?>
    </div>
    <div class="dashboard-split"><section class="card loan-hero"><div class="hero-orbit" aria-hidden="true"></div><span class="orbit-arrow" aria-hidden="true">↗</span><span class="eyebrow"><?= e(t('Your customers, connected')) ?></span><h2><?= e(t('Help the next')) ?><br><?= e(t('possibility happen.')) ?></h2><p><?= e(t('Review identity and income documents so your customers can take their next step.')) ?></p><a class="button" href="<?= e(app_url('portal/documents.php')) ?>"><?= e(t('Review customer documents →')) ?></a></section><section class="card verification-card"><div><span class="eyebrow"><?= e(t('Firm workspace')) ?></span><h2><?= e(t('Customer management')) ?></h2><p class="muted"><?= e(t('Register new customers, create their login access, and keep their contact details up to date.')) ?></p></div><div class="button-group"><a class="button" href="<?= e(app_url('view/customers/registerCustomer.php')) ?>"><?= e(t('Register customer')) ?></a><a class="button secondary" href="<?= e(app_url('view/customers/listCustomers.php')) ?>"><?= e(t('View your customers')) ?></a></div></section></div>
    <section class="card"><div class="section-heading"><div><h2><?= e(t('Your lending products')) ?></h2><p class="muted"><?= e(t('Create products with the amounts, interest rates, and terms offered to your customers.')) ?></p></div><a class="button" href="<?= e(app_url('workspace/products.php')) ?>"><?= e(t('Manage loan products ↗')) ?></a></div><div class="button-group"><a class="button secondary" href="<?= e(app_url('workspace/index.php?tab=applications')) ?>"><?= e(t('View applications')) ?></a><a class="button secondary" href="<?= e(app_url('workspace/index.php?tab=loans')) ?>"><?= e(t('Open loan portfolio →')) ?></a></div></section>
<?php elseif ($user['firm_status'] === 'PENDING'): ?>
    <section class="card status-panel"><span class="eyebrow"><?= e(t('Registration received')) ?></span><h2><?= e(t('Your firm is awaiting approval.')) ?></h2><p><?= e(t('A super admin will review your business details. Customer registration will become available here once your firm is approved.')) ?></p><a class="button secondary" href="<?= e(app_url('dashboard.php')) ?>"><?= e(t('Refresh approval status')) ?></a></section>
<?php elseif ($user['firm_status'] === 'REJECTED'): ?>
    <section class="card status-panel"><span class="eyebrow"><?= e(t('Review complete')) ?></span><h2><?= e(t('Your registration was rejected.')) ?></h2><p><?= e($user['rejection_reason'] ?? t('Contact the platform administrator for details.')) ?></p><p class="help"><?= e(t('Customer registration is unavailable. Contact the platform administrator to discuss your registration.')) ?></p></section>
<?php else: ?>
    <section class="card status-panel"><h2><?= e(t('Your firm account needs attention.')) ?></h2><p><?= e(t('Contact the platform administrator to check your firm registration.')) ?></p></section>
<?php endif; ?>
