<?php
require_once __DIR__ . '/bootstrap.php';
$viewer = current_user();
$notice = take_flash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title ?? 'MLMS') ?> · MLMS</title>
    <link rel="stylesheet" href="<?= e(app_url('assets/app.css')) ?>">
</head>
<body>
<header class="topbar">
    <a class="brand" href="<?= e(app_url($viewer ? 'dashboard.php' : 'index.php')) ?>">ML<span>MS</span></a>
    <nav aria-label="Main navigation">
        <?php if ($viewer): ?>
            <a href="<?= e(app_url('dashboard.php')) ?>">Dashboard</a>
            <?php if ($viewer['role_name'] === 'CUSTOMER'): ?>
                <a href="<?= e(app_url('requestLoan.php')) ?>">Request a loan</a>
            <?php endif; ?>
            <?php if (can_manage_customers($viewer)): ?>
                <a href="<?= e(app_url('view/customers/listCustomers.php')) ?>">Customers</a>
            <?php endif; ?>
            <span class="account"><?= e($viewer['full_name']) ?></span>
            <form method="POST" action="<?= e(app_url('auth.php')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="logout">
                <button class="button secondary small" type="submit">Sign out</button>
            </form>
        <?php else: ?>
            <a href="<?= e(app_url('customerLogin.php')) ?>">Customer sign in</a>
            <a href="<?= e(app_url('login.php')) ?>">Staff sign in</a>
            <a class="button small" href="<?= e(app_url('registerFirm.php')) ?>">Register a firm</a>
        <?php endif; ?>
    </nav>
</header>
<main class="shell">
    <?php if ($notice): ?>
        <div role="status" class="notice <?= e($notice['type']) ?>"><?= e($notice['message']) ?></div>
    <?php endif; ?>
