# MLMS Quick Start Guide

## What You Have

A complete MVC-based Customer Management system for the Money Lending Management System (MLMS).

## Start Using It

### 1. View All Customers
```
http://localhost/class_learning/PDO/view/customers/listCustomers.php
```
- See all registered customers
- Search by name, phone, or National ID
- Click Edit or View for details

### 2. Register New Customer
```
http://localhost/class_learning/PDO/view/customers/registerCustomer.php
```
- Fill in: National ID (16 digits), Name, Phone, Address, Income
- System validates input
- Shows success message & redirects

### 3. Edit Existing Customer
```
http://localhost/class_learning/PDO/view/customers/editCustomer.php?id=1
```
- All fields editable except National ID
- Update KYC status (Pending → Verified → Rejected)
- Changes saved & timestamps updated

### 4. View Customer Details
```
http://localhost/class_learning/PDO/view/customers/viewCustomer.php?id=1
```
- Read-only customer profile
- Shows audit info (created/updated dates)
- Links to edit or return to list

---

## File Organization

```
PDO/
├── config/config.php
│   └─ Database connection factory (Singleton)
│
├── modules/
│   ├─ baseModel.php        ← Abstract class with helper methods
│   └─ customerModel.php    ← All customer database queries
│
├── controller/
│   └─ CustomerController.php  ← Business logic & validation
│
└── view/customers/
    ├─ listCustomers.php       ← List page (with search)
    ├─ registerCustomer.php    ← Create form
    ├─ editCustomer.php        ← Edit form
    └─ viewCustomer.php        ← Details page
```

---

## How It Works

1. **User visits view** (HTML form)
2. **Form submits to controller** via POST
3. **Controller validates** the data
4. **If valid, model executes** database query
5. **Controller gets result** from model
6. **View displays** success/error message

---

## Built-In Features

| Feature | Location |
|---------|----------|
| Create Customer | `registerCustomer.php` |
| List All | `listCustomers.php` (10 per page) |
| Search | Top of `listCustomers.php` |
| Edit Customer | `editCustomer.php` |
| View Details | `viewCustomer.php` |
| KYC Status | In edit form (dropdown) |
| Validation | `CustomerController.php` |
| Pagination | `listCustomers.php` |

---

## Database Fields

When creating a customer, you provide:
- **National ID** (16 digits, unique)
- **Full Name** (up to 120 chars)
- **Phone Number** (up to 20 chars, e.g. +250788123456)
- **Email** (optional)
- **Physical Address** (required)
- **Monthly Income** (in RWF)

All data is validated before being saved.

---

## Next: Build Other Modules

To create a **Loan Module**, follow the same pattern:

**1. Create Model** → `modules/LoanModel.php`
```php
class LoanModel extends BaseModel {
    public function save($data) { /* ... */ }
    public function getAll() { /* ... */ }
    // etc.
}
```

**2. Create Controller** → `controller/LoanController.php`
```php
class LoanController {
    public function createLoan($data) { /* ... */ }
    public function getAllLoans() { /* ... */ }
    // etc.
}
```

**3. Create Views** → `view/loans/`
```
├─ listLoans.php
├─ createLoan.php
├─ editLoan.php
└─ viewLoan.php
```

---

## Tips

- **To add a new field**: Edit both model & form
- **To change validation**: Edit `validateCustomerData()` in controller
- **To modify table**: Update SQL in CustomerModel methods
- **To reuse logic**: Add methods to BaseModel
- **For security**: Always use prepared statements (already done)

---

## Current System

✓ Customers can be created, read, updated, deleted
✓ Search & filter customers
✓ Pagination for large lists
✓ KYC status management
✓ Responsive UI
✓ Full validation
✓ Audit timestamps

Next: Loans, Payments, Recovery Management
