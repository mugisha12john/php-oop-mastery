<?php
/**
 * CustomerModel - Handles all customer-related database operations
 * Maps to the 'customers' table in MLMS schema
 */

require_once __DIR__ . '/baseModel.php';
require_once __DIR__ . '/../includes/bootstrap.php';

class CustomerModel extends BaseModel {
    protected $table = 'customers';

    private $customer_id;
    private $user_id;
    private $national_id_number;
    private $full_name;
    private $phone_number;
    private $email;
    private $physical_address;
    private $monthly_income_rwf;
    private $kyc_status;
    private $firm_id;
    private $actor_id;

    private function scopeClause(): string {
        // Scope comes from the authenticated account, never from form fields.
        return $this->firm_id === null ? '' : ' AND firm_id = ' . (int) $this->firm_id;
    }

    public function __construct(
        $national_id_number = "",
        $full_name = "",
        $phone_number = "",
        $email = "",
        $physical_address = "",
        $monthly_income_rwf = 0,
        $kyc_status = "PENDING",
        $user_id = null,
        $customer_id = null
    ) {
        parent::__construct();
        $actor = require_customer_access();
        $this->firm_id = customer_scope();
        $this->actor_id = (int) $actor['user_id'];
        $this->customer_id = $customer_id;
        $this->user_id = $user_id;
        $this->national_id_number = $national_id_number;
        $this->full_name = $full_name;
        $this->phone_number = $phone_number;
        $this->email = $email;
        $this->physical_address = $physical_address;
        $this->monthly_income_rwf = $monthly_income_rwf;
        $this->kyc_status = $kyc_status;
    }


    public function save($registered_by = null) {
        $sql = "INSERT INTO customers 
                (user_id, national_id_number, full_name, phone_number, email, 
                 physical_address, monthly_income_rwf, kyc_status, registered_by, firm_id, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";

        $params = [
            $this->user_id,
            $this->national_id_number,
            $this->full_name,
            $this->phone_number,
            $this->email,
            $this->physical_address,
            $this->monthly_income_rwf,
            $this->kyc_status,
            $this->actor_id,
            $this->firm_id
        ];

        try {
            $stmt = $this->execute($sql, $params);
            $this->customer_id = $this->lastInsertId();
            return [
                'success' => true,
                'message' => 'Customer created successfully',
                'customer_id' => $this->customer_id
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error creating customer: ' . $e->getMessage()
            ];
        }
    }


    public function update($customer_id) {
        $sql = "UPDATE customers SET 
                full_name = ?, phone_number = ?, email = ?, 
                physical_address = ?, monthly_income_rwf = ?, 
                kyc_status = ?, updated_at = NOW() 
                WHERE customer_id = ?" . $this->scopeClause();

        $params = [
            $this->full_name,
            $this->phone_number,
            $this->email,
            $this->physical_address,
            $this->monthly_income_rwf,
            $this->kyc_status,
            $customer_id
        ];

        try {
            $this->execute($sql, $params);
            return [
                'success' => true,
                'message' => 'Customer updated successfully'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error updating customer: ' . $e->getMessage()
            ];
        }
    }


    public function getAll($limit = null, $offset = 0) {
        $sql = "SELECT * FROM customers WHERE 1=1" . $this->scopeClause() . " ORDER BY created_at DESC";

        if ($limit) {
            $sql .= " LIMIT ? OFFSET ?";
            return $this->fetchAll($sql, [$limit, $offset]);
        }

        return $this->fetchAll($sql);
    }


    public function getById($customer_id) {
        $sql = "SELECT * FROM customers WHERE customer_id = ?" . $this->scopeClause();
        return $this->fetchOne($sql, [$customer_id]);
    }


    public function getByNationalId($national_id_number) {
        $sql = "SELECT * FROM customers WHERE national_id_number = ?" . $this->scopeClause();
        return $this->fetchOne($sql, [$national_id_number]);
    }

 
    public function getByEmail($email) {
        $sql = "SELECT * FROM customers WHERE email = ?" . $this->scopeClause();
        return $this->fetchOne($sql, [$email]);
    }


    public function getByPhone($phone_number) {
        $sql = "SELECT * FROM customers WHERE phone_number = ?" . $this->scopeClause();
        return $this->fetchOne($sql, [$phone_number]);
    }


    public function search($search_term) {
        $sql = "SELECT * FROM customers 
                WHERE (full_name LIKE ? OR phone_number LIKE ? OR national_id_number LIKE ?)"
                . $this->scopeClause() . " ORDER BY full_name ASC";

        $search = "%$search_term%";
        return $this->fetchAll($sql, [$search, $search, $search]);
    }


    public function getByKycStatus($kyc_status) {
        $sql = "SELECT * FROM customers WHERE kyc_status = ?" . $this->scopeClause() . " ORDER BY created_at DESC";
        return $this->fetchAll($sql, [$kyc_status]);
    }

 
    public function delete($customer_id) {
        $sql = "DELETE FROM customers WHERE customer_id = ?" . $this->scopeClause();

        try {
            $statement = $this->execute($sql, [$customer_id]);
            if ($statement->rowCount() === 0) {
                return ['success' => false, 'message' => 'Customer not found'];
            }
            return [
                'success' => true,
                'message' => 'Customer deleted successfully'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error deleting customer: ' . $e->getMessage()
            ];
        }
    }


    public function nationalIdExists($national_id_number, $exclude_id = null) {
        $sql = "SELECT customer_id FROM customers WHERE national_id_number = ?";
        $params = [$national_id_number];

        if ($exclude_id) {
            $sql .= " AND customer_id != ?";
            $params[] = $exclude_id;
        }

        $result = $this->fetchOne($sql, $params);
        return $result !== false;
    }


    public function getTotalCount() {
        $sql = "SELECT COUNT(*) as total FROM customers WHERE 1=1" . $this->scopeClause();
        $result = $this->fetchOne($sql);
        return $result['total'] ?? 0;
    }


    public function getCountByKycStatus($kyc_status) {
        $sql = "SELECT COUNT(*) as total FROM customers WHERE kyc_status = ?" . $this->scopeClause();
        $result = $this->fetchOne($sql, [$kyc_status]);
        return $result['total'] ?? 0;
    }


    public function getCustomerId() { return $this->customer_id; }
    public function getNationalId() { return $this->national_id_number; }
    public function getFullName() { return $this->full_name; }
    public function getPhone() { return $this->phone_number; }
    public function getEmail() { return $this->email; }
    public function getPhysicalAddress() { return $this->physical_address; }
    public function getMonthlyIncome() { return $this->monthly_income_rwf; }
    public function getKycStatus() { return $this->kyc_status; }
    public function getUserId() { return $this->user_id; }

    /**
     * Setters for private properties
     */
    public function setFullName($full_name) { $this->full_name = $full_name; }
    public function setPhone($phone_number) { $this->phone_number = $phone_number; }
    public function setEmail($email) { $this->email = $email; }
    public function setPhysicalAddress($address) { $this->physical_address = $address; }
    public function setMonthlyIncome($income) { $this->monthly_income_rwf = $income; }
    public function setKycStatus($status) { $this->kyc_status = $status; }
    public function setUserId($user_id) { $this->user_id = $user_id; }
}
