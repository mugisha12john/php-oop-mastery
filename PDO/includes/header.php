<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/icons.php';
$viewer = current_user();
$notice = take_flash();
$path = $_SERVER['SCRIPT_NAME'] ?? '';
$navigation = [];
if ($viewer) {
    $role = $viewer['role_name'];
    $navigation[] = ['dashboard.php', t('Overview'), 'grid'];
    if ($role === 'CUSTOMER') {
        $navigation[] = ['requestLoan.php', t('Explore loans'), 'arrow'];
        $navigation[] = ['portal/documents.php', t('My documents'), 'file'];
        $navigation[] = ['portal/payments.php', t('Repayments'), 'wallet'];
        $navigation[] = ['portal/password.php', t('Change password'), 'check'];
    } else {
        if (can_manage_customers($viewer)) { $navigation[] = ['view/customers/listCustomers.php', t('Customers'), 'users']; }
        if (in_array($role, ['SUPER_ADMIN','MANAGER','FIRM'], true)) {
            $navigation[] = ['workspace/index.php?tab=applications', t('Applications'), 'file'];
            $navigation[] = ['portal/documents.php', t('Document reviews'), 'check'];
        }
        $navigation[] = ['workspace/index.php?tab=loans', t('Loan portfolio'), 'wallet'];
        if (in_array($role, ['SUPER_ADMIN','ACCOUNTANT'], true)) { $navigation[] = ['workspace/index.php?tab=payments', t('Payment confirmations'), 'check']; }
        if (in_array($role, ['SUPER_ADMIN','MANAGER','RECOVERY_OFFICER'], true)) { $navigation[] = ['workspace/index.php?tab=overdue', t('Overdue loans'), 'clock']; }
        if (in_array($role, ['SUPER_ADMIN','MANAGER','ACCOUNTANT'], true)) { $navigation[] = ['workspace/reports.php', t('Reports'), 'chart']; }
        if (in_array($role, ['SUPER_ADMIN','MANAGER','FIRM'], true)) { $navigation[] = ['workspace/products.php', t('Loan products'), 'layers']; }
        if ($role === 'SUPER_ADMIN') {
            $navigation[] = ['workspace/users.php', t('User accounts'), 'users'];
            $navigation[] = ['workspace/audit.php', t('Audit trail'), 'clock'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="<?= e(locale()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#203D43">
    <title><?= e(t($title ?? 'MLMS')) ?> <?= e(t('· MLMS')) ?></title>
    <link rel="stylesheet" href="<?= e(asset_url('assets/app.css')) ?>">
    <script src="<?= e(asset_url('assets/app.js')) ?>" defer></script>
    <script type="application/json" id="app-translations"><?= json_encode(javascript_translations(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?></script>
</head>
<body class="<?= $viewer ? 'authenticated' : 'public-page' ?>">
<a class="skip-link" href="#main-content"><?= e(t('Skip to content')) ?></a>
<?php if ($viewer): ?>
<aside class="sidebar">
    <a class="brand" href="<?= e(app_url('dashboard.php')) ?>"><span class="brand-mark"><?= ui_icon('arrow') ?></span>ML<span>MS</span><span class="brand-dot">.</span></a>
    <span class="workspace-label"><?= e($viewer['role_name'] === 'CUSTOMER' ? t('Your lending companion') : ($viewer['firm_name'] ?? t('Platform workspace'))) ?></span>
    <details class="workspace-menu" open><summary><?= e(t('Workspace navigation')) ?> <?= ui_icon('grid') ?></summary><nav class="side-nav" aria-label="<?= e(t('Main navigation')) ?>"><span class="nav-heading"><?= e(t('WORKSPACE')) ?></span>
        <?php foreach ($navigation as [$href,$label,$icon]):
            $navPath = explode('?', $href)[0];
            $active = str_ends_with($path, '/' . $navPath);
            if ($navPath === 'workspace/index.php') {
                parse_str(parse_url($href, PHP_URL_QUERY) ?? '', $navQuery);
                $defaultTab = $role === 'RECOVERY_OFFICER' ? 'overdue' : ($role === 'ACCOUNTANT' ? 'loans' : 'applications');
                $active = $active && ($navQuery['tab'] ?? '') === ($tab ?? $_GET['tab'] ?? $defaultTab);
            }
        ?><a href="<?= e(app_url($href)) ?>" <?= $active ? 'class="active" aria-current="page"' : '' ?>><?= ui_icon($icon) ?><span><?= e(t($label)) ?></span><?php if ($active): ?><span class="nav-dot"></span><?php endif; ?></a><?php endforeach; ?>
    </nav></details>
    <div class="sidebar-bottom"><div class="sidebar-note"><span class="sidebar-note-icon">↗</span><strong><?= e(t('Room for your next move.')) ?></strong><p><?= $viewer['role_name'] === 'CUSTOMER' ? t('Your goals. Your progress. All in one place.') : t('A connected workspace for every step in lending.') ?></p></div><form method="POST" action="<?= e(app_url('auth.php')) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="logout"><button class="signout" type="submit"><?= ui_icon('logout') ?> <?= e(t('Sign out')) ?></button></form></div>
</aside>
<header class="workspace-topbar"><span class="topbar-label">MLMS <span>/</span> <?= e(t($title ?? 'Overview')) ?></span><div class="topbar-account"><?php require __DIR__ . '/languageSwitcher.php'; ?><span class="today"><?= e(format_date('now', false, true)) ?></span><span class="avatar"><?= e(mb_strtoupper(mb_substr($viewer['full_name'], 0, 1))) ?></span><div><strong><?= e($viewer['full_name']) ?></strong><span><?= e(role_label($viewer['role_name'])) ?></span></div></div></header>
<?php else: ?>
<header class="topbar"><a class="brand" href="<?= e(app_url('index.php')) ?>"><span class="brand-mark"><?= ui_icon('arrow') ?></span>ML<span>MS</span><span class="brand-dot">.</span></a><nav aria-label="<?= e(t('Main navigation')) ?>"><?php require __DIR__ . '/languageSwitcher.php'; ?><a href="<?= e(app_url('customerLogin.php')) ?>"><?= e(t('Customer sign in')) ?></a><a href="<?= e(app_url('login.php')) ?>"><?= e(t('Staff sign in')) ?></a><a class="button small" href="<?= e(app_url('registerFirm.php')) ?>"><?= e(t('Register a firm ↗')) ?></a></nav></header>
<?php endif; ?>
<main class="shell" id="main-content">
    <?php if ($notice): ?><div role="status" class="notice <?= e($notice['type']) ?>"><?= e(t($notice['message'])) ?></div><?php endif; ?>
