<?php
require_once dirname(__DIR__, 1) . '/autoload.php';

class LendingService extends LendingAccess
{
    public const METHODS = ['CASH' => 'Cash', 'BANK_TRANSFER' => 'Bank transfer', 'MOBILE_MONEY' => 'Mobile money'];
    public const LIVE = ['DISBURSED', 'ACTIVE', 'OVERDUE', 'DEFAULTED'];

    public static function overdueSql(string $alias = 'l'): string
    {
        return "EXISTS (SELECT 1 FROM repayment_schedules rs WHERE rs.loan_id=$alias.loan_id AND rs.due_date<CURDATE()
            AND rs.amount_paid_rwf < rs.principal_due_rwf+rs.interest_due_rwf+rs.penalty_due_rwf)";
    }

    public function applications(string $status = '', int $page = 1): array
    {
        $this->allow(['SUPER_ADMIN', 'MANAGER', 'FIRM']);
        $where = $this->scope();
        $params = [];
        if ($status !== '') {
            $where .= ' AND a.status=?';
            $params[] = $status;
        }
        return $this->rows('SELECT a.*, c.full_name, p.product_name, f.firm_name FROM loan_applications a
            JOIN customers c ON c.customer_id=a.customer_id JOIN loan_products p ON p.product_id=a.product_id
            LEFT JOIN firms f ON f.firm_id=c.firm_id WHERE ' . $where . ' ORDER BY a.application_id DESC LIMIT 21 OFFSET ?', [...$params, (max(1, $page) - 1) * 20]);
    }

    public function application(int $id, bool $lock = false): array
    {
        $this->allow(['SUPER_ADMIN', 'MANAGER', 'FIRM']);
        $row = $this->rows('SELECT a.*,c.full_name,c.monthly_income_rwf,c.kyc_status,c.phone_number,c.firm_id,
            p.product_name,p.firm_id AS product_firm_id,p.interest_rate_pct,p.min_amount_rwf,p.max_amount_rwf,p.max_term_months,p.requires_collateral,p.is_active,
            f.status AS firm_status, l.loan_id FROM loan_applications a JOIN customers c ON c.customer_id=a.customer_id
            JOIN loan_products p ON p.product_id=a.product_id LEFT JOIN firms f ON f.firm_id=c.firm_id
            LEFT JOIN loans l ON l.application_id=a.application_id WHERE a.application_id=? AND ' . $this->scope()
            . ($lock ? ' FOR UPDATE' : ''), [$id])[0] ?? null;
        if (!$row) {
            throw new OutOfBoundsException('Application not found.');
        }
        return $row;
    }

    public function loans(string $filter = '', int $page = 1): array
    {
        $where = $this->scope();
        if ($filter === 'OVERDUE') {
            $where .= " AND l.status IN ('DISBURSED','ACTIVE','OVERDUE','DEFAULTED') AND " . self::overdueSql();
        } elseif ($filter !== '') {
            $where .= ' AND l.status=' . $this->db->quote($filter);
        }
        return $this->rows('SELECT l.*,c.full_name,p.product_name,
            CASE WHEN l.status IN (\'ACTIVE\',\'DISBURSED\',\'OVERDUE\') AND ' . self::overdueSql() . " THEN 'OVERDUE' ELSE l.status END AS display_status
            FROM loans l JOIN customers c ON c.customer_id=l.customer_id JOIN loan_applications a ON a.application_id=l.application_id
            JOIN loan_products p ON p.product_id=a.product_id WHERE " . $where . ' ORDER BY l.loan_id DESC LIMIT 21 OFFSET ?', [(max(1, $page) - 1) * 20]);
    }

    public function loan(int $id, bool $lock = false): array
    {
        $row = $this->rows('SELECT l.*,c.full_name,c.phone_number,c.firm_id,p.product_name,
            CASE WHEN l.status IN (\'ACTIVE\',\'DISBURSED\',\'OVERDUE\') AND ' . self::overdueSql() . " THEN 'OVERDUE' ELSE l.status END AS display_status
            FROM loans l JOIN customers c ON c.customer_id=l.customer_id JOIN loan_applications a ON a.application_id=l.application_id
            JOIN loan_products p ON p.product_id=a.product_id WHERE l.loan_id=? AND " . $this->scope()
            . ($lock ? ' FOR UPDATE' : ''), [$id])[0] ?? null;
        if (!$row) {
            throw new OutOfBoundsException('Loan not found.');
        }
        return $row;
    }

    public function activity(int $id): array
    {
        $this->loan($id);
        return [
            'schedule' => $this->rows("SELECT *, CASE WHEN amount_paid_rwf>=principal_due_rwf+interest_due_rwf+penalty_due_rwf THEN 'PAID' WHEN due_date<CURDATE() THEN 'OVERDUE' ELSE status END AS display_status FROM repayment_schedules WHERE loan_id=? ORDER BY installment_number", [$id]),
            'payments' => $this->rows('SELECT * FROM payments WHERE loan_id=? ORDER BY payment_id DESC', [$id]),
            'submissions' => $this->rows('SELECT * FROM payment_submissions WHERE loan_id=? ORDER BY submission_id DESC', [$id]),
            'history' => $this->rows('SELECT * FROM loan_status_history WHERE loan_id=? ORDER BY history_id DESC', [$id]),
            'disbursement' => $this->rows('SELECT * FROM disbursements WHERE loan_id=?', [$id])[0] ?? null,
        ];
    }

    public function summary(): array
    {
        return $this->rows("SELECT COUNT(*) AS loans,
            COALESCE(SUM(CASE WHEN l.status IN ('DISBURSED','ACTIVE','OVERDUE','DEFAULTED') THEN outstanding_balance_rwf ELSE 0 END),0) AS outstanding,
            COALESCE(SUM(l.status='APPROVED'),0) AS awaiting_disbursement,
            COALESCE(SUM(l.status IN ('DISBURSED','ACTIVE','OVERDUE','DEFAULTED') AND " . self::overdueSql() . "),0) AS overdue,
            (SELECT COALESCE(SUM(p.amount_paid_rwf),0) FROM payments p JOIN loans pl ON pl.loan_id=p.loan_id JOIN customers c ON c.customer_id=pl.customer_id WHERE " . $this->scope() . ') AS repaid
            FROM loans l JOIN customers c ON c.customer_id=l.customer_id WHERE ' . $this->scope())[0];
    }

    public function reviewApplication(int $id, string $decision, string $amount, string $notes): int
    {
        $this->allow(['MANAGER', 'SUPER_ADMIN']);
        if (!in_array($decision, ['APPROVED', 'REJECTED', 'UNDER_REVIEW'], true)) {
            throw new DomainException('Choose a valid application decision.');
        }
        $notes = trim($notes);
        if (mb_strlen($notes) > 1000 || ($decision === 'REJECTED' && $notes === '')) {
            throw new DomainException('Provide a rejection reason, with at most 1000 characters.');
        }
        $preview = $this->application($id);
        return $this->atomic(function () use ($preview, $id, $decision, $amount, $notes): int {
            $this->customer((int) $preview['customer_id'], true);
            $app = $this->application($id, true);
            if (!in_array($app['status'], ['SUBMITTED', 'UNDER_REVIEW'], true)) {
                throw new DomainException('This application already has a final decision.');
            }
            $loanId = 0;
            $approved = null;
            if ($decision === 'APPROVED') {
                if ($app['firm_status'] !== 'APPROVED' || $app['kyc_status'] !== 'VERIFIED'
                    || !DocumentService::ready($this->db, (int) $app['customer_id'], (bool) $app['requires_collateral'])) {
                    throw new DomainException('Approval requires an approved firm and approved customer documents, including collateral documents when required.');
                }
                $principal = Money::cents($amount);
                if ($app['product_firm_id'] === null || (int) $app['product_firm_id'] !== (int) $app['firm_id']) {
                    throw new DomainException('This product does not belong to the customer’s firm. The customer must choose a product offered by their firm.');
                }
                if (!$app['is_active'] || $principal < Money::cents($app['min_amount_rwf'])
                    || $principal > Money::cents($app['max_amount_rwf']) || $principal > Money::cents($app['amount_requested_rwf'])
                    || $app['term_months'] > $app['max_term_months']) {
                    throw new DomainException('The approved amount must fit the active product and cannot exceed the requested amount. Check the product term.');
                }
                $interest = Money::interest($principal, $app['interest_rate_pct']);
                if ($principal + $interest > 99999999999999) {
                    throw new DomainException('The total payable exceeds the supported amount.');
                }
                $approved = Money::decimal($principal);
                $total = Money::decimal($principal + $interest);
                $this->run("INSERT INTO loans (application_id,customer_id,principal_amount_rwf,interest_rate_pct,interest_amount_rwf,total_payable_rwf,outstanding_balance_rwf,term_months,status) VALUES (?,?,?,?,?,?,?,?,'APPROVED')",
                    [$id, $app['customer_id'], $approved, $app['interest_rate_pct'], Money::decimal($interest), $total, $total, $app['term_months']]);
                $loanId = (int) $this->db->lastInsertId();
                $this->history($loanId, null, 'APPROVED', 'Application approved; awaiting disbursement.');
            }
            $this->run('UPDATE loan_applications SET status=?,reviewed_by=?,decision_date=?,approved_amount_rwf=?,manager_notes=?,rejection_reason=? WHERE application_id=?',
                [$decision, $this->actor['user_id'], $decision === 'UNDER_REVIEW' ? null : date('Y-m-d H:i:s'), $approved,
                 $notes ?: null, $decision === 'REJECTED' ? $notes : null, $id]);
            $this->audit('APPLICATION_' . $decision, 'loan_applications', $id, $notes);
            return $loanId;
        });
    }

    private function history(int $loanId, ?string $old, string $new, string $reason): void
    {
        $this->run('INSERT INTO loan_status_history (loan_id,old_status,new_status,changed_by,reason) VALUES (?,?,?,?,?)',
            [$loanId, $old, $new, $this->actor['user_id'], $reason]);
    }

    private static function reference(string $reference): string
    {
        $reference = strtoupper(trim($reference));
        if (!preg_match('/^[A-Z0-9][A-Z0-9._\/-]{5,79}$/D', $reference)) {
            throw new DomainException('Use a transaction reference of 6–80 letters, numbers, dots, slashes, or hyphens.');
        }
        return $reference;
    }

    private function claimReference(string $reference, int $loanId, string $kind, ?int $submissionId = null): void
    {
        if ($this->rows('SELECT payment_id FROM payments WHERE reference_code=?', [$reference])
            || $this->rows('SELECT disbursement_id FROM disbursements WHERE external_reference=?', [$reference])
            || $this->rows('SELECT submission_id FROM payment_submissions WHERE reference_code=? AND submission_id<>?', [$reference, $submissionId ?? 0])) {
            throw new DomainException('This transaction reference has already been used.');
        }
        $this->run('INSERT INTO financial_references (reference_code,loan_id,kind) VALUES (?,?,?)', [$reference, $loanId, $kind]);
    }

    private static function method(string $method, bool $customer = false): string
    {
        if (!isset(self::METHODS[$method]) || ($customer && $method === 'CASH')) {
            throw new DomainException('Choose a valid payment method.');
        }
        return $method;
    }

    public function disburse(int $id, array $input): void
    {
        $this->allow(['ACCOUNTANT', 'SUPER_ADMIN']);
        $date = self::date($input['date']);
        $method = self::method($input['method']);
        $reference = self::reference($input['reference']);
        $this->atomic(function () use ($id, $date, $method, $reference): void {
            $loan = $this->loan($id, true);
            if ($loan['status'] !== 'APPROVED') {
                throw new DomainException('Only an approved, undisbursed loan can be disbursed.');
            }
            if ($date < substr($loan['created_at'], 0, 10)) {
                throw new DomainException('Disbursement cannot predate the loan approval.');
            }
            if ($this->rows('SELECT schedule_id FROM repayment_schedules WHERE loan_id=?', [$id])) {
                throw new DomainException('A repayment schedule already exists. Ask your administrator to review this loan.');
            }
            $this->claimReference($reference, $id, 'DISBURSEMENT');
            $this->run('INSERT INTO disbursements (loan_id,amount_rwf,disbursement_date,payment_method,external_reference,disbursed_by) VALUES (?,?,?,?,?,?)',
                [$id, $loan['principal_amount_rwf'], $date, $method, $reference, $this->actor['user_id']]);
            $term = (int) $loan['term_months'];
            $principal = Money::cents($loan['principal_amount_rwf']);
            $interest = Money::cents($loan['interest_amount_rwf'], false);
            $start = new DateTimeImmutable($date);
            for ($n = 1; $n <= $term; $n++) {
                $month = $start->modify('first day of this month')->modify("+$n months");
                $due = $month->setDate((int) $month->format('Y'), (int) $month->format('m'), min((int) $start->format('d'), (int) $month->format('t')))->format('Y-m-d');
                $p = intdiv($principal, $term) + ($n === $term ? $principal % $term : 0);
                $i = intdiv($interest, $term) + ($n === $term ? $interest % $term : 0);
                $this->run('INSERT INTO repayment_schedules (loan_id,installment_number,due_date,principal_due_rwf,interest_due_rwf) VALUES (?,?,?,?,?)',
                    [$id, $n, $due, Money::decimal($p), Money::decimal($i)]);
            }
            $this->run("UPDATE loans SET status='ACTIVE',disbursement_date=?,maturity_date=? WHERE loan_id=?", [$date, $due, $id]);
            $this->history($id, $loan['status'], 'ACTIVE', 'Funds disbursed and monthly schedule created.');
            $this->audit('LOAN_DISBURSED', 'loans', $id, $reference);
        });
    }

    public function submitPayment(int $id, array $input): void
    {
        $this->allow(['CUSTOMER']);
        $amount = Money::cents($input['amount']);
        $date = self::date($input['date']);
        $method = self::method($input['method'], true);
        $reference = self::reference($input['reference']);
        $this->atomic(function () use ($id, $amount, $date, $method, $reference): void {
            $loan = $this->loan($id, true);
            if (!in_array($loan['status'], self::LIVE, true) || !$loan['disbursement_date'] || $date < $loan['disbursement_date']) {
                throw new DomainException('Payments require a disbursed loan and a date on or after disbursement.');
            }
            $pending = $this->rows("SELECT COALESCE(SUM(amount_rwf),0) AS total FROM payment_submissions WHERE loan_id=? AND status='PENDING'", [$id])[0]['total'];
            if ($amount + Money::cents($pending, false) > Money::cents($loan['outstanding_balance_rwf'], false)) {
                throw new DomainException('This amount exceeds your balance after pending payment submissions.');
            }
            if ($this->rows('SELECT reference_code FROM financial_references WHERE reference_code=?', [$reference])
                || $this->rows('SELECT payment_id FROM payments WHERE reference_code=?', [$reference])
                || $this->rows('SELECT disbursement_id FROM disbursements WHERE external_reference=?', [$reference])) {
                throw new DomainException('This transaction reference has already been recorded.');
            }
            $this->run('INSERT INTO payment_submissions (loan_id,customer_id,amount_rwf,payment_method,reference_code,payment_date) VALUES (?,?,?,?,?,?)',
                [$id, $loan['customer_id'], Money::decimal($amount), $method, $reference, $date]);
            $this->audit('PAYMENT_SUBMITTED', 'payment_submissions', (int) $this->db->lastInsertId(), $reference);
        });
    }

    public function pendingPayments(int $page = 1): array
    {
        $this->allow(['ACCOUNTANT', 'SUPER_ADMIN']);
        return $this->rows("SELECT s.*,c.full_name FROM payment_submissions s JOIN customers c ON c.customer_id=s.customer_id
            WHERE s.status='PENDING' AND " . $this->scope() . ' ORDER BY s.submission_id LIMIT 21 OFFSET ?', [(max(1, $page) - 1) * 20]);
    }

    public function reviewPayment(int $id, string $decision, string $notes): void
    {
        $this->allow(['ACCOUNTANT', 'SUPER_ADMIN']);
        if (!in_array($decision, ['CONFIRMED', 'REJECTED'], true) || mb_strlen($notes) > 1000 || ($decision === 'REJECTED' && trim($notes) === '')) {
            throw new DomainException('Choose confirm or reject and provide a rejection reason of at most 1000 characters.');
        }
        $preview = $this->rows('SELECT s.* FROM payment_submissions s JOIN customers c ON c.customer_id=s.customer_id WHERE s.submission_id=? AND ' . $this->scope(), [$id])[0] ?? null;
        if (!$preview) {
            throw new OutOfBoundsException('Payment submission not found.');
        }
        $this->atomic(function () use ($id, $preview, $decision, $notes): void {
            $loan = $this->loan((int) $preview['loan_id'], true);
            $submission = $this->rows('SELECT * FROM payment_submissions WHERE submission_id=? FOR UPDATE', [$id])[0];
            if ($submission['status'] !== 'PENDING') {
                throw new DomainException('This payment submission was already reviewed.');
            }
            $paymentId = $decision === 'CONFIRMED' ? $this->postLocked($loan, [
                'amount' => $submission['amount_rwf'], 'date' => $submission['payment_date'],
                'method' => $submission['payment_method'], 'reference' => $submission['reference_code'],
            ], $id) : null;
            $this->run('UPDATE payment_submissions SET status=?,reviewed_by=?,reviewed_at=NOW(),review_notes=?,payment_id=? WHERE submission_id=?',
                [$decision, $this->actor['user_id'], trim($notes) ?: null, $paymentId, $id]);
            $this->audit('PAYMENT_' . $decision, 'payment_submissions', $id, $notes);
        });
    }

    public function postPayment(int $id, array $input): int
    {
        $this->allow(['ACCOUNTANT', 'SUPER_ADMIN']);
        return $this->atomic(fn () => $this->postLocked($this->loan($id, true), $input));
    }

    private function postLocked(array $loan, array $input, ?int $submissionId = null): int
    {
        $amount = Money::cents($input['amount']);
        $date = self::date($input['date']);
        $method = self::method($input['method']);
        $reference = self::reference($input['reference']);
        $balance = Money::cents($loan['outstanding_balance_rwf'], false);
        if (!in_array($loan['status'], self::LIVE, true) || !$loan['disbursement_date'] || $amount > $balance) {
            throw new DomainException('The loan must be disbursed and the payment cannot exceed its outstanding balance.');
        }
        $latest = $this->rows('SELECT MAX(payment_date) AS latest FROM payments WHERE loan_id=?', [$loan['loan_id']])[0]['latest'];
        if ($date < $loan['disbursement_date'] || ($latest && $date < $latest)) {
            throw new DomainException('The payment date cannot predate disbursement or a previously posted payment.');
        }
        $schedule = $this->rows('SELECT * FROM repayment_schedules WHERE loan_id=? ORDER BY installment_number FOR UPDATE', [$loan['loan_id']]);
        $scheduledBalance = 0;
        foreach ($schedule as $row) {
            $scheduledBalance += Money::cents($row['principal_due_rwf'], false) + Money::cents($row['interest_due_rwf'], false)
                + Money::cents($row['penalty_due_rwf'], false) - Money::cents($row['amount_paid_rwf'], false);
        }
        if ($scheduledBalance !== $balance) {
            throw new DomainException('The repayment schedule does not match the loan balance. Ask your administrator to reconcile this legacy loan before posting.');
        }
        $this->claimReference($reference, (int) $loan['loan_id'], 'PAYMENT', $submissionId);
        $receipt = 'ML-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(6)));
        $this->run('INSERT INTO payments (receipt_number,loan_id,amount_paid_rwf,payment_date,payment_method,reference_code,balance_after_rwf,received_by_user_id) VALUES (?,?,?,?,?,?,?,?)',
            [$receipt, $loan['loan_id'], Money::decimal($amount), $date, $method, $reference, Money::decimal($balance - $amount), $this->actor['user_id']]);
        $paymentId = (int) $this->db->lastInsertId();
        $remaining = $amount;
        foreach ($schedule as $row) {
            $paid = Money::cents($row['amount_paid_rwf'], false);
            $total = Money::cents($row['penalty_due_rwf'], false) + Money::cents($row['interest_due_rwf'], false) + Money::cents($row['principal_due_rwf'], false);
            $toApply = min($remaining, max(0, $total - $paid));
            if ($toApply === 0) {
                continue;
            }
            // Oldest installment first; within each installment: penalty, interest, principal.
            $left = $toApply;
            $previous = $paid;
            foreach (['PENALTY' => 'penalty_due_rwf', 'INTEREST' => 'interest_due_rwf', 'PRINCIPAL' => 'principal_due_rwf'] as $component => $field) {
                $due = Money::cents($row[$field], false);
                $already = min($previous, $due);
                $previous -= $already;
                $portion = min($left, $due - $already);
                if ($portion > 0) {
                    $this->run('INSERT INTO payment_allocations (payment_id,schedule_id,allocated_to,amount_rwf) VALUES (?,?,?,?)',
                        [$paymentId, $row['schedule_id'], $component, Money::decimal($portion)]);
                    $left -= $portion;
                }
            }
            $newPaid = $paid + $toApply;
            $status = $newPaid === $total ? 'PAID' : ($row['due_date'] < date('Y-m-d') ? 'OVERDUE' : 'PARTIALLY_PAID');
            $this->run('UPDATE repayment_schedules SET amount_paid_rwf=?,status=? WHERE schedule_id=?', [Money::decimal($newPaid), $status, $row['schedule_id']]);
            $remaining -= $toApply;
        }
        if ($remaining !== 0) {
            throw new RuntimeException('Payment could not be fully allocated.');
        }
        $status = $balance === $amount ? 'COMPLETED' : ($this->rows('SELECT loan_id FROM loans l WHERE loan_id=? AND ' . self::overdueSql(), [$loan['loan_id']]) ? 'OVERDUE' : 'ACTIVE');
        $this->run('UPDATE loans SET outstanding_balance_rwf=?,status=? WHERE loan_id=?', [Money::decimal($balance - $amount), $status, $loan['loan_id']]);
        if ($loan['status'] !== $status) {
            $this->history((int) $loan['loan_id'], $loan['status'], $status, 'Repayment posted: ' . $receipt);
        }
        $this->audit('REPAYMENT_POSTED', 'payments', $paymentId, $receipt);
        return $paymentId;
    }

    public function recoveryLogs(int $id): array
    {
        $this->allow(['SUPER_ADMIN', 'MANAGER', 'RECOVERY_OFFICER']);
        $this->loan($id);
        return $this->rows('SELECT r.*,u.full_name FROM recovery_logs r JOIN users u ON u.user_id=r.officer_user_id WHERE loan_id=? ORDER BY log_id DESC', [$id]);
    }

    public function logRecovery(int $id, array $input): void
    {
        $this->allow(['RECOVERY_OFFICER', 'SUPER_ADMIN']);
        if (!in_array($input['contact_type'], ['PHONE_CALL', 'PHYSICAL_VISIT', 'SMS', 'WARNING_LETTER'], true)) {
            throw new DomainException('Choose a valid contact method.');
        }
        $notes = self::text($input['notes'], 3000, 'Contact notes');
        $promise = $input['promised_date'] !== '' ? self::date($input['promised_date'], true) : null;
        $followup = $input['followup_date'] !== '' ? self::date($input['followup_date'], true) : null;
        $this->atomic(function () use ($id, $input, $notes, $promise, $followup): void {
            $loan = $this->loan($id, true);
            if (!in_array($loan['status'], self::LIVE, true) || !$this->rows('SELECT loan_id FROM loans l WHERE loan_id=? AND ' . self::overdueSql(), [$id])) {
                throw new DomainException('Contact logging is available for loans with overdue installments.');
            }
            $this->run('INSERT INTO recovery_logs (loan_id,officer_user_id,contact_type,summary_notes,promised_payment_date,next_followup_date) VALUES (?,?,?,?,?,?)',
                [$id, $this->actor['user_id'], $input['contact_type'], $notes, $promise, $followup]);
            $this->audit('RECOVERY_CONTACT', 'recovery_logs', (int) $this->db->lastInsertId());
        });
    }

    public function receipt(int $id): array
    {
        $this->allow(['CUSTOMER', 'ACCOUNTANT', 'MANAGER', 'SUPER_ADMIN', 'FIRM']);
        $row = $this->rows('SELECT p.*,c.full_name,lp.product_name,f.firm_name,u.full_name AS received_by FROM payments p
            JOIN loans l ON l.loan_id=p.loan_id JOIN customers c ON c.customer_id=l.customer_id
            JOIN loan_applications a ON a.application_id=l.application_id JOIN loan_products lp ON lp.product_id=a.product_id
            LEFT JOIN firms f ON f.firm_id=c.firm_id JOIN users u ON u.user_id=p.received_by_user_id
            WHERE p.payment_id=? AND ' . $this->scope(), [$id])[0] ?? null;
        if (!$row) {
            throw new OutOfBoundsException('Receipt not found.');
        }
        return $row;
    }

    public function report(string $from, string $to): array
    {
        $this->allow(['SUPER_ADMIN', 'MANAGER', 'ACCOUNTANT']);
        self::date($from, true);
        self::date($to, true);
        if ($from > $to) {
            throw new DomainException('The start date must be before the end date.');
        }
        $collections = $this->rows('SELECT p.payment_date,p.payment_method,COUNT(*) AS transactions,SUM(p.amount_paid_rwf) AS amount
            FROM payments p JOIN loans l ON l.loan_id=p.loan_id JOIN customers c ON c.customer_id=l.customer_id
            WHERE ' . $this->scope() . ' AND p.payment_date BETWEEN ? AND ? GROUP BY p.payment_date,p.payment_method ORDER BY p.payment_date DESC', [$from, $to]);
        $disbursed = $this->rows('SELECT COALESCE(SUM(d.amount_rwf),0) AS total FROM disbursements d JOIN loans l ON l.loan_id=d.loan_id
            JOIN customers c ON c.customer_id=l.customer_id WHERE ' . $this->scope() . ' AND d.disbursement_date BETWEEN ? AND ?', [$from, $to])[0]['total'];
        return ['collections' => $collections, 'disbursed' => $disbursed];
    }
}
