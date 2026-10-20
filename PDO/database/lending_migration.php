<?php
// Included by the CLI migration only. Changes are additive and rerunnable.
if (PHP_SAPI !== 'cli' || !isset($db)) {
    http_response_code(404);
    exit;
}
$db->exec(file_get_contents(__DIR__ . '/lending.sql'));
$addColumn = static function (string $table, string $column, string $definition) use ($db): void {
    $s = $db->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');
    $s->execute([$table, $column]);
    if (!$s->fetchColumn()) {
        $db->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
    }
};
$addColumn('users', 'firm_id', 'INT UNSIGNED NULL REFERENCES firms(firm_id)');
$addColumn('users', 'must_change_password', 'TINYINT(1) NOT NULL DEFAULT 0');
$addColumn('users', 'password_version', 'INT UNSIGNED NOT NULL DEFAULT 0');
$addColumn('users', 'password_changed_at', 'DATETIME NULL');
$addColumn('loan_products', 'firm_id', 'INT UNSIGNED NULL REFERENCES firms(firm_id)');
$addColumn('customer_documents', 'original_name', "VARCHAR(190) NOT NULL DEFAULT 'Document'");
$addColumn('customer_documents', 'review_status', "ENUM('PENDING','APPROVED','REJECTED') NOT NULL DEFAULT 'PENDING'");
$addColumn('customer_documents', 'reviewed_by', 'INT UNSIGNED NULL REFERENCES users(user_id)');
$addColumn('customer_documents', 'reviewed_at', 'DATETIME NULL');
$addColumn('customer_documents', 'review_notes', 'VARCHAR(1000) NULL');
foreach ([['users','firm_id','firms','firm_id','fk_staff_firm'], ['customer_documents','reviewed_by','users','user_id','fk_document_reviewer'], ['loan_products','firm_id','firms','firm_id','fk_product_firm']] as [$table,$column,$target,$targetId,$name]) {
    $s = $db->prepare('SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=? AND REFERENCED_TABLE_NAME=?');
    $s->execute([$table,$column,$target]);
    if (!$s->fetchColumn()) {
        $db->exec("ALTER TABLE `$table` ADD CONSTRAINT `$name` FOREIGN KEY (`$column`) REFERENCES `$target` (`$targetId`)");
    }
}
// Names are unique within a firm; two firms may offer a product with the same name.
$indexes = $db->query("SELECT INDEX_NAME, GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS columns_used
    FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='loan_products'
    AND NON_UNIQUE=0 AND INDEX_NAME<>'PRIMARY' GROUP BY INDEX_NAME")->fetchAll();
foreach ($indexes as $index) {
    if ($index['columns_used'] === 'product_name') {
        $name = str_replace('`', '``', $index['INDEX_NAME']);
        $db->exec("ALTER TABLE loan_products DROP INDEX `$name`");
    }
}
$productKey = $db->query("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE()
    AND TABLE_NAME='loan_products' AND INDEX_NAME='uq_product_firm_name'")->fetchColumn();
if (!$productKey) {
    $db->exec('ALTER TABLE loan_products ADD UNIQUE KEY uq_product_firm_name (firm_id,product_name)');
}
