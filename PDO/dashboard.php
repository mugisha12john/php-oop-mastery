<?php
require_once __DIR__ . '/includes/bootstrap.php';
$user = require_login();
$title = t('Dashboard');
define('DASHBOARD_VIEW', true);

if ($user['role_name'] === 'SUPER_ADMIN') {
    require_once __DIR__ . '/app/Firms/FirmModel.php';
    $model = new FirmModel(db());
    $counts = $model->counts();
    $status = $_GET['status'] ?? 'PENDING';
    if (!is_string($status) || !array_key_exists($status, $counts)) {
        $status = 'PENDING';
    }
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $pages = max(1, (int) ceil($counts[$status] / 20));
    $page = min($page, $pages);
    $firms = $model->list($status, $page);
    require __DIR__ . '/includes/header.php';
    require __DIR__ . '/view/dashboards/superAdmin.php';
} elseif ($user['role_name'] === 'FIRM') {
    $stats = null;
    if (can_manage_customers($user)) {
        $statement = db()->prepare("SELECT COUNT(*) AS total,
            COALESCE(SUM(kyc_status = 'VERIFIED'), 0) AS verified,
            COALESCE(SUM(kyc_status = 'PENDING'), 0) AS pending FROM customers WHERE firm_id = ?");
        $statement->execute([$user['firm_id']]);
        $stats = $statement->fetch();
    }
    require __DIR__ . '/includes/header.php';
    require __DIR__ . '/view/dashboards/firm.php';
} elseif ($user['role_name'] === 'CUSTOMER') {
    require_once __DIR__ . '/app/Customers/CustomerPortalService.php';
    $portal = new CustomerPortalService(db(), (int) $user['user_id']);
    $profile = $portal->profile();
    $summary = $portal->summary();
    $pages = max(1, (int) ceil($summary['total'] / 10));
    $page = min($pages, max(1, (int) ($_GET['page'] ?? 1)));
    $applications = $portal->applications($page);
    require_once __DIR__ . '/app/Lending/LendingService.php';
    $lending = new LendingService(db(), $user);
    $portfolio = $lending->summary();
    $customerLoans = $lending->loans();
    $products = $portal->products();
    require __DIR__ . '/includes/header.php';
    require __DIR__ . '/view/dashboards/customer.php';
} else {
    redirect('workspace/index.php');
}
require __DIR__ . '/includes/footer.php';
