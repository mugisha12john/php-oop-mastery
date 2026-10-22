<?php
require_once __DIR__ . '/bootstrap.php';
$user = require_customer_access();
?>
<nav aria-label="<?= e(t('Account navigation')) ?>" style="max-width:1200px;margin:0 auto 20px;display:flex;gap:16px;align-items:center;flex-wrap:wrap">
    <a href="<?= e(app_url('dashboard.php')) ?>"><?= e(t('Dashboard')) ?></a>
    <span><?= e($user['firm_name'] ?? t('Super admin')) ?></span>
    <form method="POST" action="<?= e(app_url('auth.php')) ?>" style="margin-left:auto">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="logout">
        <button type="submit" style="padding:8px 14px;cursor:pointer"><?= e(t('Sign out')) ?></button>
    </form>
</nav>
