# Verification

Use a development database with the existing configured credentials, or override `DB_DSN`, `DB_USER`, and `DB_PASSWORD`.

```bash
find PDO -name '*.php' -print0 | xargs -0 -n1 php -l
node --check PDO/assets/app.js
php PDO/tests/i18n_catalog_test.php
python3 PDO/tests/workflow_test.py
RUN_BROWSER_QA=1 python3 PDO/tests/workflow_test.py
```

The integration suite applies the migration twice, starts a local PHP server, creates random temporary records, and removes its own data in `finally`. It uses isolated temporary document storage. The optional browser checks require local Chrome (`CHROME_BIN` can override `/usr/bin/google-chrome`), use desktop and mobile viewports, and save screenshots to `/tmp/mlms-ui-preview`.

The checks cover:

- Firm registration, approval/rejection, customer scoping, customer account provisioning, sign-in/out, active-account checks, lockout, CSRF, and forbidden roles.
- Customer request validation, product rules, repeated form submissions, own application details, and application isolation.
- Default customer password, automatic login creation, mandatory first password change, rejected default-password reuse, current-password verification, and invalidation of older sessions.
- Firm product creation/editing, product ownership, identical names across firms, catalog isolation, inactive products, and legacy product assignment.
- Staff provisioning and firm assignment through the super admin interface.
- Real multipart document uploads, detected file types, private downloads, replacement invalidation, rejection reasons, and firm approval of current required documents.
- Manager approval, agreed interest, atomic loan creation, disbursement, exact installment totals, and duplicate-disbursement protection.
- Customer payment submissions, unchanged balances before confirmation, accountant confirmation, exact allocations, receipts, and payment isolation.
- Repeated confirmations, duplicate transaction references, overpayments, and invalid payment dates.
- Overdue display, recovery contact logging, full loan completion, reports, and audit visibility.
- English as the default even when the browser prefers French, matching catalog keys/placeholders, localized validation, dates, amounts, and status labels, plus unchanged customer-entered data.
- Language persistence through sign-in/out, same-page return with query filters, language/CSRF validation, and rejection of external return URLs.
- Optional Chrome rendering at 1440px and 390px in all three languages, the language switcher, navigation on mobile, page overflow, and browser exceptions.

## Manual browser checks

1. Open the welcome and sign-in screens. Check labels, keyboard focus, and mobile navigation.
2. Register and approve two firms. Create staff for each; verify direct URLs cannot cross firm boundaries.
3. Register a customer with an email and confirm their account is linked automatically. Sign in with `customer123456789`: every portal page must redirect to password change until they save a personal password. Test incorrect current passwords, mismatched confirmation, and reuse of the default password. Verify the old password and older sessions stop working after the change.
4. Reject a document with a reason. Verify the customer sees the reason, uploads a replacement, and stays ineligible until the latest ID and income documents are approved. Profile edits must not bypass document approval.
5. As two firms, create products with the same name and different descriptions. Each customer must see only their own firm's active products; a forged application using the other product ID must fail. Test product limits and a collateral product without its supporting document. Verify errors preserve entered application values.
6. Approve or reject the application as manager. Record a disbursement as accountant. Check dates and the monthly schedule.
7. Submit a transfer reference as customer. Confirm the balance does not change until an accountant verifies it. Check rejection notes, partial payments, remaining balances, and final settlement.
8. Open a confirmed payment receipt. Use **Print / save PDF** and inspect the print preview.
9. Inspect an overdue test loan as recovery officer. Log contact and check promised payment/follow-up dates. Customers must not see internal recovery notes.
10. Inspect reports by date and the audit trail as super admin. Change a staff member's firm and deactivate an account; access changes must take effect on the next request.
11. Review layouts at narrow mobile and desktop widths. Tables should scroll within their cards; page content should remain readable. Use a long customer/product name to check wrapping.
12. In a fresh browser, confirm English is selected. Switch to French and Kinyarwanda on public, customer, and staff screens. Check required-field errors, loan estimates, statuses, schedules, and receipt printing. Verify the same screen/filter remains selected and the language survives sign-in and sign-out. Customer notes and product names should keep their entered wording.

Existing imported loans with inconsistent balances or missing schedules are intentionally blocked from new postings until reconciled; migration does not modify those historical financial records.
