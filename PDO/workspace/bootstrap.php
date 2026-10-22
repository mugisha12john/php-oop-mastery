<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../app/Lending/LendingService.php';
$user = require_roles(['SUPER_ADMIN', 'FIRM', 'MANAGER', 'ACCOUNTANT', 'RECOVERY_OFFICER']);
$lending = new LendingService(db(), $user);
try {
    $lending->scope();
} catch (DomainException $error) {
    http_response_code(403);
    $title = t('Firm assignment required');
    require __DIR__ . '/../includes/header.php';
    ?><section class="card status-panel"><h1><?= e(t('Your workspace needs a firm.')) ?></h1><p><?= e(t($error->getMessage())) ?></p></section><?php
    require __DIR__ . '/../includes/footer.php';
    exit;
}
