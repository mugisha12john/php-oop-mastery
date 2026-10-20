<?php
require_once dirname(__DIR__, 1) . '/autoload.php';

class AuthService
{
    public const CUSTOMER_DEFAULT_PASSWORD = 'customer123456789';
    public function __construct(private PDO $db) {}

    public function login(string $email, string $password, ?string $requiredRole = null): array
    {
        $statement = $this->db->prepare('SELECT u.*, r.role_name FROM users u JOIN roles r ON r.role_id = u.role_id WHERE u.email = ?');
        $statement->execute([strtolower(trim($email))]);
        $user = $statement->fetch();
        $invalid = ['success' => false, 'errors' => ['Invalid email or password, or the account is unavailable.']];
        // Use a hash even for unknown accounts to keep password verification work consistent.
        $hash = $user['password_hash'] ?? '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';
        $valid = password_verify($password, $hash);
        if (!$user || !$user['is_active']) {
            return $invalid;
        }

        // Read the lock using the database clock, just like the writes below.
        $locked = $this->db->prepare('SELECT locked_until > NOW() FROM users WHERE user_id = ?');
        $locked->execute([$user['user_id']]);
        if ($locked->fetchColumn()) {
            return $invalid;
        }
        if (!$valid) {
            $failed = $this->db->prepare(
                'UPDATE users SET
                 failed_attempts = IF(locked_until IS NOT NULL AND locked_until <= NOW(), 1, LEAST(failed_attempts + 1, 255)),
                 locked_until = IF(failed_attempts >= 5, DATE_ADD(NOW(), INTERVAL 15 MINUTE), NULL)
                 WHERE user_id = ?'
            );
            $failed->execute([$user['user_id']]);
            return $invalid;
        }
        if ($requiredRole !== null && $user['role_name'] !== $requiredRole) {
            return $invalid;
        }

        $oldHash = $hash;
        $hash = password_needs_rehash($hash, PASSWORD_DEFAULT) ? password_hash($password, PASSWORD_DEFAULT) : $hash;
        $statement = $this->db->prepare(
            'UPDATE users SET failed_attempts=0,locked_until=NULL,last_login_at=NOW(),password_hash=?
             WHERE user_id=? AND BINARY password_hash=? AND password_version=? AND is_active=1'
        );
        $statement->execute([$hash, $user['user_id'], $oldHash, $user['password_version']]);
        $fresh = $this->db->prepare('SELECT password_hash,password_version,must_change_password,is_active FROM users WHERE user_id=?');
        $fresh->execute([$user['user_id']]);
        $account = $fresh->fetch();
        if (!$account || !$account['is_active'] || $account['password_hash'] !== $hash
            || (int) $account['password_version'] !== (int) $user['password_version']) {
            return $invalid;
        }
        return ['success' => true, 'user_id' => (int) $user['user_id'],
            'password_version' => (int) $account['password_version'],
            'must_change_password' => $user['role_name'] === 'CUSTOMER' && (bool) $account['must_change_password']];
    }

    public function registerFirm(array $data, string $password, string $confirmation): array
    {
        $errors = [];
        foreach (['firm_name' => 120, 'registration_number' => 50, 'full_name' => 120,
                  'email' => 190, 'phone' => 20, 'address' => 255] as $field => $max) {
            $data[$field] = trim($data[$field] ?? '');
            if ($data[$field] === '' || mb_strlen($data[$field]) > $max) {
                $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' is required and must be at most ' . $max . ' characters.';
            }
        }
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Enter a valid email address.';
        }
        if (strlen($password) < 12 || strlen($password) > 72) {
            $errors[] = 'Password must be between 12 and 72 bytes long.';
        }
        if ($password !== $confirmation) {
            $errors[] = 'Passwords do not match.';
        }
        if ($errors) {
            return ['success' => false, 'errors' => $errors];
        }

        $data['email'] = strtolower($data['email']);
        try {
            $this->db->beginTransaction();
            $role = $this->db->query("SELECT role_id FROM roles WHERE role_name = 'FIRM'")->fetchColumn();
            if (!$role) {
                throw new RuntimeException('FIRM role is missing. Run the database migration.');
            }
            $user = $this->db->prepare(
                'INSERT INTO users (role_id, full_name, email, phone_number, password_hash) VALUES (?, ?, ?, ?, ?)'
            );
            $user->execute([$role, $data['full_name'], $data['email'], $data['phone'], password_hash($password, PASSWORD_DEFAULT)]);
            $userId = $this->db->lastInsertId();
            $firm = $this->db->prepare(
                "INSERT INTO firms (user_id, firm_name, registration_number, email, phone, address, status)
                 VALUES (?, ?, ?, ?, ?, ?, 'PENDING')"
            );
            $firm->execute([$userId, $data['firm_name'], $data['registration_number'], $data['email'], $data['phone'], $data['address']]);
            $this->db->commit();
            return ['success' => true];
        } catch (Throwable $error) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            if ($error instanceof PDOException && $error->getCode() === '23000') {
                return ['success' => false, 'errors' => ['That email or firm registration number is already registered.']];
            }
            throw $error;
        }
    }
}
