<?php
require_once dirname(__DIR__, 1) . '/autoload.php';

class LendingAccess
{
    public function __construct(protected PDO $db, protected array $actor) {}

    protected function allow(array $roles): void
    {
        if (!in_array($this->actor['role_name'], $roles, true)) {
            throw new DomainException('Your role cannot perform this action.');
        }
        if ($this->actor['role_name'] !== 'SUPER_ADMIN' && $this->actor['role_name'] !== 'CUSTOMER'
            && (empty($this->actor['firm_id']) || $this->actor['firm_status'] !== 'APPROVED')) {
            throw new DomainException('Your account needs an approved firm assignment. Contact your administrator.');
        }
    }

    public function scope(string $alias = 'c'): string
    {
        if ($this->actor['role_name'] === 'CUSTOMER') {
            return "$alias.user_id = " . (int) $this->actor['user_id'];
        }
        $this->allow(['SUPER_ADMIN', 'FIRM', 'MANAGER', 'ACCOUNTANT', 'RECOVERY_OFFICER']);
        return $this->actor['role_name'] === 'SUPER_ADMIN' ? '1=1' : "$alias.firm_id = " . (int) $this->actor['firm_id'];
    }

    protected function rows(string $sql, array $params = []): array
    {
        $s = $this->db->prepare($sql);
        foreach (array_values($params) as $i => $value) {
            $s->bindValue($i + 1, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $s->execute();
        return $s->fetchAll();
    }

    protected function run(string $sql, array $params = []): void
    {
        $s = $this->db->prepare($sql);
        $s->execute($params);
    }

    protected function atomic(callable $work): mixed
    {
        try {
            $this->db->beginTransaction();
            $result = $work();
            $this->db->commit();
            return $result;
        } catch (Throwable $error) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $error;
        }
    }

    protected function audit(string $action, string $table, int $id, string $details = ''): void
    {
        $this->run('INSERT INTO audit_logs (user_id,action_performed,entity_table,entity_id,ip_address,details) VALUES (?,?,?,?,?,?)',
            [$this->actor['user_id'], $action, $table, $id, $_SERVER['REMOTE_ADDR'] ?? null, $details]);
    }

    public static function date(string $value, bool $future = false): string
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if (!$date || $date->format('Y-m-d') !== $value || (!$future && $value > date('Y-m-d'))) {
            throw new DomainException('Choose a valid date' . ($future ? '.' : ' on or before today.'));
        }
        return $value;
    }

    protected static function text(string $value, int $max, string $label): string
    {
        $value = trim($value);
        if ($value === '' || mb_strlen($value) > $max) {
            throw new DomainException("$label is required and must be at most $max characters.");
        }
        return $value;
    }

    public function customer(int $id, bool $lock = false): array
    {
        $rows = $this->rows('SELECT c.*, f.status AS firm_status FROM customers c LEFT JOIN firms f ON f.firm_id=c.firm_id
            WHERE c.customer_id=? AND ' . $this->scope() . ($lock ? ' FOR UPDATE' : ''), [$id]);
        if (!$rows) {
            throw new OutOfBoundsException('Customer not found.');
        }
        return $rows[0];
    }
}
