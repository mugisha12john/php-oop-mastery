<?php

class FirmModel
{
    public function __construct(private PDO $db) {}

    public function counts(): array
    {
        $counts = ['PENDING' => 0, 'APPROVED' => 0, 'REJECTED' => 0];
        foreach ($this->db->query('SELECT status, COUNT(*) AS total FROM firms GROUP BY status') as $row) {
            $counts[$row['status']] = (int) $row['total'];
        }
        return $counts;
    }

    public function list(string $status, int $page = 1): array
    {
        $statement = $this->db->prepare(
            'SELECT f.*, u.full_name AS owner_name, a.full_name AS approver_name
             FROM firms f JOIN users u ON u.user_id = f.user_id
             LEFT JOIN users a ON a.user_id = f.approved_by
             WHERE f.status = ? ORDER BY f.created_at DESC, f.firm_id DESC LIMIT 20 OFFSET ?'
        );
        $statement->bindValue(1, $status);
        $statement->bindValue(2, (max(1, $page) - 1) * 20, PDO::PARAM_INT);
        $statement->execute();
        return $statement->fetchAll();
    }

    public function review(int $firmId, string $decision, int $adminId, string $reason = ''): bool
    {
        if (!in_array($decision, ['APPROVED', 'REJECTED'], true)) {
            throw new InvalidArgumentException('Invalid decision.');
        }
        if ($decision === 'REJECTED' && (trim($reason) === '' || mb_strlen($reason) > 1000)) {
            throw new InvalidArgumentException('Give a rejection reason of at most 1000 characters.');
        }
        // Check the reviewer in SQL as well as at the protected HTTP entry point.
        $statement = $this->db->prepare(
            "UPDATE firms SET status = ?, approved_by = ?, approved_at = ?, rejection_reason = ?
             WHERE firm_id = ? AND status = 'PENDING'
             AND EXISTS (SELECT 1 FROM users u JOIN roles r ON r.role_id = u.role_id
                         WHERE u.user_id = ? AND u.is_active = 1 AND r.role_name = 'SUPER_ADMIN')"
        );
        $approved = $decision === 'APPROVED';
        $statement->execute([$decision, $approved ? $adminId : null,
            $approved ? $this->db->query('SELECT NOW()')->fetchColumn() : null,
            $approved ? null : trim($reason), $firmId, $adminId]);
        return $statement->rowCount() === 1;
    }
}
