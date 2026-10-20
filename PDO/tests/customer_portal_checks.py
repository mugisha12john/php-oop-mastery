"""Customer portal integration checks, run by workflow_test.py with isolated fixtures."""
import re


def check_customer_portal(Browser, sql, base, prefix, password, firms, customer_ids, admin):
    customers = {}
    for name in ["alpha", "beta"]:
        account_path = "customerAccount.php?id=" + str(customer_ids[name])
        email = prefix + name + "-customer@example.invalid"
        browser = firms[name]
        other_id = customer_ids["beta" if name == "alpha" else "alpha"]
        assert browser.request("customerAccount.php?id=" + str(other_id))[0] == 404
        assert browser.request(account_path, {"email": email})[0] == 403
        if name == "alpha":
            # Failure must leave the customer unlinked and never echo passwords.
            invalid = browser.submit(account_path, account_path, {
                "email": email, "password": password, "password_confirmation": "mismatch",
            })
            assert invalid[0] == 200 and "Passwords do not match" in invalid[2] and password not in invalid[2]
            assert sql("SELECT user_id FROM customers WHERE customer_id=?", [customer_ids[name]])[0]["user_id"] is None
            # An existing staff email cannot be attached to a customer account.
            duplicate = browser.submit(account_path, account_path, {
                "email": prefix + "alpha@example.invalid", "password": password, "password_confirmation": password,
            })
            assert duplicate[0] == 200 and "already has an account" in duplicate[2]
            assert sql("SELECT user_id FROM customers WHERE customer_id=?", [customer_ids[name]])[0]["user_id"] is None
        result = browser.submit(account_path, account_path, {
            "email": email, "password": password, "password_confirmation": password,
            "role": "SUPER_ADMIN", "customer_id": other_id,
        })
        assert result[0] == 303, result
        row = sql("SELECT u.user_id, r.role_name FROM users u JOIN roles r ON r.role_id=u.role_id WHERE u.email=?", [email])[0]
        assert row["role_name"] == "CUSTOMER"
        assert sql("SELECT user_id FROM customers WHERE customer_id=?", [customer_ids[name]])[0]["user_id"] == row["user_id"]
        # Creating login access again must not reset or duplicate the account.
        assert "ACCOUNT LINKED" in browser.request(account_path)[2]
        browser.submit(account_path, account_path, {
            "email": prefix + "second@example.invalid", "password": password, "password_confirmation": password,
        })
        assert not sql("SELECT user_id FROM users WHERE email=?", [prefix + "second@example.invalid"])
        client = Browser(base)
        code, headers, _ = client.submit("auth.php", "customerLogin.php", {
            "action": "customer_login", "email": email, "password": password,
        })
        assert code == 303 and headers["Location"] == "/PDO/dashboard.php"
        assert client.request("index.php")[1]["Location"] == "/PDO/dashboard.php"
        assert "Customer workspace" in client.request("dashboard.php")[2]
        for path in ["customerAccount.php?id=" + str(customer_ids[name]), "view/customers/listCustomers.php", "view/dashboards/firm.php"]:
            assert client.request(path)[0] == 403
        customers[name] = (client, row["user_id"])

    # The dedicated customer login rejects a valid firm account.
    wrong_role = Browser(base)
    assert wrong_role.submit("auth.php", "customerLogin.php", {
        "action": "customer_login", "email": prefix + "alpha@example.invalid", "password": password,
    })[1]["Location"] == "/PDO/customerLogin.php"
    assert wrong_role.request("dashboard.php")[0] == 303

    sql("INSERT INTO loan_products (product_name,min_amount_rwf,max_amount_rwf,interest_rate_pct,default_term_months,max_term_months) VALUES (?,100,1000000,10,3,12)", [prefix + "portal-product"])
    product = sql("SELECT product_id FROM loan_products WHERE product_name=?", [prefix + "portal-product"])[0]["product_id"]
    alpha, alpha_user = customers["alpha"]
    beta, _ = customers["beta"]
    assert "profile needs attention" in beta.request("requestLoan.php")[2]
    assert firms["alpha"].request("requestLoan.php")[0] == 403
    assert alpha.request("loan.php", {"product_id": product})[0] == 403

    def request_data(browser, **changes):
        body = browser.request("requestLoan.php")[2]
        token = re.search(r'name="request_token" value="([a-f0-9]+)"', body)
        assert token, body
        return dict({
            "request_token": token[1], "product_id": product,
            "amount_requested_rwf": "10000.50", "term_months": "3",
            "purpose": "Business supplies <&>", "customer_id": customer_ids["beta"],
            "submitted_by": "1", "status": "APPROVED", "approved_amount_rwf": "999999",
        }, **changes)

    assert alpha.submit("loan.php", "requestLoan.php", request_data(alpha, amount_requested_rwf="0"))[0] == 303
    assert "positive loan amount" in alpha.request("requestLoan.php")[2]
    alpha.submit("loan.php", "requestLoan.php", request_data(alpha, amount_requested_rwf="1000001"))
    assert "requested amount must be between" in alpha.request("requestLoan.php")[2]
    alpha.submit("loan.php", "requestLoan.php", request_data(alpha, term_months="13"))
    assert "up to 12 months" in alpha.request("requestLoan.php")[2]
    alpha.submit("loan.php", "requestLoan.php", request_data(alpha, purpose=""))
    assert "purpose of your loan" in alpha.request("requestLoan.php")[2]
    sql("UPDATE loan_products SET is_active=0 WHERE product_id=?", [product])
    alpha.submit("loan.php", "requestLoan.php", request_data(alpha))
    assert "Choose an available loan product" in alpha.request("requestLoan.php")[2]
    sql("UPDATE loan_products SET is_active=1 WHERE product_id=?", [product])
    # Recheck eligibility at submission, even when the form was opened earlier.
    stale = request_data(alpha)
    sql("UPDATE customers SET kyc_status='PENDING' WHERE customer_id=?", [customer_ids["alpha"]])
    alpha.submit("loan.php", "dashboard.php", stale)
    assert not sql("SELECT application_id FROM loan_applications WHERE customer_id=?", [customer_ids["alpha"]])
    sql("UPDATE customers SET kyc_status='VERIFIED' WHERE customer_id=?", [customer_ids["alpha"]])

    submitted = request_data(alpha)
    result = alpha.submit("loan.php", "requestLoan.php", submitted)
    assert result[0] == 303 and "/PDO/loan.php?id=" in result[1]["Location"]
    row = sql("SELECT * FROM loan_applications WHERE customer_id=?", [customer_ids["alpha"]])[0]
    assert row["submitted_by"] == alpha_user and row["status"] == "SUBMITTED"
    assert row["approved_amount_rwf"] is None and row["amount_requested_rwf"] == "10000.50"
    detail_path = "loan.php?id=" + str(row["application_id"])
    detail = alpha.request(detail_path)
    assert detail[0] == 200 and "10,000.50" in detail[2] and "Business supplies &lt;&amp;&gt;" in detail[2]
    assert beta.request(detail_path)[0] == 404
    assert "portal-product" not in beta.request("dashboard.php")[2]
    assert firms["alpha"].request(detail_path)[0] == 403
    # A repeat POST with the consumed form token cannot create a second application.
    alpha.submit("loan.php", "dashboard.php", submitted)
    assert len(sql("SELECT application_id FROM loan_applications WHERE customer_id=?", [customer_ids["alpha"]])) == 1
    # Decisions and issued balances come from the existing loan records.
    sql("UPDATE loan_applications SET status='REJECTED', rejection_reason=?, decision_date=NOW() WHERE application_id=?", ["Missing documents <script>", row["application_id"]])
    assert "Missing documents &lt;script&gt;" in alpha.request(detail_path)[2]
    sql("UPDATE loan_applications SET status='APPROVED', approved_amount_rwf=10000.50 WHERE application_id=?", [row["application_id"]])
    sql("INSERT INTO loans (application_id,customer_id,principal_amount_rwf,interest_rate_pct,interest_amount_rwf,total_payable_rwf,outstanding_balance_rwf,term_months,status) VALUES (?,?,10000.50,10,1000.05,11000.55,9000.25,3,'ACTIVE')", [row["application_id"], customer_ids["alpha"]])
    assert "9,000.25" in alpha.request(detail_path)[2]
    assert "9,000.25" not in beta.request("dashboard.php")[2]
    sql("UPDATE customers SET kyc_status='VERIFIED' WHERE customer_id=?", [customer_ids["beta"]])
    assert beta.submit("loan.php", "requestLoan.php", request_data(beta))[0] == 303
    assert sql("SELECT customer_id FROM loan_applications WHERE customer_id=?", [customer_ids["beta"]])
    result = alpha.submit("auth.php", "dashboard.php", {"action": "logout"})
    assert result[1]["Location"] == "/PDO/customerLogin.php"
    assert alpha.request(detail_path)[0] == 303
