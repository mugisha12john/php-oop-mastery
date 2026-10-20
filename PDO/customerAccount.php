<?php
require_once __DIR__ . '/includes/bootstrap.php';
$actor = require_customer_access();
$id = filter_var($_GET['id'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$id) {
    http_response_code(400);
    exit('Invalid customer ID');
}
// Keep both the display and transactional write within the authenticated firm.
$query = 'SELECT * FROM customers WHERE customer_id = ?';
$params = [$id];
if ($actor['role_name'] !== 'SUPER_ADMIN') {
    $query .= ' AND firm_id = ?';
    $params[] = $actor['firm_id'];
}
$statement = db()->prepare($query);
$statement->execute($params);
$customer = $statement->fetch();
if (!$customer) {
    http_response_code(404);
    exit('Customer not found');
}
$errors = [];
$email = $customer['email'] ?? '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    require_post();
    $email = strtolower(trim(post_string('email')));
    $password = post_string('password');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190) {
        $errors[] = 'Enter a valid customer email address.';
    }
    if (strlen($password) < 12 || strlen($password) > 72) {
        $errors[] = 'Password must be between 12 and 72 bytes long.';
    }
    if ($password !== post_string('password_confirmation')) {
        $errors[] = 'Passwords do not match.';
    }
    if (!$errors) {
        try {
            db()->beginTransaction();
            $statement = db()->prepare($query . ' FOR UPDATE');
            $statement->execute($params);
            $locked = $statement->fetch();
            if (!$locked || $locked['user_id'] !== null) {
                db()->rollBack();
                $errors[] = 'This customer is unavailable or already has a linked account.';
            } else {
                $role = db()->query("SELECT role_id FROM roles WHERE role_name = 'CUSTOMER'")->fetchColumn();
                if (!$role) {
                    throw new RuntimeException('Run the database migration to add the CUSTOMER role.');
                }
                $statement = db()->prepare('INSERT INTO users (role_id, full_name, email, phone_number, password_hash) VALUES (?, ?, ?, ?, ?)');
                $statement->execute([$role, $locked['full_name'], $email, $locked['phone_number'], password_hash($password, PASSWORD_DEFAULT)]);
                $userId = db()->lastInsertId();
                $statement = db()->prepare('UPDATE customers SET user_id = ?, email = ? WHERE customer_id = ?');
                $statement->execute([$userId, $email, $id]);
                db()->commit();
                flash('success', 'Customer login created. The customer can now sign in to the customer portal with this email and password.');
                redirect('customerAccount.php?id=' . $id);
            }
        } catch (Throwable $error) {
            if (db()->inTransaction()) {
                db()->rollBack();
            }
            if ($error instanceof PDOException && $error->getCode() === '23000') {
                $errors[] = 'That email already has an account. Use a different email; existing accounts cannot be linked here.';
            } else {
                error_log('Customer account creation failed: ' . $error->getMessage());
                $errors[] = 'Could not create the login. Please try again.';
            }
        }
    }
}
$title = 'Customer login access';
require __DIR__ . '/includes/header.php';
?>
<div class="page-heading"><div><span class="eyebrow">Customer access</span><h1><?= e($customer['full_name']) ?></h1><p>Create a login for this customer’s loan dashboard.</p></div><a class="button secondary" href="<?= e(app_url('view/customers/viewCustomer.php?id=' . $id)) ?>">Back to customer</a></div>
<section class="card status-panel">
<?php if ($customer['user_id'] !== null): ?>
    <span class="badge approved">ACCOUNT LINKED</span><h2>Customer login is connected.</h2><p>This customer can use <a href="<?= e(app_url('customerLogin.php')) ?>">customer sign in</a> with their account credentials.</p>
<?php else: ?>
    <h2>Create customer login</h2><p class="muted">Give the customer their sign-in details directly. Their account will only have access to their own profile and loan requests.</p>
    <?php if ($errors): ?><div class="notice error" role="alert"><?php foreach ($errors as $error): ?><div><?= e($error) ?></div><?php endforeach; ?></div><?php endif; ?>
    <form method="POST" action="<?= e(app_url('customerAccount.php?id=' . $id)) ?>">
        <?= csrf_field() ?>
        <div class="field"><label for="email">Customer sign-in email</label><input id="email" name="email" type="email" autocomplete="off" maxlength="190" value="<?= e($email) ?>" required></div>
        <div class="row"><div class="field"><label for="password">Password (12–72 bytes)</label><input id="password" name="password" type="password" autocomplete="new-password" minlength="12" maxlength="72" required></div><div class="field"><label for="confirmation">Confirm password</label><input id="confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="12" maxlength="72" required></div></div>
        <button class="button" type="submit">Create customer login</button>
    </form>
<?php endif; ?>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
