<?php
require_once __DIR__ . '/includes/bootstrap.php';
$user = require_role('CUSTOMER');
require_once __DIR__ . '/modules/CustomerPortalService.php';
$portal = new CustomerPortalService(db(), (int) $user['user_id']);
$profile = $portal->profile();
$products = $portal->products();
$state = take_form('loan_request');
$input = $state['input'] ?? [];
if (empty($_SESSION['loan_request_token'])) {
    $_SESSION['loan_request_token'] = bin2hex(random_bytes(32));
}
$title = 'Request a loan';
require __DIR__ . '/includes/header.php';
?>
<div class="page-heading"><div><span class="eyebrow">Your next step</span><h1>Request a loan</h1><p>Find a product that fits your needs and submit your request.</p></div><a class="button secondary" href="<?= e(app_url('dashboard.php')) ?>">Back to dashboard</a></div>
<?php if (!CustomerPortalService::canRequest($profile)): ?>
    <section class="card status-panel"><h2>Your profile needs attention.</h2><p>Before you request a loan, your customer profile must be verified and linked to an approved lending firm. Contact your firm to complete this step.</p><?php if ($profile): ?><p class="help">Current verification: <?= e($profile['kyc_status']) ?> · Firm: <?= e($profile['firm_name'] ?? 'Not assigned') ?></p><?php endif; ?></section>
<?php elseif (!$products): ?>
    <section class="card status-panel"><h2>No loan products are available yet.</h2><p>Contact your lending firm for information about upcoming products.</p></section>
<?php else: ?>
    <div class="portal-grid request-grid">
        <section class="card">
            <h2>Your loan request</h2>
            <?php if (!empty($state['errors'])): ?><div class="notice error" role="alert"><?php foreach ($state['errors'] as $error): ?><div><?= e($error) ?></div><?php endforeach; ?></div><?php endif; ?>
            <form method="POST" action="<?= e(app_url('loan.php')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="request_token" value="<?= e($_SESSION['loan_request_token']) ?>">
                <div class="field"><label for="product">Loan product</label><select id="product" name="product_id" required><option value="">Choose a loan product</option><?php foreach ($products as $product): ?><option value="<?= e($product['product_id']) ?>" <?= (string) ($input['product_id'] ?? '') === (string) $product['product_id'] ? 'selected' : '' ?>><?= e($product['product_name']) ?></option><?php endforeach; ?></select></div>
                <div class="row"><div class="field"><label for="amount">Amount requested (RWF)</label><input id="amount" name="amount_requested_rwf" type="number" min="0.01" max="999999999999.99" step="0.01" inputmode="decimal" value="<?= e($input['amount_requested_rwf'] ?? '') ?>" required></div><div class="field"><label for="term">Repayment term (months)</label><input id="term" name="term_months" type="number" min="1" max="255" step="1" value="<?= e($input['term_months'] ?? '') ?>" required></div></div>
                <div class="field"><label for="purpose">What will you use the loan for?</label><textarea id="purpose" name="purpose" maxlength="255" placeholder="Tell us about your plans…" required><?= e($input['purpose'] ?? '') ?></textarea></div>
                <p class="help">Your request will be submitted for review. Approval, final terms, and disbursement are subject to your lending firm’s decision.</p>
                <button class="button" type="submit">Submit loan request</button>
            </form>
        </section>
        <aside class="portal-sidebar"><h2 class="aside-title">Available products</h2><?php foreach ($products as $product): ?><section class="card product-card"><h3><?= e($product['product_name']) ?></h3><p class="muted"><?= e($product['description']) ?></p><dl class="detail-list"><div><dt>Amount range</dt><dd><?= e(number_format((float) $product['min_amount_rwf'])) ?>–<?= e(number_format((float) $product['max_amount_rwf'])) ?> RWF</dd></div><div><dt>Maximum term</dt><dd><?= e($product['max_term_months']) ?> months</dd></div><div><dt>Listed interest rate</dt><dd><?= e($product['interest_rate_pct']) ?>%</dd></div><div><dt>Collateral required</dt><dd><?= $product['requires_collateral'] ? 'Yes' : 'No' ?></dd></div></dl></section><?php endforeach; ?></aside>
    </div>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
