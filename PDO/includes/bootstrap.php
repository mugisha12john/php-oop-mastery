<?php

require_once __DIR__ . '/../app/autoload.php';
require_once __DIR__ . '/../config/config.php';
date_default_timezone_set('Africa/Kigali');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');

if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_set_cookie_params([
        'httponly' => true,
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'samesite' => 'Lax',
        'path' => '/',
    ]);
    session_start();
}

function db(): PDO
{
    return Database::getInstance()->getConnection();
}

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function app_url(string $path = ''): string
{
    $base = getenv('APP_BASE_PATH');
    if ($base === false) {
        $script = $_SERVER['SCRIPT_NAME'] ?? '/PDO/index.php';
        $position = strpos($script, '/PDO/');
        $base = $position === false ? '/PDO' : substr($script, 0, $position + 4);
    }
    return rtrim($base, '/') . '/' . ltrim($path, '/');
}

function asset_url(string $path): string
{
    $version = substr(hash_file('sha256', __DIR__ . '/../' . $path), 0, 12);
    return app_url($path) . '?v=' . $version;
}

function language_return_path(string $path): string
{
    $parts = parse_url($path);
    $allowed = ['index.php','dashboard.php','login.php','customerLogin.php','registerFirm.php','customerAccount.php',
        'requestLoan.php','loan.php','portal/apply.php','portal/application.php','portal/documents.php','portal/payments.php',
        'portal/password.php','portal/receipt.php','workspace/index.php','workspace/application.php','workspace/loan.php',
        'workspace/products.php','workspace/users.php','workspace/reports.php','workspace/audit.php',
        'view/auth/login.php','view/auth/customerLogin.php','view/auth/registerFirm.php',
        'view/customers/listCustomers.php','view/customers/registerCustomer.php','view/customers/editCustomer.php',
        'view/customers/viewCustomer.php','view/customers/loginAccess.php'];
    if ($parts === false || isset($parts['scheme']) || isset($parts['host']) || strpbrk($path, "\r\n\\") !== false
        || !in_array($parts['path'] ?? '', $allowed, true)) { return 'index.php'; }
    return $parts['path'] . (isset($parts['query']) ? '?' . $parts['query'] : '');
}

function redirect(string $path): never
{
    header('Location: ' . app_url($path), true, 303);
    exit;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function require_post(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        header('Allow: POST');
        http_response_code(405);
        exit(t('Method not allowed'));
    }
    $token = $_POST['csrf_token'] ?? null;
    if (!is_string($token) || !hash_equals(csrf_token(), $token)) {
        http_response_code(403);
        exit(t('Your form expired. Go back, refresh the page, and try again.'));
    }
}

function current_user(): ?array
{
    static $loaded = false;
    static $user = null;
    if (!$loaded) {
        $loaded = true;
        if (isset($_SESSION['user_id'])) {
            $statement = db()->prepare(
                'SELECT u.user_id, u.full_name, u.email, u.must_change_password, u.password_version, r.role_name,
                        COALESCE(f.firm_id, sf.firm_id) AS firm_id,
                        COALESCE(f.firm_name, sf.firm_name) AS firm_name,
                        COALESCE(f.status, sf.status) AS firm_status, f.rejection_reason
                 FROM users u JOIN roles r ON r.role_id = u.role_id
                 LEFT JOIN firms f ON f.user_id = u.user_id
                 LEFT JOIN firms sf ON sf.firm_id = u.firm_id
                 WHERE u.user_id = ? AND u.is_active = 1'
            );
            $statement->execute([$_SESSION['user_id']]);
            $user = $statement->fetch() ?: null;
            if ($user === null || (int) $user['password_version'] !== (int) ($_SESSION['password_version'] ?? 0)) {
                $user = null;
                unset($_SESSION['user_id'], $_SESSION['password_version']);
            }
        }
    }
    return $user;
}

function require_login(): array
{
    $user = current_user();
    if ($user === null) {
        redirect('login.php');
    }
    if ($user['role_name'] === 'CUSTOMER' && $user['must_change_password']
        && ($_SERVER['SCRIPT_NAME'] ?? '') !== app_url('portal/password.php')) {
        redirect('portal/password.php');
    }
    return $user;
}

function require_role(string $role): array
{
    $user = require_login();
    if ($user['role_name'] !== $role) {
        http_response_code(403);
        exit(t('You do not have access to this page.'));
    }
    return $user;
}

function require_roles(array $roles): array
{
    $user = require_login();
    if (!in_array($user['role_name'], $roles, true)) {
        http_response_code(403);
        exit(t('You do not have access to this page.'));
    }
    return $user;
}

function money($value): string
{
    return format_number($value, 2) . ' RWF';
}

function status_badge(string $status): string
{
    return '<span class="badge ' . e(strtolower($status)) . '">' . e(status_label($status)) . '</span>';
}

function form_nonce(string $key): string
{
    $_SESSION['nonces'][$key] ??= bin2hex(random_bytes(24));
    return '<input type="hidden" name="form_nonce" value="' . e($_SESSION['nonces'][$key]) . '">';
}

function consume_nonce(string $key): void
{
    $token = post_string('form_nonce');
    if (empty($_SESSION['nonces'][$key]) || !hash_equals($_SESSION['nonces'][$key], $token)) {
        throw new DomainException(t('This form was already submitted or expired. Refresh the page and try again.'));
    }
    unset($_SESSION['nonces'][$key]);
}

function can_manage_customers(array $user): bool
{
    return $user['role_name'] === 'SUPER_ADMIN'
        || ($user['role_name'] === 'FIRM' && $user['firm_id'] !== null && $user['firm_status'] === 'APPROVED');
}

function require_customer_access(): array
{
    $user = require_login();
    if (!can_manage_customers($user)) {
        http_response_code(403);
        exit(t('Customer access requires an approved firm or a super admin account.'));
    }
    return $user;
}

function customer_scope(): ?int
{
    $user = require_customer_access();
    return $user['role_name'] === 'SUPER_ADMIN' ? null : (int) $user['firm_id'];
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function take_flash(): ?array
{
    $message = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $message;
}

function take_form(string $key): array
{
    $state = $_SESSION['forms'][$key] ?? [];
    unset($_SESSION['forms'][$key]);
    return $state;
}

function post_string(string $key): string
{
    $value = $_POST[$key] ?? '';
    if (!is_string($value)) {
        http_response_code(400);
        exit(t('Invalid form field'));
    }
    return $value;
}
