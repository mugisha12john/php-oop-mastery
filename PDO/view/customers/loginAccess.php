<?php
require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/Customers/CustomerAccountService.php';
$actor = require_customer_access();
$id = filter_var($_GET['id'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$id) { http_response_code(400); exit(t('Invalid customer ID')); }
$accounts = new CustomerAccountService(db(), $actor);
try { $customer = $accounts->customer((int) $id); }
catch (OutOfBoundsException $error) { http_response_code(404); exit(t('Customer not found')); }
$errors = [];
$email = $customer['email'] ?? '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    require_post();
    $email = strtolower(trim(post_string('email')));
    try {
        $accounts->provision((int) $id, $email);
        flash('success', t('Customer login created with the default password. The customer must change it at first sign-in.'));
        redirect('customerAccount.php?id=' . $id);
    } catch (DomainException $error) {
        $errors[] = $error->getMessage();
    } catch (Throwable $error) {
        error_log('Customer account creation failed: ' . $error->getMessage());
        $errors[] = $error instanceof PDOException && $error->getCode() === '23000'
            ? t('That email already has an account. Use a different email.') : t('Could not create the login. Please try again.');
    }
}
$login = null;
if ($customer['user_id'] !== null) {
    $statement = db()->prepare('SELECT email,must_change_password FROM users WHERE user_id=?');
    $statement->execute([$customer['user_id']]);
    $login = $statement->fetch() ?: null;
}
$title = t('Customer login access');
require dirname(__DIR__, 2) . '/includes/header.php';
?>
<div class="page-heading"><div><span class="eyebrow"><?= e(t('Customer access')) ?></span><h1><?= e($customer['full_name']) ?></h1><p><?= e(t('Create a login for this customer’s loan dashboard.')) ?></p></div><a class="button secondary" href="<?= e(app_url('view/customers/viewCustomer.php?id=' . $id)) ?>"><?= e(t('Back to customer')) ?></a></div>
<section class="card status-panel">
<?php if ($login): ?>
    <span class="badge approved"><?= e(t('ACCOUNT LINKED')) ?></span><h2><?= e(t('Customer login is connected.')) ?></h2><p><?= e(t('Sign-in email:')) ?> <strong><?= e($login['email']) ?></strong></p>
    <?php if ($login['must_change_password']): ?><div class="notice"><strong><?= e(t('First sign-in password:')) ?> <code><?= e(AuthService::CUSTOMER_DEFAULT_PASSWORD) ?></code></strong><p><?= e(t('The customer must set a personal password before using the portal.')) ?></p></div><?php else: ?><p><?= e(t('This customer signs in using the personal password they chose.')) ?></p><?php endif; ?>
    <a class="button secondary" href="<?= e(app_url('customerLogin.php')) ?>"><?= e(t('Customer sign in →')) ?></a>
<?php else: ?>
    <h2><?= e(t('Create customer login')) ?></h2><p class="muted"><?= e(t('Give the customer their sign-in details directly. Their account accesses their own profile and loans.')) ?></p>
    <?php if ($errors): ?><div class="notice error" role="alert"><?php foreach ($errors as $error): ?><div><?= e(t($error)) ?></div><?php endforeach; ?></div><?php endif; ?>
    <form method="POST" action="<?= e(app_url('customerAccount.php?id=' . $id)) ?>"><?= csrf_field() ?>
        <div class="field"><label for="email"><?= e(t('Customer sign-in email')) ?></label><input id="email" name="email" type="email" autocomplete="off" maxlength="190" value="<?= e($email) ?>" required></div>
        <div class="notice"><strong><?= e(t('Default password:')) ?> <code><?= e(AuthService::CUSTOMER_DEFAULT_PASSWORD) ?></code></strong><p><?= e(t('The customer must change this password at first sign-in.')) ?></p></div>
        <button class="button" type="submit"><?= e(t('Create customer login')) ?></button>
    </form>
<?php endif; ?>
</section>
<?php require dirname(__DIR__, 2) . '/includes/footer.php'; ?>
