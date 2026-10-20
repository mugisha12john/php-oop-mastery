# Quick start

From the project root, run the additive database migration:

```bash
php PDO/database/migrate.php
```

Use an existing super admin, or create a new one:

```bash
php PDO/database/create_super_admin.php --name='Platform Admin' --email='admin@example.com'
```

Enter a password when prompted. Existing accounts are never overwritten.

Open `http://localhost/class_learning/PDO/index.php` for the welcome page.

- **Firm owner:** choose **Register your firm**, submit business and account details, and sign in. Your dashboard shows **PENDING** until reviewed.
- **Super admin:** sign in, review pending registrations, and choose **Approve firm** or **Reject**. Rejection requires a reason.
- **Approved firm:** refresh the dashboard, choose **Register customer** or **View your customers**. Only your firm's customers are accessible.
- **Rejected firm:** sign in to see the rejection reason. Contact the platform administrator about the registration.
- **Customer account setup:** as an approved firm or super admin, open a customer’s detail page and choose **Customer login access**. Set the customer’s login email and password, then give them their credentials directly.
- **Customer:** use **Customer sign in** (`customerLogin.php`). View your account, firm contacts, and loan requests. A verified customer linked to an approved firm can request a loan using an active product. Open a request to see decisions and any issued loan balance.

Customer record forms submit to `PDO/customer.php`. Account sign-in forms submit to `PDO/auth.php`. Firm reviews submit to `PDO/firm.php`. Loan requests submit to `PDO/loan.php`; customer login creation submits to `PDO/customerAccount.php`.

Existing lending products are preserved. On a fresh installation, configure active products in `loan_products` before customers can submit requests. The customer portal displays existing decisions and loan records; staff loan approval and repayment screens are not included.

Existing customers without a firm remain visible to super admins. See `README.md` for configuration and `TESTING.md` for verification.
