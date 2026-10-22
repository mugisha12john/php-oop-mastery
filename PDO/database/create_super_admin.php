<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/../config/config.php';

$options = getopt('', ['name:', 'email:']);
$name = trim($options['name'] ?? '');
$email = strtolower(trim($options['email'] ?? ''));
$password = getenv('SUPER_ADMIN_PASSWORD');
if ($password === false) {
    fwrite(STDOUT, "Super admin password (12–72 bytes): ");
    $password = rtrim(fgets(STDIN) ?: '', "\r\n");
}
if ($name === '' || mb_strlen($name) > 120 || !filter_var($email, FILTER_VALIDATE_EMAIL)
    || strlen($email) > 190 || strlen($password) < 12 || strlen($password) > 72) {
    fwrite(STDERR, "Usage: php PDO/database/create_super_admin.php --name='Your Name' --email='you@example.com'\nUse a password of 12–72 bytes. Run migrate.php first.\n");
    exit(1);
}

try {
    $db = Database::getInstance()->getConnection();
    $role = $db->query("SELECT role_id FROM roles WHERE role_name = 'SUPER_ADMIN'")->fetchColumn();
    if (!$role) {
        throw new RuntimeException('Run migrate.php first.');
    }
    $statement = $db->prepare('INSERT INTO users (role_id, full_name, email, password_hash) VALUES (?, ?, ?, ?)');
    $statement->execute([$role, $name, $email, password_hash($password, PASSWORD_DEFAULT)]);
    echo "Super admin created. Sign in at PDO/login.php.\n";
} catch (Throwable $error) {
    // Never change the role or password of an existing account during setup.
    fwrite(STDERR, "Could not create super admin. Check for an existing email and verify the migration.\n");
    exit(1);
}
