<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
$user = require_customer_access();
require_once __DIR__ . '/../../app/Http/Controllers/CustomerController.php';
$customer_id = (int) ($_GET['id'] ?? 0);
$result = (new CustomerController())->getCustomer($customer_id);
if (!$result['success']) { http_response_code(404); exit(t('Customer not found')); }
$customer = $result['customer'];
$title = t('Customer profile');
require __DIR__ . '/../../includes/header.php';
?>
<div class="page-heading"><div><span class="eyebrow"><?= e(t('Customer #')) ?><?= e($customer_id) ?></span><h1><?= e($customer['full_name']) ?></h1><p><?= e(t('Registered')) ?> <?= e(format_date($customer['created_at'])) ?></p></div><?= status_badge($customer['kyc_status']) ?></div>
<div class="portal-grid"><section class="card"><h2><?= e(t('Customer details')) ?></h2><dl class="detail-list detail-columns"><?php foreach (['national_id_number'=>t('National ID'),'full_name'=>t('Full name'),'phone_number'=>t('Phone number'),'email'=>t('Email address'),'physical_address'=>t('Physical address'),'monthly_income_rwf'=>t('Monthly income')] as $key=>$label): ?><div><dt><?= e(t($label)) ?></dt><dd><?= e($key==='monthly_income_rwf' ? money($customer[$key]) : ($customer[$key] ?? '—')) ?></dd></div><?php endforeach; ?></dl><div class="button-group"><a class="button" href="<?= e(app_url('view/customers/editCustomer.php?id=' . $customer_id)) ?>"><?= e(t('Edit details')) ?></a><a class="button secondary" href="<?= e(app_url('view/customers/listCustomers.php')) ?>"><?= e(t('Back to customers')) ?></a></div></section><aside class="card"><h2><?= e(t('Customer access')) ?></h2><p class="muted"><?= e(t('Create their login so they can upload documents, apply for loans, and manage repayments.')) ?></p><a class="button wide" href="<?= e(app_url('customerAccount.php?id=' . $customer_id)) ?>"><?= e(t('Customer login access')) ?></a><a class="button secondary wide spaced" href="<?= e(app_url('portal/documents.php?customer_id=' . $customer_id)) ?>"><?= e(t('Review documents ↗')) ?></a></aside></div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
