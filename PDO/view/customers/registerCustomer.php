<?php
/**
 * Register Customer View
 * Form to create new customer
 */

require_once __DIR__ . '/../../controller/CustomerController.php';

$controller = new CustomerController();
$errors = [];
$success = false;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'national_id_number' => $_POST['national_id_number'] ?? '',
        'full_name' => $_POST['full_name'] ?? '',
        'phone_number' => $_POST['phone_number'] ?? '',
        'email' => $_POST['email'] ?? '',
        'physical_address' => $_POST['physical_address'] ?? '',
        'monthly_income_rwf' => $_POST['monthly_income_rwf'] ?? 0,
        'kyc_status' => 'PENDING',
        'registered_by' => 1  // Replace with actual logged-in user ID
    ];

    $result = $controller->createCustomer($data);

    if ($result['success']) {
        $success = true;
        // Redirect after 2 seconds
        header("Refresh: 2; url=listCustomers.php?msg=created");
    } else {
        $errors = $result['errors'] ?? [$result['message']];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register New Customer - MLMS</title>
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
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .container {
            max-width: 600px;
            width: 100%;
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            padding: 40px;
        }

        h1 {
            color: #333;
            margin-bottom: 30px;
            text-align: center;
            border-bottom: 3px solid #667eea;
            padding-bottom: 15px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 600;
            font-size: 14px;
        }

        .required {
            color: #ef4444;
        }

        input[type="text"],
        input[type="email"],
        input[type="tel"],
        input[type="number"],
        textarea {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 5px;
            font-size: 14px;
            font-family: inherit;
            transition: border-color 0.3s, box-shadow 0.3s;
        }

        input[type="text"]:focus,
        input[type="email"]:focus,
        input[type="tel"]:focus,
        input[type="number"]:focus,
        textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        textarea {
            resize: vertical;
            min-height: 100px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        @media (max-width: 600px) {
            .form-row {
                grid-template-columns: 1fr;
            }
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

        .alert ul {
            margin: 10px 0 0 20px;
        }

        .alert li {
            margin: 5px 0;
        }

        .button-group {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }

        button {
            flex: 1;
            padding: 12px 24px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .btn-submit {
            background: rgb(35, 63, 186);
            color: white;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
        }

        .btn-cancel {
            background: #f0f0f0;
            color: #333;
            border: 2px solid #e0e0e0;
        }

        .btn-cancel:hover {
            background: #e0e0e0;
        }

        .help-text {
            font-size: 12px;
            color: #999;
            margin-top: 5px;
        }

        .form-section {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f0f0f0;
        }

        .form-section:last-of-type {
            border-bottom: none;
        }

        .section-title {
            color: #667eea;
            font-size: 16px;
            font-weight: 700;
            margin-bottom: 15px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Register New Customer</h1>

        <?php if ($success): ?>
            <div class="alert alert-success">
                ✓ Customer registered successfully! Redirecting to customer list...
            </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <strong>Please fix the following errors:</strong>
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <!-- Identity Information -->
            <div class="form-section">
                <div class="section-title">Identity Information</div>

                <div class="form-group">
                    <label for="national_id_number">
                        National ID Number <span class="required">*</span>
                    </label>
                    <input type="text" id="national_id_number" name="national_id_number" 
                           placeholder="16-digit national ID" 
                           value="<?php echo htmlspecialchars($_POST['national_id_number'] ?? ''); ?>"
                           maxlength="16" required>
                    <div class="help-text">Must be exactly 16 digits</div>
                </div>

                <div class="form-group">
                    <label for="full_name">
                        Full Name <span class="required">*</span>
                    </label>
                    <input type="text" id="full_name" name="full_name" 
                           placeholder="Enter full name" 
                           value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>"
                           maxlength="120" required>
                </div>
            </div>

            <!-- Contact Information -->
            <div class="form-section">
                <div class="section-title">Contact Information</div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="phone_number">
                            Phone Number <span class="required">*</span>
                        </label>
                        <input type="tel" id="phone_number" name="phone_number" 
                               placeholder="+250 XXX XXX XXX" 
                               value="<?php echo htmlspecialchars($_POST['phone_number'] ?? ''); ?>"
                               maxlength="20" required>
                    </div>

                    <div class="form-group">
                        <label for="email">
                            Email Address
                        </label>
                        <input type="email" id="email" name="email" 
                               placeholder="email@example.com" 
                               value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                               maxlength="190">
                    </div>
                </div>

                <div class="form-group">
                    <label for="physical_address">
                        Physical Address <span class="required">*</span>
                    </label>
                    <textarea id="physical_address" name="physical_address" 
                              placeholder="Enter complete physical address" required><?php echo htmlspecialchars($_POST['physical_address'] ?? ''); ?></textarea>
                </div>
            </div>

            <!-- Financial Information -->
            <div class="form-section">
                <div class="section-title">Financial Information</div>

                <div class="form-group">
                    <label for="monthly_income_rwf">
                        Monthly Income (RWF) <span class="required">*</span>
                    </label>
                    <input type="number" id="monthly_income_rwf" name="monthly_income_rwf" 
                           placeholder="0.00" 
                           value="<?php echo htmlspecialchars($_POST['monthly_income_rwf'] ?? ''); ?>"
                           step="0.01" min="0" required>
                    <div class="help-text">Used to calculate loan eligibility</div>
                </div>
            </div>

            <!-- Buttons -->
            <div class="button-group">
                <button type="submit" class="btn-submit">Register Customer</button>
                <a href="listCustomers.php" style="text-decoration: none; display: flex; align-items: center;">
                    <button type="button" class="btn-cancel">Cancel</button>
                </a>
            </div>
        </form>
    </div>
</body>
</html>
