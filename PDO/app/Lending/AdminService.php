<?php
require_once dirname(__DIR__, 1) . '/autoload.php';

class AdminService extends LendingAccess
{
    public function users(int $page = 1): array
    {
        $this->allow(['SUPER_ADMIN']);
        return $this->rows('SELECT u.user_id,u.full_name,u.email,u.is_active,u.firm_id,r.role_name,f.firm_name
            FROM users u JOIN roles r ON r.role_id=u.role_id LEFT JOIN firms f ON f.firm_id=u.firm_id
            ORDER BY u.user_id DESC LIMIT 21 OFFSET ?', [(max(1, $page) - 1) * 20]);
    }

    public function firms(): array
    {
        $this->allow(['SUPER_ADMIN']);
        return $this->rows("SELECT firm_id,firm_name FROM firms WHERE status='APPROVED' ORDER BY firm_name");
    }

    public function saveUser(array $input): void
    {
        $this->allow(['SUPER_ADMIN']);
        $id = filter_var($input['id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id) {
            $this->atomic(function () use ($id, $input): void {
                // Serialize administrator changes so the final active administrator is protected.
                $admins = $this->rows("SELECT u.user_id FROM users u JOIN roles r ON r.role_id=u.role_id WHERE r.role_name='SUPER_ADMIN' AND u.is_active=1 ORDER BY u.user_id FOR UPDATE");
                $target = $this->rows('SELECT u.*,r.role_name FROM users u JOIN roles r ON r.role_id=u.role_id WHERE user_id=? FOR UPDATE', [$id])[0] ?? null;
                if (!$target) {
                    throw new OutOfBoundsException('User not found.');
                }
                $active = $input['active'] === '1' ? 1 : 0;
                if (!$active && ((int) $id === (int) $this->actor['user_id'] || ($target['role_name'] === 'SUPER_ADMIN' && count($admins) <= 1))) {
                    throw new DomainException('You cannot deactivate your own account or the last active super admin.');
                }
                $firmId = $target['firm_id'];
                if (in_array($target['role_name'], ['MANAGER', 'ACCOUNTANT', 'RECOVERY_OFFICER'], true)) {
                    $firmId = $this->approvedFirm($input['firm_id']);
                }
                $this->run('UPDATE users SET is_active=?,firm_id=? WHERE user_id=?', [$active, $firmId, $id]);
                $this->audit('USER_UPDATED', 'users', $id, 'Active=' . $active . '; firm=' . ($firmId ?? 'none'));
            });
            return;
        }
        $name = self::text($input['name'], 120, 'Full name');
        $email = trim($input['email']);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
            throw new DomainException('Enter a valid email address.');
        }
        if (!in_array($input['role'], ['SUPER_ADMIN', 'MANAGER', 'ACCOUNTANT', 'RECOVERY_OFFICER'], true)) {
            throw new DomainException('Choose a staff role. Customers and firm owners use their existing registration flows.');
        }
        if (strlen($input['password']) < 12 || strlen($input['password']) > 72) {
            throw new DomainException('The password must be 12–72 bytes.');
        }
        $firmId = $input['role'] === 'SUPER_ADMIN' ? null : $this->approvedFirm($input['firm_id']);
        $this->atomic(function () use ($input, $name, $email, $firmId): void {
            $this->run('INSERT INTO users (full_name,email,password_hash,role_id,firm_id) VALUES (?,?,?,(SELECT role_id FROM roles WHERE role_name=?),?)',
                [$name, $email, password_hash($input['password'], PASSWORD_DEFAULT), $input['role'], $firmId]);
            $this->audit('USER_CREATED', 'users', (int) $this->db->lastInsertId(), $input['role']);
        });
    }

    private function approvedFirm(string $id): int
    {
        $firm = $this->rows("SELECT firm_id FROM firms WHERE firm_id=? AND status='APPROVED'", [$id])[0] ?? null;
        if (!$firm) {
            throw new DomainException('Choose an approved firm for this staff account.');
        }
        return (int) $firm['firm_id'];
    }

    public function auditLogs(int $page = 1): array
    {
        $this->allow(['SUPER_ADMIN']);
        return $this->rows('SELECT a.*,u.full_name FROM audit_logs a LEFT JOIN users u ON u.user_id=a.user_id ORDER BY audit_id DESC LIMIT 31 OFFSET ?', [(max(1, $page) - 1) * 30]);
    }

    public function products(): array
    {
        $this->allow(['SUPER_ADMIN', 'FIRM', 'MANAGER']);
        return $this->rows('SELECT p.*,f.firm_name FROM loan_products p LEFT JOIN firms f ON f.firm_id=p.firm_id'
            . ($this->actor['role_name'] === 'SUPER_ADMIN' ? '' : ' WHERE p.firm_id=' . (int) $this->actor['firm_id'])
            . ' ORDER BY p.product_name,p.product_id');
    }

    public function saveProduct(array $input): void
    {
        $this->allow(['SUPER_ADMIN', 'FIRM']);
        $name = self::text($input['name'], 80, 'Product name');
        if (mb_strlen($input['description']) > 3000) {
            throw new DomainException('Use at most 3000 characters for the description.');
        }
        $min = Money::cents($input['min']);
        $max = Money::cents($input['max']);
        $rate = Money::cents($input['rate'], false);
        $term = filter_var($input['term'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 255]]);
        $default = filter_var($input['default_term'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 255]]);
        if ($max < $min || $rate > 99999 || !$term || !$default || $default > $term) {
            throw new DomainException('Check the amount range, interest rate (0–999.99%), and default/maximum terms (1–255 months).');
        }
        $this->atomic(function () use ($input, $name, $min, $max, $rate, $term, $default): void {
            $values = [$name, trim($input['description']), Money::decimal($min), Money::decimal($max), Money::decimal($rate), $default, $term,
                $input['collateral'] === '1' ? 1 : 0, $input['active'] === '1' ? 1 : 0];
            $id = filter_var($input['id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($id) {
                $product = $this->rows('SELECT * FROM loan_products WHERE product_id=?'
                    . ($this->actor['role_name'] === 'FIRM' ? ' AND firm_id=' . (int) $this->actor['firm_id'] : '') . ' FOR UPDATE', [$id])[0] ?? null;
                if (!$product) {
                    throw new OutOfBoundsException('Product not found.');
                }
                $firmId = $product['firm_id'] === null ? $this->approvedFirm($input['firm_id'] ?? '') : (int) $product['firm_id'];
                if ($product['firm_id'] === null && $this->rows('SELECT a.application_id FROM loan_applications a JOIN customers c ON c.customer_id=a.customer_id
                    WHERE a.product_id=? AND (c.firm_id IS NULL OR c.firm_id<>?) LIMIT 1', [$id, $firmId])) {
                    throw new DomainException('This unassigned product has applications from another firm. Create a separate firm product to preserve that loan history.');
                }
                $this->run('UPDATE loan_products SET product_name=?,description=?,min_amount_rwf=?,max_amount_rwf=?,interest_rate_pct=?,default_term_months=?,max_term_months=?,requires_collateral=?,is_active=?,firm_id=? WHERE product_id=?', [...$values, $firmId, $id]);
            } else {
                $firmId = $this->actor['role_name'] === 'FIRM' ? (int) $this->actor['firm_id'] : $this->approvedFirm($input['firm_id'] ?? '');
                $this->run('INSERT INTO loan_products (product_name,description,min_amount_rwf,max_amount_rwf,interest_rate_pct,default_term_months,max_term_months,requires_collateral,is_active,firm_id) VALUES (?,?,?,?,?,?,?,?,?,?)', [...$values, $firmId]);
                $id = (int) $this->db->lastInsertId();
            }
            $this->audit('PRODUCT_SAVED', 'loan_products', $id);
        });
    }
}
