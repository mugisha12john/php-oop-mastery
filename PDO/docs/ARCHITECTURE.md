# Folder guide

The application keeps PHP, PDO, MySQL, and plain CSS/JavaScript. There is no framework or build step. Business code lives in `app/`; screens and form handlers call those classes. Files keep their existing global class names, loaded through `app/autoload.php`.

| Folder | Responsibility | Examples |
| --- | --- | --- |
| `app/Auth/` | Sign-in, account checks, password changes | `AuthService.php`, `PasswordService.php` |
| `app/Customers/` | Customer records, login provisioning, customer portal data | `CustomerModel.php`, `CustomerAccountService.php`, `CustomerPortalService.php` |
| `app/Firms/` | Firm registration and approval | `FirmModel.php` |
| `app/Http/Controllers/` | Coordinate customer requests with models | `CustomerController.php` |
| `app/Lending/` | Tenant access, documents, products, loans, schedules, payments, staff | `Access.php`, `DocumentService.php`, `LendingService.php`, `AdminService.php`, `Money.php` |
| `app/Support/` | Shared model and translation helpers | `BaseModel.php`, `Translation.php` |
| `actions/` | POST handlers without page layouts | `auth.php`, `customers.php`, `firms.php`, `language.php` |
| `portal/` | Borrower application, document, password, and repayment screens; document reviews shared with staff | `apply.php`, `application.php`, `payments.php`, `receipt.php` |
| `workspace/` | Staff lending decisions, portfolio, recovery, products, users, reports, audit | `application.php`, `loan.php`, `products.php` |
| `view/auth/` | Public sign-in and firm registration screens | `login.php`, `customerLogin.php`, `registerFirm.php` |
| `view/customers/` | Customer management screens and shared form | `listCustomers.php`, `form.php`, `loginAccess.php` |
| `view/dashboards/` | Role-specific overview content | `customer.php`, `firm.php`, `superAdmin.php` |
| `view/loans/` | Shared schedule, repayment, and history display | Shared by customer and staff loan screens |
| `includes/` | Session bootstrap, access helpers, layout, icons, navigation | `bootstrap.php`, `header.php`, `languageSwitcher.php` |
| `resources/lang/` | User-interface translations | `en.php`, `fr.php`, `rw.php` |
| `assets/` | Browser styling and progressive enhancements | `app.css`, `app.js` |
| `config/` | PDO connection configuration | Environment overrides supported |
| `database/` | Additive migrations and CLI administrator setup | `migrate.php`, `create_super_admin.php` |
| `examples/users/` | Original CLI learning exercises, outside application workflows | Web access returns 404 |
| `tests/` | Catalog checks, integration workflows, optional Chrome checks | `workflow_test.py`, `i18n_catalog_test.php` |
| `docs/` | Developer explanations | This guide and `I18N.md` |

## How a request works

1. A public PHP page loads `includes/bootstrap.php`. This initializes the session, configuration, class loader, translation helpers, and request/access helpers.
2. The page checks authentication, role, and firm access. A service handles queries, validation, or transactions; the page renders escaped results with the shared header/footer.
3. Form handlers require POST and a valid CSRF token. Ownership comes from the signed-in account. Successful changes redirect to a page, with flash messages translated when displayed.
4. The header loads versioned CSS/JavaScript and the selected language catalog. Browser-only loan estimates receive translated templates as JSON.

Some existing screens handle their own POST requests; they keep the same checks. New standalone form handlers belong in `actions/` when that makes the screen easier to follow. Financial and document operations belong in services so they can be reused without copying access or accounting rules.

## Existing URLs

Root files preserve bookmarked URLs and existing forms. Most are small delegates:

| Existing URL | Implementation |
| --- | --- |
| `login.php`, `customerLogin.php`, `registerFirm.php` | `view/auth/` |
| `auth.php` | `actions/auth.php` |
| `customer.php` | `actions/customers.php` |
| `firm.php` | `actions/firms.php` |
| `customerAccount.php` | `view/customers/loginAccess.php` |
| `requestLoan.php` | `portal/apply.php` |
| `loan.php` | `portal/application.php` |

`index.php` remains the welcome page and `dashboard.php` selects the overview for the current role. The retired `users/` web location returns 404; run learning examples from `examples/users/` using the CLI.

## Where new work goes

- Add a business class to the appropriate `app/` feature folder, then register its class name/path in `app/autoload.php`. Keep SQL and transactions in these classes.
- Add borrower screens to `portal/`, staff screens to `workspace/`, and reusable presentation fragments to `view/`. Use `app_url()` for links and redirects so alternate web mounts work.
- Reuse `includes/header.php`, `includes/footer.php`, and existing assets. Put new interface wording in all three language catalogs and call `t()`; see [I18N.md](I18N.md).
- Add database changes under `database/` through the rerunnable migration. Keep private uploaded documents outside the web root using `DOCUMENT_STORAGE_PATH`.
- Add integration checks for meaningful behavior under `tests/`. Run the checks described in [TESTING.md](../TESTING.md).

The former `modules/` and `controller/` classes have moved into `app/`. The default development document-storage identity intentionally retains its original location key so this move does not disconnect uploaded files. An explicit `DOCUMENT_STORAGE_PATH` continues to take precedence.
