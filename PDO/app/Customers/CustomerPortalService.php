<?php
require_once dirname(__DIR__, 1) . '/autoload.php';

class CustomerPortalService
{
    public function __construct(private PDO $db, private int $userId) {}

    public function profile(bool $lock = false): ?array
    {
        $statement = $this->db->prepare('SELECT c.*, f.firm_name, f.status AS firm_status,
            f.phone AS firm_phone, f.email AS firm_email
            FROM customers c LEFT JOIN firms f ON f.firm_id = c.firm_id
            WHERE c.user_id = ?' . ($lock ? ' FOR UPDATE' : ''));
        $statement->execute([$this->userId]);
        $profile = $statement->fetch() ?: null;
        if ($profile) {
            $profile['documents_ready'] = DocumentService::ready($this->db, (int) $profile['customer_id']);
        }
        return $profile;
    }

    public function products(): array
    {
        $statement = $this->db->prepare("SELECT p.* FROM loan_products p
            JOIN customers c ON c.firm_id=p.firm_id JOIN firms f ON f.firm_id=p.firm_id
            WHERE c.user_id=? AND p.is_active=1 AND f.status='APPROVED' ORDER BY p.product_name");
        $statement->execute([$this->userId]);
        return $statement->fetchAll();
    }

    public function summary(): array
    {
        $statement = $this->db->prepare("SELECT COUNT(*) AS total,
            COALESCE(SUM(a.status IN ('SUBMITTED','UNDER_REVIEW')), 0) AS pending,
            COALESCE(SUM(a.status = 'APPROVED'), 0) AS approved
            FROM loan_applications a JOIN customers c ON c.customer_id = a.customer_id WHERE c.user_id = ?");
        $statement->execute([$this->userId]);
        return $statement->fetch();
    }

    public function applications(int $page): array
    {
        $statement = $this->db->prepare('SELECT a.*, p.product_name FROM loan_applications a
            JOIN customers c ON c.customer_id = a.customer_id JOIN loan_products p ON p.product_id = a.product_id
            WHERE c.user_id = ? ORDER BY a.submission_date DESC, a.application_id DESC LIMIT 10 OFFSET ?');
        $statement->execute([$this->userId, (max(1, $page) - 1) * 10]);
        return $statement->fetchAll();
    }

    public function application(int $id): ?array
    {
        // Ownership is checked in the query for every detail request.
        $statement = $this->db->prepare('SELECT a.*, p.product_name,
            l.loan_id, l.status AS loan_status, l.principal_amount_rwf, l.interest_rate_pct,
            l.interest_amount_rwf, l.total_payable_rwf, l.outstanding_balance_rwf,
            l.disbursement_date, l.maturity_date
            FROM loan_applications a JOIN customers c ON c.customer_id = a.customer_id
            JOIN loan_products p ON p.product_id = a.product_id
            LEFT JOIN loans l ON l.application_id = a.application_id AND l.customer_id = c.customer_id
            WHERE a.application_id = ? AND c.user_id = ?');
        $statement->execute([$id, $this->userId]);
        return $statement->fetch() ?: null;
    }

    public static function canRequest(?array $profile): bool
    {
        return $profile !== null && $profile['firm_id'] !== null && $profile['firm_status'] === 'APPROVED'
            && $profile['kyc_status'] === 'VERIFIED' && !empty($profile['documents_ready']);
    }

    public function submit(array $input): array
    {
        $errors = [];
        $amount = trim($input['amount_requested_rwf']);
        if (!preg_match('/^[0-9]{1,12}(?:\.[0-9]{1,2})?$/D', $amount) || (float) $amount <= 0) {
            $errors[] = 'Enter a positive loan amount with at most two decimal places.';
        }
        $term = filter_var($input['term_months'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 255]]);
        if ($term === false) {
            $errors[] = 'Enter a repayment term in whole months.';
        }
        $purpose = trim($input['purpose']);
        if ($purpose === '' || mb_strlen($purpose) > 255) {
            $errors[] = 'Tell us the purpose of your loan in 255 characters or fewer.';
        }
        if ($errors) {
            return ['success' => false, 'errors' => $errors];
        }
        try {
            $this->db->beginTransaction();
            $profile = $this->profile(true);
            if (!self::canRequest($profile)) {
                $this->db->rollBack();
                return ['success' => false, 'errors' => ['Loan requests require a verified customer profile with an approved lending firm. Contact your firm.']];
            }
            $statement = $this->db->prepare('SELECT * FROM loan_products WHERE product_id=? AND firm_id=? AND is_active=1 FOR UPDATE');
            $statement->execute([$input['product_id'], $profile['firm_id']]);
            $product = $statement->fetch();
            if (!$product) {
                $errors[] = 'Choose an available loan product.';
            } else {
                if ($product['requires_collateral'] && !DocumentService::ready($this->db, (int) $profile['customer_id'], true)) {
                    $errors[] = 'This product requires an approved supporting document for collateral.';
                }
                if ((float) $amount < (float) $product['min_amount_rwf'] || (float) $amount > (float) $product['max_amount_rwf']) {
                    $errors[] = 'The requested amount must be between ' . number_format((float) $product['min_amount_rwf'], 2)
                        . ' and ' . number_format((float) $product['max_amount_rwf'], 2) . ' RWF for this product.';
                }
                if ($term > (int) $product['max_term_months']) {
                    $errors[] = 'This product allows a repayment term of up to ' . $product['max_term_months'] . ' months.';
                }
            }
            if ($errors) {
                $this->db->rollBack();
                return ['success' => false, 'errors' => $errors];
            }
            $statement = $this->db->prepare("INSERT INTO loan_applications
                (customer_id, product_id, amount_requested_rwf, term_months, purpose, status, submitted_by)
                VALUES (?, ?, ?, ?, ?, 'SUBMITTED', ?)");
            $statement->execute([$profile['customer_id'], $product['product_id'], $amount, $term, $purpose, $this->userId]);
            $id = (int) $this->db->lastInsertId();
            $this->db->commit();
            return ['success' => true, 'application_id' => $id];
        } catch (Throwable $error) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $error;
        }
    }
}
