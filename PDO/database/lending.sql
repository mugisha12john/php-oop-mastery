-- Complements schema.sql and preserves the tables supplied with the original database.
CREATE TABLE IF NOT EXISTS customer_documents (
 document_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 customer_id INT UNSIGNED NOT NULL,
 document_type ENUM('NATIONAL_ID','PROOF_OF_INCOME','OTHER') NOT NULL,
 file_path VARCHAR(255) NOT NULL,
 mime_type ENUM('application/pdf','image/jpeg','image/png') NOT NULL,
 file_size_bytes INT UNSIGNED NOT NULL,
 uploaded_by INT UNSIGNED NULL,
 uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (customer_id) REFERENCES customers(customer_id) ON DELETE CASCADE,
 FOREIGN KEY (uploaded_by) REFERENCES users(user_id) ON DELETE SET NULL,
 CHECK (file_size_bytes <= 5242880)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS disbursements (
 disbursement_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 loan_id INT UNSIGNED NOT NULL UNIQUE,
 amount_rwf DECIMAL(14,2) NOT NULL,
 disbursement_date DATE NOT NULL,
 payment_method ENUM('CASH','BANK_TRANSFER','MOBILE_MONEY') NOT NULL,
 external_reference VARCHAR(80) NOT NULL UNIQUE,
 disbursed_by INT UNSIGNED NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (loan_id) REFERENCES loans(loan_id),
 FOREIGN KEY (disbursed_by) REFERENCES users(user_id),
 CHECK (amount_rwf > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS repayment_schedules (
 schedule_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 loan_id INT UNSIGNED NOT NULL,
 installment_number TINYINT UNSIGNED NOT NULL,
 due_date DATE NOT NULL,
 principal_due_rwf DECIMAL(14,2) NOT NULL,
 interest_due_rwf DECIMAL(14,2) NOT NULL,
 penalty_due_rwf DECIMAL(14,2) NOT NULL DEFAULT 0,
 amount_paid_rwf DECIMAL(14,2) NOT NULL DEFAULT 0,
 status ENUM('PENDING','PARTIALLY_PAID','PAID','OVERDUE') NOT NULL DEFAULT 'PENDING',
 UNIQUE KEY (loan_id,installment_number),
 FOREIGN KEY (loan_id) REFERENCES loans(loan_id) ON DELETE CASCADE,
 CHECK (amount_paid_rwf >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS payments (
 payment_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 receipt_number VARCHAR(30) NOT NULL UNIQUE,
 loan_id INT UNSIGNED NOT NULL,
 amount_paid_rwf DECIMAL(14,2) NOT NULL,
 payment_date DATE NOT NULL,
 payment_method ENUM('CASH','BANK_TRANSFER','MOBILE_MONEY') NOT NULL,
 reference_code VARCHAR(80) NULL,
 balance_after_rwf DECIMAL(14,2) NOT NULL,
 received_by_user_id INT UNSIGNED NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (loan_id) REFERENCES loans(loan_id),
 FOREIGN KEY (received_by_user_id) REFERENCES users(user_id),
 CHECK (amount_paid_rwf > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS payment_allocations (
 allocation_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 payment_id INT UNSIGNED NOT NULL,
 schedule_id INT UNSIGNED NULL,
 allocated_to ENUM('PENALTY','INTEREST','PRINCIPAL') NOT NULL,
 amount_rwf DECIMAL(14,2) NOT NULL,
 FOREIGN KEY (payment_id) REFERENCES payments(payment_id) ON DELETE CASCADE,
 FOREIGN KEY (schedule_id) REFERENCES repayment_schedules(schedule_id) ON DELETE SET NULL,
 CHECK (amount_rwf > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS recovery_logs (
 log_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 loan_id INT UNSIGNED NOT NULL,
 officer_user_id INT UNSIGNED NOT NULL,
 contact_type ENUM('PHONE_CALL','PHYSICAL_VISIT','SMS','WARNING_LETTER') NOT NULL,
 summary_notes TEXT NOT NULL,
 promised_payment_date DATE NULL,
 next_followup_date DATE NULL,
 logged_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (loan_id) REFERENCES loans(loan_id) ON DELETE CASCADE,
 FOREIGN KEY (officer_user_id) REFERENCES users(user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS loan_status_history (
 history_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 loan_id INT UNSIGNED NOT NULL,
 old_status VARCHAR(20) NULL,
 new_status VARCHAR(20) NOT NULL,
 changed_by INT UNSIGNED NULL,
 reason VARCHAR(255) NULL,
 changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (loan_id) REFERENCES loans(loan_id) ON DELETE CASCADE,
 FOREIGN KEY (changed_by) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS audit_logs (
 audit_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id INT UNSIGNED NULL,
 action_performed VARCHAR(80) NOT NULL,
 entity_table VARCHAR(64) NULL,
 entity_id INT UNSIGNED NULL,
 ip_address VARCHAR(45) NULL,
 details TEXT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- A submission is a customer's report of payment; only an accountant posts money.
CREATE TABLE IF NOT EXISTS payment_submissions (
 submission_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 loan_id INT UNSIGNED NOT NULL,
 customer_id INT UNSIGNED NOT NULL,
 amount_rwf DECIMAL(14,2) NOT NULL,
 payment_method ENUM('BANK_TRANSFER','MOBILE_MONEY') NOT NULL,
 reference_code VARCHAR(80) NOT NULL UNIQUE,
 payment_date DATE NOT NULL,
 status ENUM('PENDING','CONFIRMED','REJECTED') NOT NULL DEFAULT 'PENDING',
 submitted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 reviewed_by INT UNSIGNED NULL,
 reviewed_at DATETIME NULL,
 review_notes VARCHAR(1000) NULL,
 payment_id INT UNSIGNED NULL UNIQUE,
 FOREIGN KEY (loan_id) REFERENCES loans(loan_id),
 FOREIGN KEY (customer_id) REFERENCES customers(customer_id),
 FOREIGN KEY (reviewed_by) REFERENCES users(user_id) ON DELETE SET NULL,
 FOREIGN KEY (payment_id) REFERENCES payments(payment_id),
 CHECK (amount_rwf > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS financial_references (
 reference_code VARCHAR(80) PRIMARY KEY,
 loan_id INT UNSIGNED NOT NULL,
 kind ENUM('DISBURSEMENT','PAYMENT') NOT NULL,
 FOREIGN KEY (loan_id) REFERENCES loans(loan_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
