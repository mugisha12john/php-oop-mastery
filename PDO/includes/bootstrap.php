<?php

require_once __DIR__ . '/../config/config.php';
header('Cache-Control: no-store');

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
        exit('Method not allowed');
    }
    $token = $_POST['csrf_token'] ?? null;
    if (!is_string($token) || !hash_equals(csrf_token(), $token)) {
        http_response_code(403);
        exit('Your form expired. Go back, refresh the page, and try again.');
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
                'SELECT u.user_id, u.full_name, u.email, r.role_name,
                        f.firm_id, f.firm_name, f.status AS firm_status, f.rejection_reason
                 FROM users u JOIN roles r ON r.role_id = u.role_id
                 LEFT JOIN firms f ON f.user_id = u.user_id
                 WHERE u.user_id = ? AND u.is_active = 1'
            );
            $statement->execute([$_SESSION['user_id']]);
            $user = $statement->fetch() ?: null;
            if ($user === null) {
                unset($_SESSION['user_id']);
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
    return $user;
}

function require_role(string $role): array
{
    $user = require_login();
    if ($user['role_name'] !== $role) {
        http_response_code(403);
        exit('You do not have access to this page.');
    }
    return $user;
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
        exit('Customer access requires an approved firm or a super admin account.');
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
        exit('Invalid form field');
    }
    return $value;
}
