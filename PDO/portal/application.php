<?php
require_once dirname(__DIR__, 1) . '/includes/bootstrap.php';
$user = require_role('CUSTOMER');
require_once dirname(__DIR__, 1) . '/app/Customers/CustomerPortalService.php';
$portal = new CustomerPortalService(db(), (int) $user['user_id']);
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    require_post();
    $token = post_string('request_token');
    if (empty($_SESSION['loan_request_token']) || !hash_equals($_SESSION['loan_request_token'], $token)) {
        flash('error', t('This request form has already been submitted or expired. Please start a new request.'));
        redirect('requestLoan.php');
    }
    $input = [];
    foreach (['product_id', 'amount_requested_rwf', 'term_months', 'purpose'] as $field) {
        $input[$field] = trim(post_string($field));
    }
    try {
        $result = $portal->submit($input);
        if ($result['success']) {
            unset($_SESSION['loan_request_token'], $_SESSION['forms']['loan_request']);
            flash('success', t('Your loan request has been submitted. You can follow its progress here.'));
            redirect('loan.php?id=' . $result['application_id']);
        }
    } catch (Throwable $error) {
        error_log('Loan request failed: ' . $error->getMessage());
        $result = ['errors' => [t('We could not submit your request. Please try again.')]];
    }
    $_SESSION['forms']['loan_request'] = ['input' => $input, 'errors' => $result['errors']];
    redirect('requestLoan.php');
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    header('Allow: GET, POST');
    http_response_code(405);
    exit(t('Method not allowed'));
}
$id = filter_var($_GET['id'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$application = $id ? $portal->application($id) : null;
if (!$application) {
    http_response_code(404);
    $title = t('Request not found');
    require dirname(__DIR__, 1) . '/includes/header.php';
    ?><section class="card status-panel"><h1><?= e(t('Request not found')) ?></h1><p><?= e(t('This loan request is not available in your account.')) ?></p><a class="button secondary" href="<?= e(app_url('dashboard.php')) ?>"><?= e(t('Back to dashboard')) ?></a></section><?php
    require dirname(__DIR__, 1) . '/includes/footer.php';
    exit;
}
$title = t('Loan request #') . $id;
require_once dirname(__DIR__, 1) . '/app/Lending/LendingService.php';
$lending = new LendingService(db(), $user);
$loan = $application['loan_id'] ? $lending->loan((int) $application['loan_id']) : null;
$activity = $loan ? $lending->activity((int) $loan['loan_id']) : null;
if ($loan) { $application['loan_status'] = $loan['display_status']; }
require dirname(__DIR__, 1) . '/includes/header.php';
?>
<div class="page-heading"><div><span class="eyebrow"><?= e(t('Loan request #')) ?><?= e($id) ?></span><h1><?= e($application['product_name']) ?></h1><p><?= e(t('Submitted')) ?> <?= e(format_date($application['submission_date'], true)) ?></p></div><a class="button secondary" href="<?= e(app_url('dashboard.php#requests')) ?>"><?= e(t('Back to your requests')) ?></a></div>
<div class="portal-grid">
    <section class="card"><div class="section-heading"><h2><?= e(t('Request details')) ?></h2><span class="badge <?= e(strtolower($application['status'])) ?>"><?= e(status_label($application['status'])) ?></span></div>
        <dl class="detail-list detail-columns"><div><dt><?= e(t('Requested amount')) ?></dt><dd class="large-value"><?= e(format_number((float) $application['amount_requested_rwf'], 2)) ?> RWF</dd></div><div><dt><?= e(t('Requested term')) ?></dt><dd><?= e($application['term_months']) ?> <?= e(t('months')) ?></dd></div><div><dt><?= e(t('Loan purpose')) ?></dt><dd class="preserve-lines"><?= e($application['purpose']) ?></dd></div><div><dt><?= e(t('Last decision date')) ?></dt><dd><?= $application['decision_date'] ? e(format_date($application['decision_date'], true)) : t('Awaiting decision') ?></dd></div><?php if ($application['approved_amount_rwf'] !== null): ?><div><dt><?= e(t('Approved amount')) ?></dt><dd><?= e(format_number((float) $application['approved_amount_rwf'], 2)) ?> RWF</dd></div><?php endif; ?></dl>
        <?php if ($application['status'] === 'REJECTED'): ?><div class="notice error"><strong><?= e(t('Decision reason')) ?></strong><p class="preserve-lines"><?= e($application['rejection_reason'] ?? t('Contact your lending firm for details.')) ?></p></div><?php elseif (in_array($application['status'], ['SUBMITTED', 'UNDER_REVIEW'], true)): ?><p class="help"><?= e(t('Your request is awaiting a decision. Check this page for updates or contact your lending firm.')) ?></p><?php elseif ($application['status'] === 'APPROVED' && !$application['loan_id']): ?><p class="help"><?= e(t('Your request is approved. Loan balance and disbursement details will appear when your firm creates the loan record.')) ?></p><?php endif; ?>
    </section>
    <aside class="card"><h2><?= e(t('Loan information')) ?></h2><?php if ($application['loan_id']): ?><span class="badge <?= e(strtolower($application['loan_status'])) ?>"><?= e(status_label($application['loan_status'])) ?></span><dl class="detail-list"><?php foreach (['principal_amount_rwf' => t('Principal'), 'interest_amount_rwf' => t('Interest amount'), 'total_payable_rwf' => t('Total payable'), 'outstanding_balance_rwf' => t('Outstanding balance')] as $key => $label): ?><div><dt><?= e(t($label)) ?></dt><dd><?= e(format_number((float) $application[$key], 2)) ?> RWF</dd></div><?php endforeach; ?><div><dt><?= e(t('Agreed interest rate')) ?></dt><dd><?= e(format_number($application['interest_rate_pct'], 2)) ?>%</dd></div><?php foreach (['disbursement_date' => t('Disbursement date'), 'maturity_date' => t('Maturity date')] as $key => $label): ?><div><dt><?= e(t($label)) ?></dt><dd><?= $application[$key] ? e(format_date($application[$key])) : t('Not scheduled') ?></dd></div><?php endforeach; ?></dl><?php else: ?><p class="muted"><?= e(t('No issued loan is linked to this request yet. Your loan terms, balance, and dates will appear here once available.')) ?></p><?php endif; ?></aside>
</div>
<?php if ($loan): ?><div class="button-group"><a class="button" href="<?= e(app_url('portal/payments.php?loan_id=' . $loan['loan_id'])) ?>"><?= e(t('Manage repayments ↗')) ?></a></div><?php require dirname(__DIR__, 1) . '/view/loans/activity.php'; ?><?php endif; ?>
<?php require dirname(__DIR__, 1) . '/includes/footer.php'; ?>
