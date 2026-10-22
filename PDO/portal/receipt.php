<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../app/Lending/LendingService.php';
$user = require_login();
try { $receipt = (new LendingService(db(), $user))->receipt((int) ($_GET['id'] ?? 0)); }
catch (OutOfBoundsException $error) { http_response_code(404); exit(t('Receipt not found.')); }
catch (DomainException $error) { http_response_code(403); exit(e(t($error->getMessage()))); }
$title = t('Payment receipt');
require __DIR__ . '/../includes/header.php';
?>
<section class="card receipt"><div class="section-heading"><a class="brand" href="<?= e(app_url('dashboard.php')) ?>">ML<span>MS</span></a><?= status_badge('CONFIRMED') ?></div><span class="eyebrow"><?= e($receipt['firm_name'] ?? t('Lending firm')) ?></span><h1><?= e(t('Payment receipt')) ?></h1><p class="muted"><?= e($receipt['receipt_number']) ?></p><div class="receipt-amount"><?= e(money($receipt['amount_paid_rwf'])) ?></div><dl class="detail-list detail-columns"><?php foreach (['full_name' => t('Customer'), 'product_name' => t('Loan product'), 'loan_id' => t('Loan number'), 'payment_date' => t('Payment date'), 'payment_method' => t('Payment method'), 'reference_code' => t('Transaction reference'), 'balance_after_rwf' => t('Balance after payment'), 'received_by' => t('Recorded by')] as $key => $label): ?><div><dt><?= e(t($label)) ?></dt><dd><?= e($key === 'balance_after_rwf' ? money($receipt[$key]) : ($key === 'payment_method' ? enum_label($receipt[$key]) : ($key === 'payment_date' ? format_date($receipt[$key]) : ($receipt[$key] ?? '—')))) ?></dd></div><?php endforeach; ?></dl><div class="button-group no-print"><button class="button" type="button" data-print><?= e(t('Print / save PDF')) ?></button><a class="button secondary" href="<?= e(app_url($user['role_name'] === 'CUSTOMER' ? 'portal/payments.php?loan_id=' . $receipt['loan_id'] : 'workspace/loan.php?id=' . $receipt['loan_id'])) ?>"><?= e(t('Back to loan')) ?></a></div><p class="help"><?= e(t('This receipt confirms a repayment recorded by your lending firm.')) ?></p></section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
