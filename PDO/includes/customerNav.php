<?php
require_once __DIR__ . '/bootstrap.php';
$user = require_customer_access();
?>
<nav aria-label="Account navigation" style="max-width:1200px;margin:0 auto 20px;display:flex;gap:16px;align-items:center;flex-wrap:wrap">
    <a href="<?= e(app_url('dashboard.php')) ?>">Dashboard</a>
    <span><?= e($user['firm_name'] ?? 'Super admin') ?></span>
    <form method="POST" action="<?= e(app_url('auth.php')) ?>" style="margin-left:auto">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="logout">
        <button type="submit" style="padding:8px 14px;cursor:pointer">Sign out</button>
    </form>
</nav>
