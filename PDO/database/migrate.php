<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/../config/config.php';
$db = Database::getInstance()->getConnection();

try {
    // MySQL DDL commits implicitly. Each step is safe to rerun after interruption.
    $db->exec(file_get_contents(__DIR__ . '/schema.sql'));
    $column = $db->query("SELECT COUNT(*) FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customers' AND COLUMN_NAME = 'firm_id'")->fetchColumn();
    if (!$column) {
        $db->exec('ALTER TABLE customers ADD COLUMN firm_id INT UNSIGNED NULL, ADD INDEX idx_customers_firm (firm_id)');
    }
    $foreignKey = $db->query("SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customers'
        AND COLUMN_NAME = 'firm_id' AND REFERENCED_TABLE_NAME = 'firms'")->fetchColumn();
    if (!$foreignKey) {
        $db->exec('ALTER TABLE customers ADD CONSTRAINT fk_customers_firm
            FOREIGN KEY (firm_id) REFERENCES firms(firm_id) ON DELETE RESTRICT');
    }
    $ownerKey = $db->query("SELECT COUNT(*) FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'firms' AND INDEX_NAME = 'uq_firms_owner'")->fetchColumn();
    if (!$ownerKey) {
        $db->exec('ALTER TABLE firms ADD UNIQUE KEY uq_firms_owner (user_id)');
    }
    require __DIR__ . '/lending_migration.php';
    $roles = [
        1 => ['SUPER_ADMIN', 'Full system access; manages accounts and reads audit logs.'],
        2 => ['MANAGER', 'Reviews loan applications and reads reports.'],
        3 => ['ACCOUNTANT', 'Records disbursements and posts repayments.'],
        4 => ['RECOVERY_OFFICER', 'Works overdue loans and records borrower contact.'],
        5 => ['CUSTOMER', 'Views own profile, applications, schedules, and receipts.'],
        9 => ['FIRM', 'Manages customers belonging to an approved firm.'],
    ];
    foreach ($roles as $id => [$name, $description]) {
        $existing = $db->prepare('SELECT role_id FROM roles WHERE role_name=?');
        $existing->execute([$name]);
        if ($existing->fetchColumn()) { continue; }
        $occupied = $db->prepare('SELECT role_id FROM roles WHERE role_id=?');
        $occupied->execute([$id]);
        if ($occupied->fetchColumn()) {
            $db->prepare('INSERT INTO roles (role_name,description) VALUES (?,?)')->execute([$name,$description]);
        } else {
            $db->prepare('INSERT INTO roles (role_id,role_name,description) VALUES (?,?,?)')->execute([$id,$name,$description]);
        }
    }
    echo "Migration complete. Existing customers remain visible to super admins; new firm customers are scoped to their firm.\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'Migration failed: ' . $error->getMessage() . PHP_EOL);
    exit(1);
}
