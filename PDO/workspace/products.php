<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../app/Lending/AdminService.php';
require_roles(['SUPER_ADMIN', 'MANAGER', 'FIRM']);
$admin = new AdminService(db(), $user);
$products = $admin->products();
$canEdit = in_array($user['role_name'], ['SUPER_ADMIN', 'FIRM'], true);
$firms = $user['role_name'] === 'SUPER_ADMIN' ? $admin->firms() : [];
$editing = null;
$editId = (int) ($_GET['edit'] ?? 0);
foreach ($products as $product) {
    if ((int) $product['product_id'] === $editId) { $editing = $product; }
}
if ($editId > 0 && !$editing) { http_response_code(404); exit(t('Product not found.')); }
$title = t('Loan products');
require __DIR__ . '/../includes/header.php';
?>
<div class="page-heading"><div><span class="eyebrow"><?= e(t('Lending options')) ?></span><h1><?= e(t('Products with a purpose.')) ?></h1><p><?= $user['role_name'] === 'SUPER_ADMIN' ? t('Manage products for each lending firm.') : t('Products offered to your firm’s customers.') ?> <?= e(t('Rates apply once to the approved principal.')) ?></p></div><?php if ($canEdit): ?><a class="button" href="<?= e(app_url('workspace/products.php#product-form')) ?>"><?= e(t('+ Create loan product')) ?></a><?php endif; ?></div>
<?php if (!$products): ?><section class="card empty"><h2><?= e(t('Your next product starts here.')) ?></h2><p><?= e(t('Create a loan product with its amounts, interest rate, and repayment terms. Active products appear to your firm’s customers.')) ?></p></section><?php endif; ?>
<?php if ($user['role_name'] === 'SUPER_ADMIN' && in_array(null, array_column($products, 'firm_id'), true)): ?><div class="notice"><strong><?= e(t('Some existing products need a firm.')) ?></strong><p><?= e(t('Assign an owning firm before customers can apply. Unassigned products keep their existing loan history.')) ?></p></div><?php endif; ?>
<div class="product-grid">
<?php foreach ($products as $product): ?>
<article class="card product-tile">
    <div class="section-heading"><span class="product-symbol">↗</span><?= status_badge($product['is_active'] ? 'ACTIVE' : 'INACTIVE') ?></div>
    <h2><?= e($product['product_name']) ?></h2>
    <?php if ($user['role_name'] === 'SUPER_ADMIN'): ?><span class="count-pill"><?= e($product['firm_name'] ?? t('Firm not assigned')) ?></span><?php endif; ?>
    <p class="muted"><?= e($product['description']) ?></p>
    <div class="product-rate"><?= e(format_number($product['interest_rate_pct'], 2)) ?><small><?= e(t('% flat interest')) ?></small></div>
    <dl class="detail-list"><div><dt><?= e(t('Amount range')) ?></dt><dd><?= e(money($product['min_amount_rwf'])) ?> – <?= e(money($product['max_amount_rwf'])) ?></dd></div><div><dt><?= e(t('Maximum term')) ?></dt><dd><?= e($product['max_term_months']) ?> <?= e(t('months')) ?></dd></div></dl>
    <?php if ($canEdit): ?><a class="button secondary wide" href="<?= e(app_url('workspace/products.php?edit=' . $product['product_id'] . '#product-form')) ?>"><?= e(t('Edit product')) ?></a><?php endif; ?>
</article>
<?php endforeach; ?>
</div>
<?php if ($canEdit): ?>
<section class="card spaced" id="product-form">
<h2><?= $editing ? t('Edit loan product') : t('Create loan product') ?></h2>
<form method="POST" action="<?= e(app_url('workspace/action.php')) ?>">
    <?= csrf_field() ?><?= form_nonce('product_' . ($editing['product_id'] ?? 0)) ?>
    <input type="hidden" name="action" value="product"><input type="hidden" name="id" value="<?= e($editing['product_id'] ?? '') ?>">
    <?php if ($user['role_name'] === 'SUPER_ADMIN' && (!$editing || $editing['firm_id'] === null)): ?>
    <div class="field"><label for="firm"><?= e(t('Owning firm')) ?></label><select id="firm" name="firm_id" required><option value=""><?= e(t('Choose an approved firm')) ?></option><?php foreach ($firms as $firm): ?><option value="<?= e($firm['firm_id']) ?>"><?= e($firm['firm_name']) ?></option><?php endforeach; ?></select></div>
    <?php else: ?><div class="notice"><?= e(t('Offered by')) ?> <strong><?= e($editing['firm_name'] ?? $user['firm_name']) ?></strong><?= e(t('. Active products are visible to this firm’s customers.')) ?></div><?php endif; ?>
    <div class="field"><label for="name"><?= e(t('Product name')) ?></label><input id="name" name="name" maxlength="80" value="<?= e($editing['product_name'] ?? '') ?>" required></div>
    <div class="field"><label for="description"><?= e(t('Description')) ?></label><textarea id="description" name="description" maxlength="3000"><?= e($editing['description'] ?? '') ?></textarea></div>
    <div class="row">
    <?php foreach (['min' => [t('Minimum amount (RWF)'),'min_amount_rwf'], 'max' => [t('Maximum amount (RWF)'),'max_amount_rwf']] as $key => [$label,$field]): ?>
        <div class="field"><label for="<?= e($key) ?>"><?= e(t($label)) ?></label><input id="<?= e($key) ?>" name="<?= e($key) ?>" type="number" min="0.01" max="999999999999.99" step="0.01" value="<?= e($editing[$field] ?? '') ?>" required></div>
    <?php endforeach; ?>
    </div>
    <div class="field"><label for="rate"><?= e(t('Flat interest rate (%)')) ?></label><input id="rate" name="rate" type="number" min="0" max="999.99" step="0.01" value="<?= e($editing['interest_rate_pct'] ?? '') ?>" required></div>
    <div class="row">
    <?php foreach (['default_term' => [t('Default term (months)'),'default_term_months'], 'term' => [t('Maximum term (months)'),'max_term_months']] as $key => [$label,$field]): ?>
        <div class="field"><label for="<?= e($key) ?>"><?= e(t($label)) ?></label><input id="<?= e($key) ?>" name="<?= e($key) ?>" type="number" min="1" max="255" value="<?= e($editing[$field] ?? '') ?>" required></div>
    <?php endforeach; ?>
    </div>
    <div class="row">
        <div class="field"><label for="collateral"><?= e(t('Requires collateral document')) ?></label><select id="collateral" name="collateral"><option value="0"><?= e(t('No')) ?></option><option value="1" <?= !empty($editing['requires_collateral']) ? 'selected' : '' ?>><?= e(t('Yes')) ?></option></select></div>
        <div class="field"><label for="active"><?= e(t('Availability')) ?></label><select id="active" name="active"><option value="1"><?= e(t('Active')) ?></option><option value="0" <?= $editing && !$editing['is_active'] ? 'selected' : '' ?>><?= e(t('Inactive')) ?></option></select></div>
    </div>
    <p class="help"><?= e(t('Product changes apply to future approvals. Issued loans keep their agreed terms.')) ?></p><button class="button"><?= e(t('Save product')) ?></button>
</form>
</section>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
