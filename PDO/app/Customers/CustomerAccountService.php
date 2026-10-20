<?php
require_once dirname(__DIR__, 1) . '/autoload.php';

class CustomerAccountService extends LendingAccess
{
    public function provision(int $customerId, string $email): int
    {
        return $this->atomic(fn () => $this->provisionInTransaction($customerId, $email));
    }

    public function provisionInTransaction(int $customerId, string $email): int
    {
        $this->allow(['FIRM', 'SUPER_ADMIN']);
        if (!$this->db->inTransaction()) {
            throw new LogicException('Customer login creation requires a transaction.');
        }
        $customer = $this->customer($customerId, true);
        if ($customer['user_id'] !== null) {
            throw new DomainException('This customer already has a linked account. Its password has not been changed.');
        }
        $email = strtolower(trim($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190) {
            throw new DomainException('Enter a valid customer email address.');
        }
        if ($this->rows('SELECT user_id FROM users WHERE email=?', [$email])) {
            throw new DomainException('That email already has an account. Use a different email; existing accounts cannot be linked here.');
        }
        $this->run("INSERT INTO users (role_id,full_name,email,phone_number,password_hash,must_change_password)
            VALUES ((SELECT role_id FROM roles WHERE role_name='CUSTOMER'),?,?,?,?,1)",
            [$customer['full_name'], $email, $customer['phone_number'], password_hash(AuthService::CUSTOMER_DEFAULT_PASSWORD, PASSWORD_DEFAULT)]);
        $id = (int) $this->db->lastInsertId();
        $this->run('UPDATE customers SET user_id=?,email=? WHERE customer_id=?', [$id, $email, $customerId]);
        $this->audit('CUSTOMER_LOGIN_CREATED', 'users', $id, 'Customer #' . $customerId . '; password change required.');
        return $id;
    }
}
