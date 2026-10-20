-- Account, customer, and lending schema for a fresh installation.
-- Existing tables and records are preserved by migrate.php.
CREATE TABLE IF NOT EXISTS roles (
    role_id TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    role_name VARCHAR(30) NOT NULL UNIQUE,
    description VARCHAR(255) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS users (
    user_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    role_id TINYINT UNSIGNED NOT NULL,
    full_name VARCHAR(120) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    phone_number VARCHAR(20) NULL,
    password_hash VARCHAR(255) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    failed_attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
    locked_until DATETIME NULL,
    last_login_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles(role_id) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS firms (
    firm_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    firm_name VARCHAR(120) NOT NULL,
    registration_number VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(190) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    address VARCHAR(255) NOT NULL,
    status ENUM('PENDING','APPROVED','REJECTED') NOT NULL DEFAULT 'PENDING',
    approved_by INT UNSIGNED NULL,
    approved_at DATETIME NULL,
    rejection_reason TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_status (status),
    CONSTRAINT fk_firm_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    CONSTRAINT fk_firm_approved_by FOREIGN KEY (approved_by) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS customers (
    customer_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NULL UNIQUE,
    national_id_number CHAR(16) NOT NULL UNIQUE,
    full_name VARCHAR(120) NOT NULL,
    phone_number VARCHAR(20) NOT NULL,
    email VARCHAR(190) NULL,
    physical_address VARCHAR(255) NOT NULL,
    monthly_income_rwf DECIMAL(14,2) NOT NULL,
    kyc_status ENUM('PENDING','VERIFIED','REJECTED') NOT NULL DEFAULT 'PENDING',
    registered_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_customers_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL,
    CONSTRAINT fk_customers_registered_by FOREIGN KEY (registered_by) REFERENCES users(user_id) ON DELETE SET NULL,
    CONSTRAINT chk_nid_16_digits CHECK (national_id_number REGEXP '^[0-9]{16}$'),
    CONSTRAINT chk_income_positive CHECK (monthly_income_rwf >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Customer portal tables. Existing lending tables and product settings are retained.
CREATE TABLE IF NOT EXISTS loan_products (
    product_id SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_name VARCHAR(80) NOT NULL UNIQUE,
    description TEXT NULL,
    min_amount_rwf DECIMAL(14,2) NOT NULL,
    max_amount_rwf DECIMAL(14,2) NOT NULL,
    interest_rate_pct DECIMAL(5,2) NOT NULL,
    penalty_rate_pct DECIMAL(5,2) NOT NULL DEFAULT 0,
    default_term_months TINYINT UNSIGNED NOT NULL,
    max_term_months TINYINT UNSIGNED NOT NULL,
    requires_collateral TINYINT(1) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT chk_product_range CHECK (max_amount_rwf >= min_amount_rwf),
    CONSTRAINT chk_product_term CHECK (max_term_months >= default_term_months)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS loan_applications (
    application_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id INT UNSIGNED NOT NULL,
    product_id SMALLINT UNSIGNED NOT NULL,
    amount_requested_rwf DECIMAL(14,2) NOT NULL,
    term_months TINYINT UNSIGNED NOT NULL,
    purpose VARCHAR(255) NULL,
    status ENUM('SUBMITTED','UNDER_REVIEW','APPROVED','REJECTED','CANCELLED') NOT NULL DEFAULT 'SUBMITTED',
    submitted_by INT UNSIGNED NULL,
    submission_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reviewed_by INT UNSIGNED NULL,
    decision_date DATETIME NULL,
    approved_amount_rwf DECIMAL(14,2) NULL,
    manager_notes TEXT NULL,
    rejection_reason TEXT NULL,
    KEY idx_app_customer (customer_id),
    KEY idx_app_status (status),
    CONSTRAINT fk_app_customer FOREIGN KEY (customer_id) REFERENCES customers(customer_id),
    CONSTRAINT fk_app_product FOREIGN KEY (product_id) REFERENCES loan_products(product_id),
    CONSTRAINT fk_app_submitted_by FOREIGN KEY (submitted_by) REFERENCES users(user_id) ON DELETE SET NULL,
    CONSTRAINT fk_app_reviewed_by FOREIGN KEY (reviewed_by) REFERENCES users(user_id) ON DELETE SET NULL,
    CONSTRAINT chk_app_amount CHECK (amount_requested_rwf > 0),
    CONSTRAINT chk_reject_reason CHECK (status <> 'REJECTED' OR rejection_reason IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS loans (
    loan_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    application_id INT UNSIGNED NOT NULL UNIQUE,
    customer_id INT UNSIGNED NOT NULL,
    principal_amount_rwf DECIMAL(14,2) NOT NULL,
    interest_rate_pct DECIMAL(5,2) NOT NULL,
    interest_amount_rwf DECIMAL(14,2) NOT NULL,
    total_payable_rwf DECIMAL(14,2) NOT NULL,
    outstanding_balance_rwf DECIMAL(14,2) NOT NULL,
    term_months TINYINT UNSIGNED NOT NULL,
    status ENUM('APPROVED','DISBURSED','ACTIVE','OVERDUE','COMPLETED','DEFAULTED','WRITTEN_OFF') NOT NULL DEFAULT 'APPROVED',
    disbursement_date DATE NULL,
    maturity_date DATE NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_loan_customer (customer_id),
    CONSTRAINT fk_loan_application FOREIGN KEY (application_id) REFERENCES loan_applications(application_id),
    CONSTRAINT fk_loan_customer FOREIGN KEY (customer_id) REFERENCES customers(customer_id),
    CONSTRAINT chk_loan_balance CHECK (outstanding_balance_rwf >= 0),
    CONSTRAINT chk_loan_total CHECK (total_payable_rwf = principal_amount_rwf + interest_amount_rwf)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
