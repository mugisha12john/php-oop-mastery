<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
$user = require_customer_access();
require_once __DIR__ . '/../../app/Auth/AuthService.php';
$state = $_SESSION['customer_forms']['register'] ?? [];
unset($_SESSION['customer_forms']['register']);
$input = $state['input'] ?? [];
$errors = $state['errors'] ?? [];
$formMode = 'create';
$title = t('Register customer');
require __DIR__ . '/../../includes/header.php';
?>
<div class="page-heading"><div><span class="eyebrow"><?= e(t('Customer management')) ?></span><h1><?= e(t('A new customer. A new possibility.')) ?></h1><p><?= e(t('Create their profile, then provision their customer login.')) ?></p></div></div>
<?php require __DIR__ . '/form.php'; require __DIR__ . '/../../includes/footer.php'; ?>
