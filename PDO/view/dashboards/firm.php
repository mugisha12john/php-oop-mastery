<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_role('FIRM');
if (!defined('DASHBOARD_VIEW')) {
    redirect('dashboard.php');
}
?>
<div class="page-heading">
    <div><span class="eyebrow">Firm workspace</span><h1><?= e($user['firm_name'] ?? 'Your firm') ?></h1><p>Welcome, <?= e($user['full_name']) ?>.</p></div>
    <span class="badge <?= e(strtolower($user['firm_status'] ?? 'pending')) ?>"><?= e($user['firm_status'] ?? 'ACCOUNT NEEDS ATTENTION') ?></span>
</div>
<?php if ($stats !== null): ?>
    <div class="metrics">
        <?php foreach (['total' => 'Your customers', 'verified' => 'Verified customers', 'pending' => 'Pending KYC'] as $key => $label): ?>
            <div class="card metric"><span class="label"><?= e($label) ?></span><div class="number"><?= e($stats[$key]) ?></div></div>
        <?php endforeach; ?>
    </div>
    <section class="card"><h2>Customer management</h2><p>Your firm is approved. Register new customers and keep their contact details and verification status up to date.</p><a class="button" href="<?= e(app_url('view/customers/registerCustomer.php')) ?>">Register customer</a> <a class="button secondary" href="<?= e(app_url('view/customers/listCustomers.php')) ?>">View your customers</a></section>
<?php elseif ($user['firm_status'] === 'PENDING'): ?>
    <section class="card status-panel"><span class="eyebrow">Registration received</span><h2>Your firm is awaiting approval.</h2><p>A super admin will review your business details. Customer registration will become available here once your firm is approved.</p><a class="button secondary" href="<?= e(app_url('dashboard.php')) ?>">Refresh approval status</a></section>
<?php elseif ($user['firm_status'] === 'REJECTED'): ?>
    <section class="card status-panel"><span class="eyebrow">Review complete</span><h2>Your registration was rejected.</h2><p><?= e($user['rejection_reason'] ?? 'Contact the platform administrator for details.') ?></p><p class="help">Customer registration is unavailable. Contact the platform administrator to discuss your registration.</p></section>
<?php else: ?>
    <section class="card status-panel"><h2>Your firm account needs attention.</h2><p>Contact the platform administrator to check your firm registration.</p></section>
<?php endif; ?>
