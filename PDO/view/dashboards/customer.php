<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_role('CUSTOMER');
if (!defined('DASHBOARD_VIEW')) {
    redirect('dashboard.php');
}
?>
<div class="page-heading">
    <div><span class="eyebrow">Customer workspace</span><h1>Welcome, <?= e($profile['full_name'] ?? $user['full_name']) ?></h1><p>Your requests, loan updates, and account information.</p></div>
    <a class="button" href="<?= e(app_url('requestLoan.php')) ?>">+ Request a loan</a>
</div>
<?php if (!$profile): ?>
    <section class="card status-panel"><h2>Let’s connect your customer profile.</h2><p>Your login is active, but it is not linked to a customer record yet. Contact your lending firm to connect your account before requesting a loan.</p></section>
<?php else: ?>
    <div class="metrics">
        <?php foreach (['total' => 'Loan requests', 'pending' => 'Awaiting a decision', 'approved' => 'Approved requests'] as $key => $label): ?><div class="card metric"><span class="label"><?= e($label) ?></span><div class="number"><?= e($summary[$key]) ?></div></div><?php endforeach; ?>
    </div>
    <div class="portal-grid">
        <section class="card applications-card" id="requests">
            <div class="section-heading"><div><h2>Your loan requests</h2><p class="muted">Open a request to see its full details.</p></div><span class="count-pill"><?= e($summary['total']) ?> total</span></div>
            <?php if (!$applications): ?>
                <div class="empty"><span class="empty-symbol" aria-hidden="true">↗</span><h3>Your first request starts here.</h3><p>Choose a loan product and tell us what you need.</p><a class="button secondary" href="<?= e(app_url('requestLoan.php')) ?>">Explore loan products</a></div>
            <?php else: ?>
                <div class="table-wrap"><table class="data-table"><caption class="sr-only">Your loan applications</caption><thead><tr><th>Request</th><th>Amount</th><th>Status</th><th>Details</th></tr></thead><tbody>
                <?php foreach ($applications as $application): ?><tr><td><strong><?= e($application['product_name']) ?></strong><span class="cell-note">#<?= e($application['application_id']) ?> · <?= e(date('d M Y', strtotime($application['submission_date']))) ?></span></td><td class="amount"><?= e(number_format((float) $application['amount_requested_rwf'], 2)) ?><span class="cell-note">RWF · <?= e($application['term_months']) ?> months</span></td><td><span class="badge <?= e(strtolower($application['status'])) ?>"><?= e(str_replace('_', ' ', $application['status'])) ?></span></td><td><a href="<?= e(app_url('loan.php?id=' . $application['application_id'])) ?>" aria-label="<?= e('View request #' . $application['application_id']) ?>">View →</a></td></tr><?php endforeach; ?>
                </tbody></table></div>
                <?php if ($pages > 1): ?><nav class="pagination" aria-label="Loan request pages"><?php if ($page > 1): ?><a class="button secondary small" href="?page=<?= $page - 1 ?>#requests">Previous</a><?php endif; ?><span class="muted">Page <?= $page ?> of <?= $pages ?></span><?php if ($page < $pages): ?><a class="button secondary small" href="?page=<?= $page + 1 ?>#requests">Next</a><?php endif; ?></nav><?php endif; ?>
            <?php endif; ?>
        </section>
        <aside class="portal-sidebar">
            <section class="card"><span class="eyebrow">Your profile</span><h2>Account information</h2><dl class="detail-list"><div><dt>Full name</dt><dd><?= e($profile['full_name']) ?></dd></div><div><dt>Sign-in email</dt><dd><?= e($user['email']) ?></dd></div><div><dt>Phone number</dt><dd><?= e($profile['phone_number']) ?></dd></div><div><dt>Verification</dt><dd><span class="badge <?= $profile['kyc_status'] === 'VERIFIED' ? 'approved' : e(strtolower($profile['kyc_status'])) ?>"><?= e($profile['kyc_status']) ?></span></dd></div></dl><p class="help">Contact your firm to update your profile.</p></section>
            <section class="card"><span class="eyebrow">Here to help</span><h2><?= e($profile['firm_name'] ?? 'Your lending firm') ?></h2><?php if ($profile['firm_name']): ?><p class="muted">Contact your firm about loan decisions or account access.</p><dl class="detail-list"><div><dt>Phone</dt><dd><?= e($profile['firm_phone']) ?></dd></div><div><dt>Email</dt><dd><?= e($profile['firm_email']) ?></dd></div></dl><?php else: ?><p class="muted">Contact the administrator to assign your profile to a lending firm.</p><?php endif; ?></section>
        </aside>
    </div>
<?php endif; ?>
