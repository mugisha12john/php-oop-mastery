<?php
/**
 * Shared submission handler for customer forms.
 */

require_once __DIR__ . '/includes/bootstrap.php';
$user = require_customer_access();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $search = $_GET['search'] ?? '';
    if (!is_string($search)) {
        http_response_code(400);
        exit('Invalid search term');
    }

    $query = $search !== '' ? '?' . http_build_query(['search' => $search]) : '';
    header('Location: view/customers/listCustomers.php' . $query, true, 303);
    exit;
}

if ($method !== 'POST') {
    header('Allow: GET, POST');
    http_response_code(405);
    exit('Method not allowed');
}
require_post();

$action = $_POST['action'] ?? '';
if (!in_array($action, ['create', 'update'], true)) {
    http_response_code(400);
    exit('Invalid customer action');
}

$customer_id = null;
if ($action === 'update') {
    $customer_id = filter_var($_POST['customer_id'] ?? '', FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1]
    ]);
    if ($customer_id === false) {
        http_response_code(400);
        exit('Invalid customer ID');
    }
}

$data = [];
foreach (['national_id_number', 'full_name', 'phone_number', 'email',
          'physical_address', 'monthly_income_rwf', 'kyc_status'] as $field) {
    $value = $_POST[$field] ?? '';
    if (!is_string($value)) {
        http_response_code(400);
        exit('Invalid customer field');
    }
    $data[$field] = $value;
}

require_once __DIR__ . '/controller/CustomerController.php';
$controller = new CustomerController();

if ($action === 'create') {
    $data['kyc_status'] = 'PENDING';
    $data['registered_by'] = (int) $user['user_id'];
    $result = $controller->createCustomer($data);
    $form_key = 'register';
    $return_url = 'view/customers/registerCustomer.php';
    $message = 'created';
} else {
    $existing = $controller->getCustomer($customer_id);
    if (!$existing['success']) {
        http_response_code(404);
        exit('Customer not found');
    }

    // National ID is read-only in the edit form.
    $data['national_id_number'] = $existing['customer']['national_id_number'];
    $data['kyc_status'] = $_POST['kyc_status'] ?? $existing['customer']['kyc_status'];
    $result = $controller->updateCustomer($customer_id, $data);
    $form_key = 'edit_' . $customer_id;
    $return_url = 'view/customers/editCustomer.php?id=' . $customer_id;
    $message = 'updated';
}

if ($result['success']) {
    unset($_SESSION['customer_forms'][$form_key]);
    $return_url = 'view/customers/listCustomers.php?msg=' . $message;
} else {
    $_SESSION['customer_forms'][$form_key] = [
        'errors' => $result['errors'] ?? [$result['message']],
        'input' => $data
    ];
}

header('Location: ' . $return_url, true, 303);
exit;
