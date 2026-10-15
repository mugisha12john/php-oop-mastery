# MLMS - Money Lending Management System

## New MVC Architecture

This refactored structure follows the Model-View-Controller pattern:

### Directory Layout

```
PDO/
├── config/config.php              # Database singleton factory
├── modules/
│   ├── baseModel.php              # Abstract CRUD base class
│   └── customerModel.php          # Customer data layer
├── controller/
│   └── CustomerController.php     # Business logic
└── view/customers/
    ├── listCustomers.php          # List with search & pagination
    ├── registerCustomer.php       # Create customer form
    ├── editCustomer.php           # Edit customer form
    └── viewCustomer.php           # View customer details
```

## Architecture Layers

### 1. Config (`config/config.php`)
- **Singleton pattern** database connection
- All classes inherit connection via `Database::getInstance()`
- Centralized DSN, credentials, PDO attributes

### 2. Models (`modules/`)

**BaseModel**: Abstract class with CRUD helpers
- `execute($sql, $params)` - Run prepared statement
- `fetchOne($sql, $params)` - Get single record
- `fetchAll($sql, $params)` - Get multiple records
- Transaction methods: `beginTransaction()`, `commit()`, `rollback()`

**CustomerModel**: Inherits from BaseModel
- `save()` - Insert new customer
- `update($id)` - Update customer
- `getAll()`, `getById()`, `getByNationalId()`, `getByEmail()`, `getByPhone()`
- `search($term)` - Full-text search
- `getByKycStatus()` - Filter by verification status
- `delete()` - Delete customer
- Validation: 16-digit NID, email, phone, income

### 3. Controllers (`controller/`)

**CustomerController**: Routes views to models
- `getAllCustomers()` - List with pagination
- `getCustomer()` - Single customer
- `createCustomer($data)` - Create with validation
- `updateCustomer($id, $data)` - Update with validation
- `deleteCustomer()` - Delete
- `searchCustomers()` - Full-text search
- `getCustomersByKycStatus()` - Filter by KYC status

All methods return consistent response arrays:
```php
[
    'success' => true/false,
    'message' => 'Status message',
    'customers' => [...],
    'errors' => [...]
]
```

### 4. Views (`view/customers/`)

**listCustomers.php**
- Displays all customers
- Search by name/phone/ID
- Pagination (10 per page)
- KYC status badges
- Edit/View buttons

**registerCustomer.php**
- Create new customer form
- Sections: Identity, Contact, Financial
- Real-time validation
- Success redirect

**editCustomer.php**
- Update existing customer
- National ID read-only
- KYC status dropdown
- Validation & error display

**viewCustomer.php**
- Display full customer profile
- Identity, Contact, Financial, Audit sections
- Edit/Back buttons

## Usage

### Access customer list
```
http://localhost/class_learning/PDO/view/customers/listCustomers.php
```

### Create customer
```
http://localhost/class_learning/PDO/view/customers/registerCustomer.php
```

### Edit customer (ID 5)
```
http://localhost/class_learning/PDO/view/customers/editCustomer.php?id=5
```

### View customer (ID 5)
```
http://localhost/class_learning/PDO/view/customers/viewCustomer.php?id=5
```

## Key Features

✓ Prepared statements for SQL injection prevention
✓ Input validation at controller & model levels
✓ Transaction support for complex operations
✓ Pagination for large datasets
✓ Search functionality
✓ KYC status management
✓ Audit timestamps (created_at, updated_at)
✓ Responsive UI with modern CSS
✓ Error handling with user-friendly messages
✓ Singleton database connection pattern

## Database Requirements

- MySQL 8.0+ / MariaDB 10.4+
- Database: `money_lending_db`
- User: `app_user` with password `buban1234`
- Tables: `customers`, `roles`, `users`, etc. (from schema)

## Next Steps

To build additional modules (Loans, Payments, etc.):

1. **Create Model** → `modules/LoanModel.php` extends `BaseModel`
2. **Create Controller** → `controller/LoanController.php`
3. **Create Views** → `view/loans/` folder with forms
4. **Follow the same pattern** as CustomerModule

This structure ensures:
- Clean separation of concerns
- Reusable components
- Easy testing & maintenance
- Scalability for future features
