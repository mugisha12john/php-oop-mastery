<?php
require_once __DIR__ . '/includes/bootstrap.php';
$user = require_role('CUSTOMER');
require_once __DIR__ . '/modules/CustomerPortalService.php';
$portal = new CustomerPortalService(db(), (int) $user['user_id']);
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    require_post();
    $token = post_string('request_token');
    if (empty($_SESSION['loan_request_token']) || !hash_equals($_SESSION['loan_request_token'], $token)) {
        flash('error', 'This request form has already been submitted or expired. Please start a new request.');
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
            flash('success', 'Your loan request has been submitted. You can follow its progress here.');
            redirect('loan.php?id=' . $result['application_id']);
        }
    } catch (Throwable $error) {
        error_log('Loan request failed: ' . $error->getMessage());
        $result = ['errors' => ['We could not submit your request. Please try again.']];
    }
    $_SESSION['forms']['loan_request'] = ['input' => $input, 'errors' => $result['errors']];
    redirect('requestLoan.php');
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    header('Allow: GET, POST');
    http_response_code(405);
    exit('Method not allowed');
}
$id = filter_var($_GET['id'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$application = $id ? $portal->application($id) : null;
if (!$application) {
    http_response_code(404);
    $title = 'Request not found';
    require __DIR__ . '/includes/header.php';
    ?><section class="card status-panel"><h1>Request not found</h1><p>This loan request is not available in your account.</p><a class="button secondary" href="<?= e(app_url('dashboard.php')) ?>">Back to dashboard</a></section><?php
    require __DIR__ . '/includes/footer.php';
    exit;
}
$title = 'Loan request #' . $id;
require __DIR__ . '/includes/header.php';
?>
<div class="page-heading"><div><span class="eyebrow">Loan request #<?= e($id) ?></span><h1><?= e($application['product_name']) ?></h1><p>Submitted <?= e(date('d M Y, H:i', strtotime($application['submission_date']))) ?></p></div><a class="button secondary" href="<?= e(app_url('dashboard.php#requests')) ?>">Back to your requests</a></div>
<div class="portal-grid">
    <section class="card"><div class="section-heading"><h2>Request details</h2><span class="badge <?= e(strtolower($application['status'])) ?>"><?= e(str_replace('_', ' ', $application['status'])) ?></span></div>
        <dl class="detail-list detail-columns"><div><dt>Requested amount</dt><dd class="large-value"><?= e(number_format((float) $application['amount_requested_rwf'], 2)) ?> RWF</dd></div><div><dt>Requested term</dt><dd><?= e($application['term_months']) ?> months</dd></div><div><dt>Loan purpose</dt><dd class="preserve-lines"><?= e($application['purpose']) ?></dd></div><div><dt>Last decision date</dt><dd><?= $application['decision_date'] ? e(date('d M Y, H:i', strtotime($application['decision_date']))) : 'Awaiting decision' ?></dd></div><?php if ($application['approved_amount_rwf'] !== null): ?><div><dt>Approved amount</dt><dd><?= e(number_format((float) $application['approved_amount_rwf'], 2)) ?> RWF</dd></div><?php endif; ?></dl>
        <?php if ($application['status'] === 'REJECTED'): ?><div class="notice error"><strong>Decision reason</strong><p class="preserve-lines"><?= e($application['rejection_reason'] ?? 'Contact your lending firm for details.') ?></p></div><?php elseif (in_array($application['status'], ['SUBMITTED', 'UNDER_REVIEW'], true)): ?><p class="help">Your request is awaiting a decision. Check this page for updates or contact your lending firm.</p><?php elseif ($application['status'] === 'APPROVED' && !$application['loan_id']): ?><p class="help">Your request is approved. Loan balance and disbursement details will appear when your firm creates the loan record.</p><?php endif; ?>
    </section>
    <aside class="card"><h2>Loan information</h2><?php if ($application['loan_id']): ?><span class="badge <?= e(strtolower($application['loan_status'])) ?>"><?= e(str_replace('_', ' ', $application['loan_status'])) ?></span><dl class="detail-list"><?php foreach (['principal_amount_rwf' => 'Principal', 'interest_amount_rwf' => 'Interest amount', 'total_payable_rwf' => 'Total payable', 'outstanding_balance_rwf' => 'Outstanding balance'] as $key => $label): ?><div><dt><?= e($label) ?></dt><dd><?= e(number_format((float) $application[$key], 2)) ?> RWF</dd></div><?php endforeach; ?><div><dt>Agreed interest rate</dt><dd><?= e($application['interest_rate_pct']) ?>%</dd></div><?php foreach (['disbursement_date' => 'Disbursement date', 'maturity_date' => 'Maturity date'] as $key => $label): ?><div><dt><?= e($label) ?></dt><dd><?= $application[$key] ? e(date('d M Y', strtotime($application[$key]))) : 'Not scheduled' ?></dd></div><?php endforeach; ?></dl><?php else: ?><p class="muted">No issued loan is linked to this request yet. Your loan terms, balance, and dates will appear here once available.</p><?php endif; ?></aside>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
