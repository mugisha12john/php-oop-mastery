# Testing the MLMS Customer Module

## Prerequisites

1. Database `money_lending_db` created
2. `customers` table exists
3. MySQL user `app_user` with password `buban1234`
4. Web server running (Apache/Nginx)
5. PHP 8.1+ with PDO MySQL and mbstring extensions

---

## Test Scenario 0: Accounts and firm approval

Run `php PDO/database/migrate.php`. Use an existing super admin or create one with `PDO/database/create_super_admin.php`.

1. Open `PDO/registerFirm.php`, submit business and owner details, and sign in.
2. Confirm the firm dashboard says it is awaiting approval. Direct customer page URLs must return 403.
3. Sign in as super admin in another browser/session and approve the firm.
4. Refresh the firm's existing session. Confirm customer links now appear.
5. Register a second firm, reject it with a reason, and verify its status dashboard shows that reason and blocks customer access.
6. Register and approve a third firm. Create customers for both approved firms and verify each sees only its own customers.
7. Change a customer ID in a view/edit URL or submission to another firm's ID. Expect 404 and no changes.
8. Sign out. Customer pages should redirect to login. Missing/invalid CSRF tokens on state changes should return 403.
9. As super admin, verify both firms' customers are visible and approval history includes the reviewer and time.

Automated checks: `python3 PDO/tests/workflow_test.py`. This uses temporary records in the configured development database and cleans them up afterward.

The following customer scenarios require a signed-in, approved firm or a super admin.

## Customer portal checks

1. Open `PDO/index.php` while signed out. Confirm the welcome page links to customer sign in, staff sign in, and firm registration.
2. As an approved firm, view a customer and choose **Customer login access**. Create login credentials. A different firm must receive 404 for that customer’s login setup URL. Existing account emails must fail without linking the customer.
3. Sign in through `PDO/customerLogin.php` with the new account. Confirm the dashboard uses the shared MLMS styling and shows profile details, firm contacts, request counts, and the requests list.
4. Before KYC verification, requesting a loan must show a profile notice. Verify KYC through the staff customer edit form and return to the customer portal.
5. Open **Request a loan**. Choose an active product, enter an amount within its range, a term within its maximum, and a purpose. Submit and confirm the request details page shows `SUBMITTED` and the saved values.
6. Test zero/negative amounts, values outside product limits, unavailable products, excessive terms, and missing purposes. Confirm the form preserves input and displays validation errors. Refresh/back/re-submit the original successful form; it must not create a duplicate.
7. Sign in as another customer. The first customer’s detail URL must return 404 and their requests must not appear in the dashboard. Customer accounts must receive 403 on staff customer management and login provisioning pages.
8. With controlled test records, update the application decision and link an issued loan. Confirm approved/rejected details, loan balance, interest, and dates appear for the correct customer. Internal manager notes must stay private.
9. Check welcome, sign-in, dashboard, request, and detail pages at desktop and mobile sizes. Verify keyboard focus, readable labels, wrapped contact information, and horizontal scrolling for the requests table.
10. Sign out and confirm loan pages require authentication.

These functional and access checks are included in `python3 PDO/tests/workflow_test.py` (with `customer_portal_checks.py`). Visual layout checks require a browser.

## Test Scenario 1: Create Customer

### Steps:
1. Navigate to: `http://localhost/class_learning/PDO/view/customers/registerCustomer.php`
2. Fill form with:
   - **National ID**: 1234567890123456 (16 digits)
   - **Full Name**: John Doe
   - **Phone**: +250788123456
   - **Email**: john@example.com
   - **Address**: Kigali, Rwanda
   - **Income**: 500000

3. Click "Register Customer"

### Expected Result:
- ✅ Redirects immediately to the list with a success message
- ✅ New customer appears in list
- ✅ KYC status shows "PENDING"

---

## Test Scenario 2: Search Customer

### Steps:
1. Go to: `http://localhost/class_learning/PDO/view/customers/listCustomers.php`
2. In search box, type: "John"
3. Click "Search"

### Expected Result:
- ✅ Table shows only customers with "John" in name
- ✅ Search term preserved in input
- ✅ "Clear" button returns to full list

---

## Test Scenario 3: Edit Customer

### Steps:
1. From list page, find a customer
2. Click "Edit" button
3. Change phone to: +250799999999
4. Change KYC status to: "VERIFIED"
5. Click "Update Customer"

### Expected Result:
- ✅ Success message appears
- ✅ Redirects back to list
- ✅ Updated values saved in database
- ✅ Timestamps updated

---

## Test Scenario 4: View Details

### Steps:
1. From list, click "View" button
2. Review all displayed information
3. Click "Edit Customer" link

### Expected Result:
- ✅ All customer info displayed correctly
- ✅ KYC status shows badge
- ✅ Audit timestamps shown
- ✅ Links work properly

---

## Test Scenario 5: Validation Tests

### Try Invalid National ID:
1. Register form
2. Enter: "123456" (only 6 digits)
3. Click Register

**Expected**: Error "National ID must be exactly 16 digits"

### Try Duplicate National ID:
1. Create customer with ID: 1234567890123456
2. Try to create another with same ID

**Expected**: Error "National ID already exists"

### Try Invalid Email:
1. Register form
2. Enter email: "notanemail"
3. Click Register

**Expected**: Error "Invalid email format"

### Try Missing Required Fields:
1. Register form
2. Leave "Full Name" empty
3. Click Register

**Expected**: Error "Full name is required"

---

## Test Scenario 6: Pagination

### Steps:
1. Create 25+ customers
2. Go to list page

### Expected Result:
- ✅ Shows 10 customers per page
- ✅ Pagination links at bottom (1, 2, 3...)
- ✅ Clicking page number loads that page
- ✅ Current page highlighted

---

## Test Scenario 7: KYC Status Filter

### Manual Test:
1. Edit a customer
2. Set KYC status to "VERIFIED"
3. Save

**Expected**: Status badge changes color (green for verified)

---

## SQL Verification

### Check data was inserted:
```sql
SELECT * FROM customers ORDER BY created_at DESC LIMIT 1;
```

Should show your newly created customer.

### Check prepared statements are used:
In `CustomerModel.php`, all queries use `?` placeholders:
```sql
"SELECT * FROM customers WHERE customer_id = ?"  ✓ Good
"SELECT * FROM customers WHERE name = '$name'"   ✗ Bad (none in code)
```

---

## Browser Console Checks

1. Open DevTools (F12)
2. Go to Network tab
3. Perform actions
4. Should see:
   - POST requests for form submissions
   - Create/edit POST requests go to `PDO/customer.php` and receive a 303 redirect
   - Invalid submissions return to the form with errors and entered values preserved
   - GET requests for page loads
   - No JavaScript errors

---

## Database Checks

### Check connection works:
```php
<?php
require_once 'config/config.php';
$db = Database::getInstance()->getConnection();
echo "Connected!";
?>
```

### Check table structure:
```sql
DESCRIBE customers;
```

Should show all 18 fields.

---

## Performance Test

### Test pagination speed:
1. Create 100 customers
2. Go to page 10
3. Should load in <1 second

### Test search performance:
1. Search for "John"
2. Should return results in <1 second

---

## Error Handling Test

### Disconnect database:
1. Stop MySQL
2. Try to create customer
3. Should see: "Connection failed" message
4. NOT a PHP fatal error

### Invalid URL parameter:
1. Visit: `editCustomer.php?id=999999`
2. Should see: "Customer not found"

---

## Security Tests

### SQL Injection Test:
1. In register form, try National ID: `1234567890123456'; DROP TABLE customers; --`
2. Submit

**Expected**: Validation error, NO table drop (safe)

### XSS Test:
1. Register customer with name: `<script>alert('xss')</script>`
2. View in list

**Expected**: Script displays as text, NOT executed

---

## Checklist

- [ ] Create customer works
- [ ] List shows all customers
- [ ] Search finds customers
- [ ] Edit updates values
- [ ] View shows details
- [ ] Pagination works (10 per page)
- [ ] KYC status changes
- [ ] Validation catches errors
- [ ] National ID unique constraint works
- [ ] Timestamps update on edit
- [ ] No SQL injection issues
- [ ] No XSS issues
- [ ] Redirects work correctly
- [ ] Error messages display
- [ ] Success messages display

---

## Common Issues & Solutions

### "Connection failed" error:
- Check MySQL is running
- Verify credentials in config.php
- Check `money_lending_db` exists

### Page shows blank:
- Check PHP error logs
- Verify file paths are correct
- Ensure mod_rewrite is enabled

### Edit doesn't save:
- Check form method is POST
- Verify POST data is being sent
- Check SQL for syntax errors

### Search returns no results:
- Check search term is correct
- Verify customers exist in database
- Try searching different field

---

## Next: Test Other Modules

Once Customer module works, follow same process for:
- Loan Module
- Payment Module
- Recovery Module
