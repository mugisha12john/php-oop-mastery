<?php
require_once dirname(__DIR__, 2) . '/autoload.php';


class CustomerController {
    private $model;
    public function __construct() {
        $this->model = new CustomerModel();
    }
    public function getAllCustomers($page = 1, $limit = 10) {
        $page = max(1, (int) $page);
        $limit = max(1, min(100, (int) $limit));
        $offset = ($page - 1) * $limit;
        
        try {
            $customers = $this->model->getAll($limit, $offset);
            $total = $this->model->getTotalCount();

            return [
                'success' => true,
                'customers' => $customers,
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'pages' => ceil($total / $limit)
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error fetching customers: ' . $e->getMessage()
            ];
        }
    }


    public function getCustomer($customer_id) {
        try {
            $customer = $this->model->getById($customer_id);
            
            if (!$customer) {
                return [
                    'success' => false,
                    'message' => 'Customer not found'
                ];
            }

            return [
                'success' => true,
                'customer' => $customer
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error fetching customer: ' . $e->getMessage()
            ];
        }
    }

    public function createCustomer($data) {
        // Validate required fields
        $validation = $this->validateCustomerData($data);
        if (!$validation['valid']) {
            return [
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validation['errors']
            ];
        }

        try {
            // Check if national ID already exists
            if ($this->model->nationalIdExists($data['national_id_number'])) {
                return [
                    'success' => false,
                    'message' => 'National ID already registered'
                ];
            }

            // Create customer object
            $customer = new CustomerModel(
                $data['national_id_number'],
                $data['full_name'],
                $data['phone_number'],
                $data['email'] ?? null,
                $data['physical_address'],
                $data['monthly_income_rwf'],
                $data['kyc_status'] ?? 'PENDING',
                $data['user_id'] ?? null
            );

            // Save to database
            $result = $customer->save($data['registered_by'] ?? null);
            
            return $result;
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error creating customer: ' . $e->getMessage()
            ];
        }
    }


    public function updateCustomer($customer_id, $data) {
        $validation = $this->validateCustomerData($data, $customer_id);
        if (!$validation['valid']) {
            return [
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validation['errors']
            ];
        }

        try {
          
            $existing = $this->model->getById($customer_id);
            if (!$existing) {
                return [
                    'success' => false,
                    'message' => 'Customer not found'
                ];
            }

         
            $customer = new CustomerModel(
                $data['national_id_number'] ?? $existing['national_id_number'],
                $data['full_name'] ?? $existing['full_name'],
                $data['phone_number'] ?? $existing['phone_number'],
                $data['email'] ?? $existing['email'],
                $data['physical_address'] ?? $existing['physical_address'],
                $data['monthly_income_rwf'] ?? $existing['monthly_income_rwf'],
                $data['kyc_status'] ?? $existing['kyc_status']
            );

            
            $result = $customer->update($customer_id);
            
            return $result;
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error updating customer: ' . $e->getMessage()
            ];
        }
    }

  
    public function deleteCustomer($customer_id) {
        try {
            $customer = $this->model->getById($customer_id);
            if (!$customer) {
                return [
                    'success' => false,
                    'message' => 'Customer not found'
                ];
            }

            $result = $this->model->delete($customer_id);
            return $result;
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error deleting customer: ' . $e->getMessage()
            ];
        }
    }

 
    public function searchCustomers($search_term) {
        if (empty(trim($search_term))) {
            return [
                'success' => false,
                'message' => 'Search term cannot be empty'
            ];
        }

        try {
            $customers = $this->model->search($search_term);
            return [
                'success' => true,
                'customers' => $customers,
                'count' => count($customers)
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error searching customers: ' . $e->getMessage()
            ];
        }
    }


    public function getCustomersByKycStatus($kyc_status) {
        $valid_statuses = ['PENDING', 'VERIFIED', 'REJECTED'];
        
        if (!in_array($kyc_status, $valid_statuses)) {
            return [
                'success' => false,
                'message' => 'Invalid KYC status'
            ];
        }

        try {
            $customers = $this->model->getByKycStatus($kyc_status);
            $total = $this->model->getCountByKycStatus($kyc_status);

            return [
                'success' => true,
                'customers' => $customers,
                'status' => $kyc_status,
                'total' => $total
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error fetching customers: ' . $e->getMessage()
            ];
        }
    }


    public function updateKycStatus($customer_id, $kyc_status) {
        $valid_statuses = ['PENDING', 'VERIFIED', 'REJECTED'];
        
        if (!in_array($kyc_status, $valid_statuses)) {
            return [
                'success' => false,
                'message' => 'Invalid KYC status'
            ];
        }

        try {
            $customer = $this->model->getById($customer_id);
            if (!$customer) {
                return [
                    'success' => false,
                    'message' => 'Customer not found'
                ];
            }

            $customer_obj = new CustomerModel(
                $customer['national_id_number'],
                $customer['full_name'],
                $customer['phone_number'],
                $customer['email'],
                $customer['physical_address'],
                $customer['monthly_income_rwf'],
                $kyc_status
            );

            $result = $customer_obj->update($customer_id);
            return $result;
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error updating KYC status: ' . $e->getMessage()
            ];
        }
    }


    private function validateCustomerData($data, $exclude_id = null) {
        $errors = [];

        // Required fields
        if (empty($data['national_id_number'])) {
            $errors[] = 'National ID is required';
        } elseif (!preg_match('/^\d{16}$/', $data['national_id_number'])) {
            $errors[] = 'National ID must be exactly 16 digits';
        } elseif ($this->model->nationalIdExists($data['national_id_number'], $exclude_id)) {
            $errors[] = 'National ID already exists';
        }

        if (empty($data['full_name'])) {
            $errors[] = 'Full name is required';
        } elseif (strlen($data['full_name']) > 120) {
            $errors[] = 'Full name cannot exceed 120 characters';
        }

        if (empty($data['phone_number'])) {
            $errors[] = 'Phone number is required';
        } elseif (strlen($data['phone_number']) > 20) {
            $errors[] = 'Phone number cannot exceed 20 characters';
        }

        if (!empty($data['email'])) {
            if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Invalid email format';
            }
        }

        if (empty($data['physical_address'])) {
            $errors[] = 'Physical address is required';
        } elseif (strlen($data['physical_address']) > 255) {
            $errors[] = 'Physical address cannot exceed 255 characters';
        }

        if (!isset($data['monthly_income_rwf']) || $data['monthly_income_rwf'] === '') {
            $errors[] = 'Monthly income is required';
        } elseif (!is_numeric($data['monthly_income_rwf']) || $data['monthly_income_rwf'] < 0
            || $data['monthly_income_rwf'] > 999999999999.99) {
            $errors[] = 'Monthly income must be between 0 and 999999999999.99';
        }

        if (!empty($data['kyc_status'])) {
            $valid_statuses = ['PENDING', 'VERIFIED', 'REJECTED'];
            if (!in_array($data['kyc_status'], $valid_statuses)) {
                $errors[] = 'Invalid KYC status';
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
}
