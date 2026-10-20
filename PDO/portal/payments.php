<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../app/Lending/LendingService.php';
$user = require_role('CUSTOMER');
$lending = new LendingService(db(), $user);
$id = (int) ($_GET['loan_id'] ?? $_POST['loan_id'] ?? 0);
try {
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        require_post();
        consume_nonce('submit_payment_' . $id);
        $input = [];
        foreach (['amount', 'date', 'method', 'reference'] as $key) { $input[$key] = trim(post_string($key)); }
        $lending->submitPayment($id, $input);
        flash('success', t('Payment submitted for accountant confirmation. Your balance will update after confirmation.'));
        redirect('portal/payments.php?loan_id=' . $id);
    }
    $loans = $lending->loans();
    $loan = $id ? $lending->loan($id) : null;
    $activity = $loan ? $lending->activity($id) : null;
} catch (OutOfBoundsException $error) {
    http_response_code(404); exit(t('Loan not found.'));
} catch (DomainException $error) {
    flash('error', $error->getMessage()); redirect('portal/payments.php?loan_id=' . $id);
} catch (Throwable $error) {
    error_log('Payment submission failed: ' . $error->getMessage());
    flash('error', t('We could not save your payment. Check that the reference is unique and try again.'));
    redirect('portal/payments.php?loan_id=' . $id);
}
$title = t('Repayments');
require __DIR__ . '/../includes/header.php';
?>
<div class="page-heading"><div><span class="eyebrow"><?= e(t('Keep moving forward')) ?></span><h1><?= e(t('Your repayments')) ?></h1><p><?= e(t('Track every payment and see what remains.')) ?></p></div></div>
<?php if (!$loan): ?><section class="card"><h2><?= e(t('Choose a loan')) ?></h2><?php if (!$loans): ?><div class="empty"><h3><?= e(t('No issued loans yet.')) ?></h3><p><?= e(t('After approval and disbursement, manage your repayments here.')) ?></p><a class="button" href="<?= e(app_url('requestLoan.php')) ?>"><?= e(t('Explore loan products')) ?></a></div><?php endif; ?><div class="loan-picker"><?php foreach ($loans as $row): ?><a class="loan-pick" href="<?= e(app_url('portal/payments.php?loan_id=' . $row['loan_id'])) ?>"><div><strong><?= e($row['product_name']) ?></strong><span class="cell-note"><?= e(t('Loan #')) ?><?= e($row['loan_id']) ?> · <?= e(money($row['outstanding_balance_rwf'])) ?> <?= e(t('remaining')) ?></span></div><?= status_badge($row['display_status']) ?><span>→</span></a><?php endforeach; ?></div></section>
<?php else: ?>
<div class="portal-grid"><section class="card"><span class="eyebrow"><?= e(t('Loan #')) ?><?= e($loan['loan_id']) ?></span><h2><?= e($loan['product_name']) ?></h2><div class="balance-value"><?= e(format_number((float) $loan['outstanding_balance_rwf'], 2)) ?> <small>RWF</small></div><p class="muted"><?= e(t('Remaining balance ·')) ?> <?= e(status_label($loan['display_status'])) ?></p>
<?php if (in_array($loan['status'], LendingService::LIVE, true)): ?>
<form method="POST" action="<?= e(app_url('portal/payments.php')) ?>"><?= csrf_field() ?><?= form_nonce('submit_payment_' . $id) ?><input type="hidden" name="loan_id" value="<?= e($id) ?>">
<div class="row"><div class="field"><label for="amount"><?= e(t('Amount paid (RWF)')) ?></label><input id="amount" type="number" name="amount" min="0.01" max="<?= e($loan['outstanding_balance_rwf']) ?>" step="0.01" required></div><div class="field"><label for="date"><?= e(t('Payment date')) ?></label><input id="date" name="date" type="date" min="<?= e($loan['disbursement_date']) ?>" max="<?= e(date('Y-m-d')) ?>" value="<?= e(date('Y-m-d')) ?>" required></div></div>
<div class="field"><label for="method"><?= e(t('How did you pay?')) ?></label><select id="method" name="method"><option value="MOBILE_MONEY"><?= e(t('Mobile money')) ?></option><option value="BANK_TRANSFER"><?= e(t('Bank transfer')) ?></option></select></div><div class="field"><label for="reference"><?= e(t('Transaction reference')) ?></label><input id="reference" name="reference" minlength="6" maxlength="80" placeholder="<?= e(t('Reference from your bank or mobile money receipt')) ?>" required></div>
<p class="help"><?= e(t('Make the transfer using the payment instructions supplied by your firm, then submit its reference here. An accountant verifies the transfer before your balance changes. For cash payments, visit your firm for an accountant-issued receipt.')) ?></p><button class="button"><?= e(t('Submit payment details ↗')) ?></button></form>
<?php else: ?><p><?= e(t('Payment submissions are available after disbursement while the loan has a remaining balance.')) ?></p><?php endif; ?></section><aside class="card dark-card"><span class="eyebrow"><?= e(t('Your repayment journey')) ?></span><h2><?= e(t('Every payment')) ?><br><?= e(t('is progress.')) ?></h2><dl class="detail-list"><div><dt><?= e(t('Total payable')) ?></dt><dd><?= e(money($loan['total_payable_rwf'])) ?></dd></div><div><dt><?= e(t('Confirmed repayments')) ?></dt><dd><?= e(money(array_sum(array_column($activity['payments'], 'amount_paid_rwf')))) ?></dd></div><div><dt><?= e(t('Maturity date')) ?></dt><dd><?= e($loan['maturity_date'] ? format_date($loan['maturity_date']) : t('Awaiting disbursement')) ?></dd></div></dl><a class="button" href="<?= e(app_url('loan.php?id=' . $loan['application_id'])) ?>"><?= e(t('View loan details →')) ?></a></aside></div>
<?php require __DIR__ . '/../view/loans/activity.php'; ?>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
