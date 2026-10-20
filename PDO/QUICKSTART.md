# Quick start

```bash
php PDO/database/migrate.php
php PDO/database/create_super_admin.php --name='Platform Admin' --email='admin@example.com'
```

Skip administrator creation if you already have an account. Open `http://localhost/class_learning/PDO/`.

English is the default language. Use the header language selector and **Apply language** to choose French or Kinyarwanda. The browser remembers your choice. The [folder guide](docs/ARCHITECTURE.md) explains the project layout; [translation maintenance](docs/I18N.md) explains how to change the wording.

1. Register a firm and approve it as super admin.
2. From **User accounts**, create/assign the firm's manager, accountant, and recovery officer.
3. As the firm, create a product under **Loan products**. Register a customer with an email to create their login automatically. Without an email, provision login access from the customer's profile later.
4. As the customer, sign in using **`customer123456789`**, set a personal password, then upload the national ID and proof of income under **My documents**.
5. As the firm owner, approve both current documents under **Document reviews**.
6. As the customer, choose a product under **Explore loans** and submit the application.
7. As the manager, review and approve the application.
8. As the accountant, open the loan and record its disbursement. Its schedule is created automatically.
9. As the customer, submit a bank/mobile payment reference under **Repayments**. As the accountant, verify and confirm it under **Payment confirmations**. Cash payments can be posted directly by the accountant.
10. The customer sees the updated balance, schedule, payment history, and printable receipt. Recovery officers can work overdue installments and record borrower contact.

Firm owners manage their products under **Loan products**; customers see only their own firm's active products. Super admins can assign existing unowned products to a firm. Existing customer passwords and loan terms are preserved. Passwords are stored as hashes in the linked user account. Interest is a flat rate applied once to principal. Bank/mobile transfers happen outside the app and need accountant confirmation.

Before real document uploads, set `DOCUMENT_STORAGE_PATH` to persistent storage outside the web root, writable by PHP. The default development directory is under `/tmp`. PHP upload limits must allow 5 MB documents. See `README.md` for configuration and `TESTING.md` for verification.
