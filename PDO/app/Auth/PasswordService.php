<?php
require_once dirname(__DIR__, 1) . '/autoload.php';

class PasswordService extends LendingAccess
{
    public function change(string $current, string $password, string $confirmation): int
    {
        $this->allow(['CUSTOMER']);
        if (strlen($password) < 12 || strlen($password) > 72) {
            throw new DomainException('Your new password must be between 12 and 72 bytes long.');
        }
        if ($password !== $confirmation) {
            throw new DomainException('Passwords do not match.');
        }
        if ($password === AuthService::CUSTOMER_DEFAULT_PASSWORD || $password === $current) {
            throw new DomainException('Choose a new personal password different from your current password and the default password.');
        }
        return $this->atomic(function () use ($current, $password): int {
            $account = $this->rows("SELECT u.* FROM users u JOIN roles r ON r.role_id=u.role_id
                WHERE u.user_id=? AND r.role_name='CUSTOMER' AND u.is_active=1 FOR UPDATE", [$this->actor['user_id']])[0] ?? null;
            if (!$account || (int) $account['password_version'] !== (int) $this->actor['password_version']) {
                throw new DomainException('Your account changed. Sign in again before changing your password.');
            }
            if (!password_verify($current, $account['password_hash'])) {
                throw new DomainException('Your current password is incorrect.');
            }
            $version = (int) $account['password_version'] + 1;
            $this->run('UPDATE users SET password_hash=?,must_change_password=0,password_version=?,password_changed_at=NOW(),failed_attempts=0,locked_until=NULL WHERE user_id=?',
                [password_hash($password, PASSWORD_DEFAULT), $version, $this->actor['user_id']]);
            $this->audit('PASSWORD_CHANGED', 'users', (int) $this->actor['user_id']);
            return $version;
        });
    }
}
