<?php

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/modules/AuthService.php';
require_post();

$action = post_string('action');
if ($action === 'logout') {
    $destination = (current_user()['role_name'] ?? '') === 'CUSTOMER' ? 'customerLogin.php' : 'login.php';
    $_SESSION = [];
    $cookie = session_get_cookie_params();
    setcookie(session_name(), '', ['expires' => time() - 3600, 'path' => $cookie['path'],
        'secure' => $cookie['secure'], 'httponly' => true, 'samesite' => 'Lax']);
    session_destroy();
    redirect($destination);
}
if (current_user()) {
    redirect('dashboard.php');
}
if (!in_array($action, ['login', 'customer_login', 'register'], true)) {
    http_response_code(400);
    exit('Invalid account action');
}

$input = [];
$service = new AuthService(db());
try {
    if (in_array($action, ['login', 'customer_login'], true)) {
        $input['email'] = trim(post_string('email'));
        $result = $service->login($input['email'], post_string('password'), $action === 'customer_login' ? 'CUSTOMER' : null);
        if ($result['success']) {
            session_regenerate_id(true);
            $_SESSION = ['user_id' => $result['user_id']];
            csrf_token();
            redirect('dashboard.php');
        }
    } else {
        foreach (['firm_name', 'registration_number', 'full_name', 'email', 'phone', 'address'] as $field) {
            $input[$field] = trim(post_string($field));
        }
        $result = $service->registerFirm($input, post_string('password'), post_string('password_confirmation'));
        if ($result['success']) {
            unset($_SESSION['forms']['register']);
            flash('success', 'Firm registered. Sign in to track approval. Customer access starts after super admin approval.');
            redirect('login.php');
        }
    }
} catch (Throwable $error) {
    error_log('Account action failed: ' . $error->getMessage());
    $result = ['success' => false, 'errors' => ['We could not complete that request. Please try again.']];
}
// Passwords are deliberately excluded from form state.
$_SESSION['forms'][$action] = ['input' => $input, 'errors' => $result['errors']];
redirect(match ($action) {
    'customer_login' => 'customerLogin.php',
    'login' => 'login.php',
    default => 'registerFirm.php',
});
