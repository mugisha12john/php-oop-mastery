<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
$user = require_customer_access();
require_once __DIR__ . '/../../app/Http/Controllers/CustomerController.php';
$customer_id = (int) ($_GET['id'] ?? 0);
$result = (new CustomerController())->getCustomer($customer_id);
if (!$result['success']) { http_response_code(404); exit(t('Customer not found')); }
$state = $_SESSION['customer_forms']['edit_' . $customer_id] ?? [];
unset($_SESSION['customer_forms']['edit_' . $customer_id]);
$input = array_replace($result['customer'], $state['input'] ?? []);
$errors = $state['errors'] ?? [];
$formMode = 'edit';
$title = t('Edit customer');
require __DIR__ . '/../../includes/header.php';
?>
<div class="page-heading"><div><span class="eyebrow"><?= e(t('Customer #')) ?><?= e($customer_id) ?></span><h1><?= e(t('Edit customer details')) ?></h1><p><?= e($result['customer']['full_name']) ?></p></div><?= status_badge($result['customer']['kyc_status']) ?></div>
<?php require __DIR__ . '/form.php'; require __DIR__ . '/../../includes/footer.php'; ?>
