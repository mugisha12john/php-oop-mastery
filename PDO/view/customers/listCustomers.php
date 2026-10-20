<?php
/**
 * List Customers View
 * Displays all customers with pagination, search, and action buttons
 */

require_once __DIR__ . '/../../includes/bootstrap.php';
$user = require_customer_access();
require_once __DIR__ . '/../../controller/CustomerController.php';

$controller = new CustomerController();

// Handle page request
$page = max(1, (int) ($_GET['page'] ?? 1));
$result = $controller->getAllCustomers($page, 10);

// Handle search
$search_term = '';
$search_results = null;
if (isset($_GET['search']) && is_string($_GET['search']) && trim($_GET['search']) !== '') {
    $search_term = $_GET['search'];
    $search_results = $controller->searchCustomers($search_term);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Management - MLMS</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
   
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            padding: 30px;
        }

        h1 {
            color: #333;
            margin-bottom: 30px;
            border-bottom: 3px solid #667eea;
            padding-bottom: 10px;
        }

        .header-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .search-form {
            display: flex;
            gap: 10px;
            flex: 1;
            min-width: 300px;
        }

        .search-form input {
            flex: 1;
            padding: 10px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 5px;
            font-size: 14px;
            transition: border-color 0.3s;
        }

        .search-form input:focus {
            outline: none;
            border-color: #667eea;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }

        .btn-primary {
            background: #667eea;
            color: white;
        }

        .btn-primary:hover {
            background: #5568d3;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .btn-secondary {
            background: #f0f0f0;
            color: #333;
            border: 2px solid #e0e0e0;
        }

        .btn-secondary:hover {
            background: #e0e0e0;
        }

        .btn-success {
            background: #10b981;
            color: white;
        }

        .btn-success:hover {
            background: #059669;
        }

        .btn-danger {
            background: #ef4444;
            color: white;
        }

        .btn-danger:hover {
            background: #dc2626;
        }

        .btn-info {
            background: #3b82f6;
            color: white;
            padding: 5px 10px;
            font-size: 12px;
        }

        .btn-info:hover {
            background: #2563eb;
        }

        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
            border-left: 4px solid;
        }

        .alert-success {
            background: #d1fae5;
            border-color: #10b981;
            color: #065f46;
        }

        .alert-danger {
            background: #fee2e2;
            border-color: #ef4444;
            color: #991b1b;
        }

        .alert-info {
            background: #dbeafe;
            border-color: #3b82f6;
            color: #1e40af;
        }

        .table-responsive {
            overflow-x: auto;
            margin-bottom: 30px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
        }

        thead {
            background: #f9fafb;
            border-bottom: 2px solid #e5e7eb;
        }

        th {
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: #374151;
            text-transform: uppercase;
            font-size: 12px;
            letter-spacing: 0.5px;
        }

        td {
            padding: 15px;
            border-bottom: 1px solid #f3f4f6;
            color: #4b5563;
        }

        tbody tr:hover {
            background: #f9fafb;
        }

        .kyc-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .kyc-badge.pending {
            background: #fef3c7;
            color: #92400e;
        }

        .kyc-badge.verified {
            background: #d1fae5;
            color: #065f46;
        }

        .kyc-badge.rejected {
            background: #fee2e2;
            color: #991b1b;
        }

        .actions {
            display: flex;
            gap: 5px;
        }

        .pagination {
            display: flex;
            justify-content: center;
            gap: 5px;
            margin-top: 30px;
            flex-wrap: wrap;
        }

        .pagination a,
        .pagination span {
            padding: 8px 12px;
            border: 1px solid #e0e0e0;
            border-radius: 5px;
            text-decoration: none;
            color: #667eea;
            transition: all 0.3s;
        }

        .pagination a:hover {
            background: #667eea;
            color: white;
        }

        .pagination .current {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }

        .no-results {
            text-align: center;
            padding: 40px;
            color: #999;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: white;
            border-radius: 10px;
            padding: 30px;
            max-width: 500px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
        }

        .modal-header {
            margin-bottom: 20px;
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 15px;
        }

        .modal-header h2 {
            color: #333;
        }

        .close-btn {
            position: absolute;
            top: 20px;
            right: 20px;
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #999;
        }
    </style>
</head>
<body>
    <?php require __DIR__ . '/../../includes/customerNav.php'; ?>
    <div class="container">
        <h1>Customer Management</h1>

        <div class="header-actions">
            <form method="GET" action="../../customer.php" class="search-form">
                <input type="text" name="search" placeholder="Search by name, phone, or ID..." 
                       value="<?php echo htmlspecialchars($search_term); ?>">
                <button type="submit" class="btn btn-secondary">Search</button>
                <a href="listCustomers.php" class="btn btn-secondary">Clear</a>
            </form>
            <a href="registerCustomer.php" class="btn btn-primary">+ New Customer</a>
        </div>

        <?php if (isset($_GET['msg']) && $_GET['msg'] === 'created'): ?>
            <div class="alert alert-success">
                ✓ Customer created successfully!
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['msg']) && $_GET['msg'] === 'updated'): ?>
            <div class="alert alert-success">
                ✓ Customer updated successfully!
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['msg']) && $_GET['msg'] === 'deleted'): ?>
            <div class="alert alert-success">
                ✓ Customer deleted successfully!
            </div>
        <?php endif; ?>

        <div class="table-responsive">
            <?php if ($search_results && $search_results['success']): ?>
                <?php if (count($search_results['customers']) > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Full Name</th>
                                <th>National ID</th>
                                <th>Phone</th>
                                <th>Email</th>
                                <th>Monthly Income</th>
                                <th>KYC Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($search_results['customers'] as $customer): ?>
                                <tr>
                                    <td>#<?php echo htmlspecialchars($customer['customer_id']); ?></td>
                                    <td><?php echo htmlspecialchars($customer['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($customer['national_id_number']); ?></td>
                                    <td><?php echo htmlspecialchars($customer['phone_number']); ?></td>
                                    <td><?php echo htmlspecialchars($customer['email'] ?? '—'); ?></td>
                                    <td><?php echo number_format($customer['monthly_income_rwf'], 2); ?> RWF</td>
                                    <td>
                                        <span class="kyc-badge <?php echo strtolower($customer['kyc_status']); ?>">
                                            <?php echo htmlspecialchars($customer['kyc_status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="actions">
                                            <a href="viewCustomer.php?id=<?php echo $customer['customer_id']; ?>" 
                                               class="btn btn-info">View</a>
                                            <a href="editCustomer.php?id=<?php echo $customer['customer_id']; ?>" 
                                               class="btn btn-info">Edit</a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="alert alert-info">
                        No customers found matching your search.
                    </div>
                <?php endif; ?>
            <?php elseif ($result['success']): ?>
                <?php if (count($result['customers']) > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Full Name</th>
                                <th>National ID</th>
                                <th>Phone</th>
                                <th>Email</th>
                                <th>Monthly Income</th>
                                <th>KYC Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($result['customers'] as $customer): ?>
                                <tr>
                                    <td>#<?php echo htmlspecialchars($customer['customer_id']); ?></td>
                                    <td><?php echo htmlspecialchars($customer['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($customer['national_id_number']); ?></td>
                                    <td><?php echo htmlspecialchars($customer['phone_number']); ?></td>
                                    <td><?php echo htmlspecialchars($customer['email'] ?? '—'); ?></td>
                                    <td><?php echo number_format($customer['monthly_income_rwf'], 2); ?> RWF</td>
                                    <td>
                                        <span class="kyc-badge <?php echo strtolower($customer['kyc_status']); ?>">
                                            <?php echo htmlspecialchars($customer['kyc_status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="actions">
                                            <a href="viewCustomer.php?id=<?php echo $customer['customer_id']; ?>" 
                                               class="btn btn-info">View</a>
                                            <a href="editCustomer.php?id=<?php echo $customer['customer_id']; ?>" 
                                               class="btn btn-info">Edit</a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <?php if ($result['pages'] > 1): ?>
                        <div class="pagination">
                            <?php for ($i = 1; $i <= $result['pages']; $i++): ?>
                                <?php if ($i === $page): ?>
                                    <span class="current"><?php echo $i; ?></span>
                                <?php else: ?>
                                    <a href="listCustomers.php?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                <?php endif; ?>
                            <?php endfor; ?>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="no-results">
                        <p>No customers found. <a href="registerCustomer.php" class="btn btn-primary">Create one</a></p>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="alert alert-danger">
                    <?php echo htmlspecialchars($result['message'] ?? 'Error loading customers'); ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
