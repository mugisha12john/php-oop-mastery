<?php
/**
 * View Customer Details
 * Display full customer information
 */

require_once __DIR__ . '/../../controller/CustomerController.php';

$controller = new CustomerController();

// Get customer ID from URL
$customer_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($customer_id <= 0) {
    die("Invalid customer ID");
}

// Fetch customer data
$result = $controller->getCustomer($customer_id);
if (!$result['success']) {
    die("Customer not found");
}

$customer = $result['customer'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($customer['full_name']); ?> - Customer Profile - MLMS</title>
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
            max-width: 900px;
            margin: 0 auto;
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            overflow: hidden;
        }

        .header {
            background: rgb(35, 63, 186);
            color: white;
            padding: 40px;
            display: flex;
            justify-content: space-between;
            align-items: start;
        }

        .header-info h1 {
            font-size: 32px;
            margin-bottom: 10px;
        }

        .header-info p {
            font-size: 14px;
            opacity: 0.9;
        }

        .header-actions {
            display: flex;
            gap: 10px;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s;
        }

        .btn-primary {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border: 2px solid white;
        }

        .btn-primary:hover {
            background: white;
            color: #667eea;
        }

        .content {
            padding: 40px;
        }

        .section {
            margin-bottom: 40px;
        }

        .section-title {
            font-size: 18px;
            font-weight: 700;
            color: #667eea;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }

        .info-item {
            padding: 15px;
            background: #f9fafb;
            border-radius: 5px;
            border-left: 4px solid #667eea;
        }

        .info-label {
            font-size: 12px;
            color: #999;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
            font-weight: 600;
        }

        .info-value {
            font-size: 16px;
            color: #333;
            font-weight: 500;
        }

        .status-badge {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-pending {
            background: #fef3c7;
            color: #92400e;
        }

        .status-verified {
            background: #d1fae5;
            color: #065f46;
        }

        .status-rejected {
            background: #fee2e2;
            color: #991b1b;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 15px 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .detail-row:last-child {
            border-bottom: none;
        }

        .detail-label {
            color: #666;
            font-weight: 600;
            min-width: 150px;
        }

        .detail-value {
            color: #333;
            flex: 1;
            text-align: right;
        }

        .actions {
            display: flex;
            gap: 15px;
            margin-top: 40px;
            padding-top: 20px;
            border-top: 2px solid #f0f0f0;
        }

        .btn-edit {
           background: rgb(35, 63, 186);
            color: white;
            padding: 12px 24px;
            flex: 1;
            text-align: center;
        }

        .btn-edit:hover {
            background: #5568d3;
        }

        .btn-back {
            background: #f0f0f0;
            color: #333;
            border: 2px solid #e0e0e0;
            padding: 12px 24px;
            flex: 1;
            text-align: center;
        }

        .btn-back:hover {
            background: #e0e0e0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="header-info">
                <h1><?php echo htmlspecialchars($customer['full_name']); ?></h1>
                <p>Customer ID: #<?php echo htmlspecialchars($customer['customer_id']); ?></p>
            </div>
            <div class="header-actions">
                <a href="editCustomer.php?id=<?php echo $customer['customer_id']; ?>" class="btn btn-primary">
                    Edit Customer
                </a>
                <a href="listCustomers.php" class="btn btn-primary">
                    Back to List
                </a>
            </div>
        </div>

        <div class="content">
            <div class="section">
                <div class="section-title">Identity Information</div>
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">National ID Number</div>
                        <div class="info-value"><?php echo htmlspecialchars($customer['national_id_number']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Registration Date</div>
                        <div class="info-value">
                            <?php echo date('M d, Y', strtotime($customer['created_at'])); ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="section">
                <div class="section-title">Contact Information</div>
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">Phone Number</div>
                        <div class="info-value">
                            <a href="tel:<?php echo htmlspecialchars($customer['phone_number']); ?>">
                                <?php echo htmlspecialchars($customer['phone_number']); ?>
                            </a>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Email Address</div>
                        <div class="info-value">
                            <?php if ($customer['email']): ?>
                                <a href="mailto:<?php echo htmlspecialchars($customer['email']); ?>">
                                    <?php echo htmlspecialchars($customer['email']); ?>
                                </a>
                            <?php else: ?>
                                <span style="color: #999;">Not provided</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div style="margin-top: 20px;">
                    <div class="detail-row">
                        <div class="detail-label">Physical Address</div>
                        <div class="detail-value">
                            <?php echo htmlspecialchars($customer['physical_address']); ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="section">
                <div class="section-title">Financial Profile</div>
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">Monthly Income</div>
                        <div class="info-value">
                            <?php echo number_format($customer['monthly_income_rwf'], 2); ?> RWF
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">KYC Status</div>
                        <div class="info-value">
                            <span class="status-badge status-<?php echo strtolower($customer['kyc_status']); ?>">
                                <?php echo htmlspecialchars($customer['kyc_status']); ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="section">
                <div class="section-title">Audit Information</div>
                <div style="background: #f9fafb; padding: 15px; border-radius: 5px;">
                    <div class="detail-row">
                        <div class="detail-label">Created</div>
                        <div class="detail-value">
                            <?php echo date('M d, Y H:i:s', strtotime($customer['created_at'])); ?>
                        </div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Last Updated</div>
                        <div class="detail-value">
                            <?php echo date('M d, Y H:i:s', strtotime($customer['updated_at'])); ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="actions">
                <a href="editCustomer.php?id=<?php echo $customer['customer_id']; ?>" class="btn btn-edit">
                    Edit Customer
                </a>
                <a href="listCustomers.php" class="btn btn-back">
                    Back to List
                </a>
            </div>
        </div>
    </div>
</body>
</html>
