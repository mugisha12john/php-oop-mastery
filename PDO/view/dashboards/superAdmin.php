<?php
// Prevent the template from being used as an unprotected public entry point.
require_once __DIR__ . '/../../includes/bootstrap.php';
require_role('SUPER_ADMIN');
if (!defined('DASHBOARD_VIEW')) {
    redirect('dashboard.php');
}
?>
<div class="page-heading">
    <div><span class="eyebrow">Super admin workspace</span><h1>Firm registrations</h1><p>Review businesses and control access to customer management.</p></div>
    <a class="button secondary" href="<?= e(app_url('view/customers/listCustomers.php')) ?>">View all customers</a>
</div>
<div class="metrics">
    <?php foreach ($counts as $key => $count): ?>
        <div class="card metric"><span class="label"><?= e(ucfirst(strtolower($key))) ?> firms</span><div class="number"><?= e($count) ?></div></div>
    <?php endforeach; ?>
</div>
<nav class="tabs" aria-label="Firm registration status">
    <?php foreach (array_keys($counts) as $key): ?>
        <a href="<?= e(app_url('dashboard.php?status=' . $key)) ?>" <?= $status === $key ? 'class="active" aria-current="page"' : '' ?>><?= e(ucfirst(strtolower($key))) ?></a>
    <?php endforeach; ?>
</nav>
<div class="firm-list">
    <?php if (!$firms): ?><div class="card empty">No <?= e(strtolower($status)) ?> registrations to show.</div><?php endif; ?>
    <?php foreach ($firms as $firm): ?>
        <article class="card firm-card">
            <div>
                <h3><?= e($firm['firm_name']) ?> <span class="badge <?= e(strtolower($firm['status'])) ?>"><?= e($firm['status']) ?></span></h3>
                <div class="firm-meta"><span>Registration: <?= e($firm['registration_number']) ?></span><span>Owner: <?= e($firm['owner_name']) ?></span></div>
                <div class="firm-meta"><span><?= e($firm['email']) ?></span><span><?= e($firm['phone']) ?></span><span><?= e($firm['address']) ?></span></div>
                <p class="help">Submitted <?= e($firm['created_at']) ?></p>
                <?php if ($firm['status'] === 'APPROVED'): ?><p class="help">Approved by <?= e($firm['approver_name']) ?> on <?= e($firm['approved_at']) ?></p><?php endif; ?>
                <?php if ($firm['rejection_reason']): ?><p>Reason: <?= e($firm['rejection_reason']) ?></p><?php endif; ?>
            </div>
            <?php if ($firm['status'] === 'PENDING'): ?>
                <form method="POST" action="<?= e(app_url('firm.php')) ?>" class="review">
                    <?= csrf_field() ?>
                    <input type="hidden" name="firm_id" value="<?= e($firm['firm_id']) ?>">
                    <div class="field"><label for="reason-<?= e($firm['firm_id']) ?>">Reason if rejecting</label><textarea id="reason-<?= e($firm['firm_id']) ?>" name="reason" maxlength="1000" placeholder="Explain what needs attention"></textarea></div>
                    <div class="review-actions"><button class="button small" name="action" value="approve" type="submit">Approve firm</button><button class="button danger small" name="action" value="reject" type="submit">Reject</button></div>
                </form>
            <?php endif; ?>
        </article>
    <?php endforeach; ?>
</div>
<?php if ($pages > 1): ?>
    <nav class="pagination" aria-label="Firm pages">
        <?php if ($page > 1): ?><a class="button secondary small" href="<?= e(app_url('dashboard.php?status=' . $status . '&page=' . ($page - 1))) ?>">Previous</a><?php endif; ?>
        <span>Page <?= e($page) ?> of <?= e($pages) ?></span>
        <?php if ($page < $pages): ?><a class="button secondary small" href="<?= e(app_url('dashboard.php?status=' . $status . '&page=' . ($page + 1))) ?>">Next</a><?php endif; ?>
    </nav>
<?php endif; ?>
