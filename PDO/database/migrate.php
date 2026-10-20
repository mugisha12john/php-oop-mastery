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
    $role = $db->prepare('INSERT INTO roles (role_name, description) VALUES (?, ?)
        ON DUPLICATE KEY UPDATE role_name = VALUES(role_name)');
    $role->execute(['SUPER_ADMIN', 'Approves firm registrations and manages the platform.']);
    $role->execute(['FIRM', 'Manages customers belonging to an approved firm.']);
    $role->execute(['CUSTOMER', 'Views own customer profile and submits loan applications.']);
    echo "Migration complete. Existing customers remain visible to super admins; new firm customers are scoped to their firm.\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'Migration failed: ' . $error->getMessage() . PHP_EOL);
    exit(1);
}
