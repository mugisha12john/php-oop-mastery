<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
$user = require_customer_access();
require_once __DIR__ . '/../../app/Http/Controllers/CustomerController.php';
$controller = new CustomerController();
$page = max(1,(int)($_GET['page'] ?? 1));
$search = is_string($_GET['search'] ?? null) ? trim($_GET['search']) : '';
$result = $search !== '' ? $controller->searchCustomers($search) : $controller->getAllCustomers($page,20);
$customers = $result['customers'] ?? [];
$title = t('Customers');
require __DIR__ . '/../../includes/header.php';
?>
<div class="page-heading"><div><span class="eyebrow"><?= e(t('Customer management')) ?></span><h1><?= e(t('People behind the possibilities.')) ?></h1><p><?= e($user['firm_name'] ?? t('All lending firms')) ?> <?= e(t('· Profiles, verification, and customer access.')) ?></p></div><a class="button" href="<?= e(app_url('view/customers/registerCustomer.php')) ?>"><?= e(t('+ Register customer')) ?></a></div>
<?php if (in_array($_GET['msg'] ?? '', ['created','updated'], true)): ?><div class="notice success"><?= e(t($_GET['msg'] === 'created' ? 'Customer created successfully' : 'Customer updated successfully')) ?></div><?php endif; ?>
<section class="card"><div class="section-heading"><h2><?= e(t('Your customers')) ?></h2><form class="filter-form" method="GET"><label class="sr-only" for="search"><?= e(t('Search customers')) ?></label><input id="search" name="search" value="<?= e($search) ?>" placeholder="<?= e(t('Name, phone, or national ID')) ?>" style="padding:9px 12px;border:1px solid var(--line);border-radius:8px;max-width:100%"><button class="button secondary small"><?= e(t('Search')) ?></button></form></div>
<?php if (!$customers): ?><div class="empty"><h3><?= $search !== '' ? t('No customers found matching your search.') : t('Your next customer starts here.') ?></h3><p><?= $search !== '' ? t('Try another name, phone number, or national ID.') : t('Register a customer and help them take their next step.') ?></p></div><?php else: ?><div class="table-wrap"><table class="data-table"><thead><tr><th><?= e(t('Customer')) ?></th><th><?= e(t('Contact')) ?></th><th><?= e(t('National ID')) ?></th><th><?= e(t('Verification')) ?></th><th></th></tr></thead><tbody><?php foreach ($customers as $customer): ?><tr><td><strong><?= e($customer['full_name']) ?></strong><span class="cell-note">#<?= e($customer['customer_id']) ?></span></td><td><?= e($customer['phone_number']) ?><span class="cell-note"><?= e($customer['email']) ?></span></td><td><?= e($customer['national_id_number']) ?></td><td><?= status_badge($customer['kyc_status']) ?></td><td><a href="<?= e(app_url('view/customers/viewCustomer.php?id=' . $customer['customer_id'])) ?>"><?= e(t('View customer →')) ?></a></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
<?php if ($search === '' && ($result['pages'] ?? 0)>1): ?><nav class="pagination" aria-label="<?= e(t('Customer pages')) ?>"><?php if ($page>1): ?><a href="?page=<?= e($page-1) ?>"><?= e(t('Previous')) ?></a><?php endif; ?><span><?= e(t('Page')) ?> <?= e($page) ?> <?= e(t('of')) ?> <?= e($result['pages']) ?></span><?php if ($page<$result['pages']): ?><a href="?page=<?= e($page+1) ?>"><?= e(t('Next')) ?></a><?php endif; ?></nav><?php endif; ?></section>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
