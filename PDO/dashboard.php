<?php
require_once __DIR__ . '/includes/bootstrap.php';
$user = require_login();
$title = 'Dashboard';
define('DASHBOARD_VIEW', true);

if ($user['role_name'] === 'SUPER_ADMIN') {
    require_once __DIR__ . '/modules/firmModel.php';
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
    require_once __DIR__ . '/modules/CustomerPortalService.php';
    $portal = new CustomerPortalService(db(), (int) $user['user_id']);
    $profile = $portal->profile();
    $summary = $portal->summary();
    $pages = max(1, (int) ceil($summary['total'] / 10));
    $page = min($pages, max(1, (int) ($_GET['page'] ?? 1)));
    $applications = $portal->applications($page);
    require __DIR__ . '/includes/header.php';
    require __DIR__ . '/view/dashboards/customer.php';
} else {
    require __DIR__ . '/includes/header.php';
    ?>
    <div class="page-heading"><div><span class="eyebrow"><?= e(str_replace('_', ' ', $user['role_name'])) ?></span><h1>Welcome, <?= e($user['full_name']) ?></h1></div></div>
    <section class="card"><h2>Your account is active</h2><p>Firm registration and approval workspaces are available to firm owners and super admins. Contact your administrator about access for your role.</p></section>
    <?php
}
require __DIR__ . '/includes/footer.php';
