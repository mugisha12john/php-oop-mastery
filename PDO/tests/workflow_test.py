"""Run against the configured local MySQL database; test accounts are removed in finally.

Usage: python3 PDO/tests/workflow_test.py
Requires PHP 8.1+, PDO MySQL, Python 3, and permission to bind a local test server.
"""

import http.cookiejar
import json
import os
from pathlib import Path
import re
import secrets
import socket
import subprocess
import tempfile
import time
import urllib.error
import urllib.parse
import urllib.request


ROOT = Path(__file__).resolve().parents[2]
PREFIX = "mlms-test-" + secrets.token_hex(8) + "-"
PASSWORD = "Workflow-" + secrets.token_urlsafe(18)
ENV = dict(os.environ, APP_BASE_PATH="/PDO", SUPER_ADMIN_PASSWORD=PASSWORD)
ADMIN = PREFIX + "admin@example.invalid"


def php(code, data=None):
    result = subprocess.run(
        ["php", "-r", code], cwd=ROOT, env=ENV,
        input=json.dumps(data or {}), capture_output=True, text=True, check=True,
    )
    assert not result.stderr, result.stderr
    return json.loads(result.stdout or "null")


def sql(query, params=()):
    return php(
        'require "PDO/config/config.php"; $input=json_decode(stream_get_contents(STDIN),true);'
        '$s=$conn->prepare($input["sql"]);$s->execute($input["params"]);'
        'echo json_encode($s->columnCount() ? $s->fetchAll() : []);',
        {"sql": query, "params": list(params)},
    )


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, *args, **kwargs):
        return None


class Browser:
    def __init__(self, base):
        self.base = base
        self.client = urllib.request.build_opener(
            urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()), NoRedirect()
        )

    def request(self, path, data=None, method=None):
        body = urllib.parse.urlencode(data).encode() if data is not None else None
        request = urllib.request.Request(self.base + path, body, method=method)
        try:
            response = self.client.open(request, timeout=15)
        except urllib.error.HTTPError as error:
            response = error
        result = (response.code, response.headers, response.read().decode())
        assert not re.search(r"(?:Warning|Fatal error|Deprecated|Notice):", result[2]), result[2]
        return result

    def token(self, page):
        code, _, body = self.request(page)
        assert code == 200, (code, page, body)
        match = re.search(r'name="csrf_token" value="([a-f0-9]+)"', body)
        assert match, page
        return match[1]

    def submit(self, target, page, data):
        return self.request(target, dict(data, csrf_token=self.token(page)))

    def login(self, email):
        code, headers, _ = self.submit("auth.php", "login.php", {
            "action": "login", "email": email, "password": PASSWORD,
        })
        assert code == 303 and headers["Location"] == "/PDO/dashboard.php", (code, dict(headers))


def cleanup():
    # Only rows belonging to this random test prefix are touched.
    sql("DELETE l FROM loans l JOIN customers c ON c.customer_id=l.customer_id JOIN users u ON u.user_id=c.registered_by WHERE u.email LIKE ?", [PREFIX + "%"])
    sql("DELETE a FROM loan_applications a JOIN customers c ON c.customer_id=a.customer_id JOIN users u ON u.user_id=c.registered_by WHERE u.email LIKE ?", [PREFIX + "%"])
    sql("DELETE FROM loan_products WHERE product_name LIKE ?", [PREFIX + "%"])
    sql("DELETE c FROM customers c JOIN users u ON u.user_id = c.registered_by WHERE u.email LIKE ?", [PREFIX + "%"])
    sql("DELETE f FROM firms f JOIN users u ON u.user_id = f.user_id WHERE u.email LIKE ?", [PREFIX + "%"])
    sql("DELETE FROM users WHERE email LIKE ?", [PREFIX + "%"])


def main():
    for _ in range(2):
        subprocess.run(["php", "PDO/database/migrate.php"], cwd=ROOT, env=ENV, check=True, capture_output=True)
    subprocess.run([
        "php", "PDO/database/create_super_admin.php", "--name=Workflow Reviewer", "--email=" + ADMIN,
    ], cwd=ROOT, env=ENV, check=True, capture_output=True)

    with tempfile.TemporaryDirectory(prefix="mlms-http-") as temp:
        with socket.socket() as sock:
            sock.bind(("127.0.0.1", 0))
            port = sock.getsockname()[1]
        with open(Path(temp) / "server.log", "w+") as log:
            server = subprocess.Popen([
                "php", "-d", "session.save_path=" + temp, "-d", "display_errors=1",
                "-d", "error_reporting=32767", "-S", f"127.0.0.1:{port}", "-t", str(ROOT),
            ], cwd=ROOT, env=ENV, stdout=log, stderr=log)
            try:
                base = f"http://127.0.0.1:{port}/PDO/"
                anonymous = Browser(base)
                for _ in range(50):
                    try:
                        assert anonymous.request("login.php")[0] == 200
                        break
                    except urllib.error.URLError:
                        time.sleep(.1)
                else:
                    raise AssertionError("Test server did not start")

                welcome = anonymous.request("index.php")
                assert welcome[0] == 200 and 'Customer sign in' in welcome[2] and 'customerLogin.php' in welcome[2]
                assert anonymous.request("customerLogin.php")[0] == 200

                for page in ["dashboard.php", "customer.php", "view/customers/registerCustomer.php",
                             "view/customers/listCustomers.php", "view/customers/viewCustomer.php?id=1",
                             "view/customers/editCustomer.php?id=1"]:
                    code, headers, _ = anonymous.request(page)
                    assert code == 303 and headers["Location"] == "/PDO/login.php", page
                assert anonymous.request("auth.php", {"action": "login"})[0] == 403
                for page in ["users/insert.php", "users/deleteUser.php?id=1", "users/updateUser.php?id=1",
                             "users/getAllUsers.php", "users/getUserById.php", "database/create_super_admin.php",
                             "database/migrate.php"]:
                    assert anonymous.request(page)[0] == 404, page

                firms = {}
                browsers = {}
                for name in ["alpha", "beta", "rejected"]:
                    browser = Browser(base)
                    registration = {
                        "action": "register", "firm_name": PREFIX + name,
                        "registration_number": PREFIX + name, "full_name": name + " Owner",
                        "email": PREFIX + name + "@example.invalid", "phone": "+250788123456",
                        "address": "Kigali", "password": PASSWORD, "password_confirmation": PASSWORD,
                        "role": "SUPER_ADMIN", "role_id": "1", "status": "APPROVED",
                    }
                    if name == "alpha":
                        invalid = dict(registration, password_confirmation="mismatch")
                        assert browser.submit("auth.php", "registerFirm.php", invalid)[0] == 303
                        page = browser.request("registerFirm.php")[2]
                        assert "Passwords do not match" in page and PASSWORD not in page
                        assert not sql("SELECT user_id FROM users WHERE email = ?", [registration["email"]])
                    code, headers, _ = browser.submit("auth.php", "registerFirm.php", registration)
                    assert code == 303 and headers["Location"] == "/PDO/login.php"
                    row = sql("SELECT f.*, r.role_name FROM firms f JOIN users u ON u.user_id=f.user_id JOIN roles r ON r.role_id=u.role_id WHERE f.email=?", [registration["email"]])[0]
                    assert row["status"] == "PENDING" and row["role_name"] == "FIRM"
                    firms[name] = row
                    browsers[name] = browser
                    if name == "alpha":
                        duplicate = dict(registration, email=PREFIX + "duplicate@example.invalid")
                        browser.submit("auth.php", "registerFirm.php", duplicate)
                        assert not sql("SELECT user_id FROM users WHERE email=?", [duplicate["email"]]), "Registration must roll back its user on duplicate firm"
                        assert "already registered" in browser.request("registerFirm.php")[2]
                    browser.login(registration["email"])
                    assert "awaiting approval" in browser.request("dashboard.php")[2]
                    assert browser.request("view/customers/registerCustomer.php")[0] == 403
                    assert browser.request("customer.php", {"action": "create"})[0] == 403
                    assert browser.submit("firm.php", "dashboard.php", {"action": "approve", "firm_id": row["firm_id"]})[0] == 403

                admin = Browser(base)
                admin.login(ADMIN)
                assert "Super admin workspace" in admin.request("dashboard.php")[2]
                assert admin.request("firm.php", {"action": "approve", "firm_id": firms["alpha"]["firm_id"]})[0] == 403
                for name in ["alpha", "beta"]:
                    assert admin.submit("firm.php", "dashboard.php", {"action": "approve", "firm_id": firms[name]["firm_id"]})[0] == 303
                rejected = firms["rejected"]["firm_id"]
                admin.submit("firm.php", "dashboard.php", {"action": "reject", "firm_id": rejected, "reason": ""})
                assert sql("SELECT status FROM firms WHERE firm_id=?", [rejected])[0]["status"] == "PENDING"
                reason = '<script>alert("review")</script> Missing registration details'
                admin.submit("firm.php", "dashboard.php", {"action": "reject", "firm_id": rejected, "reason": reason})
                rejected_page = browsers["rejected"].request("dashboard.php")[2]
                assert "was rejected" in rejected_page and "&lt;script&gt;" in rejected_page
                assert browsers["rejected"].request("view/customers/listCustomers.php")[0] == 403
                review = sql("SELECT status, approved_by, approved_at FROM firms WHERE firm_id=?", [firms["alpha"]["firm_id"]])[0]
                assert review["status"] == "APPROVED" and review["approved_by"] and review["approved_at"]
                admin.submit("firm.php", "dashboard.php", {"action": "reject", "firm_id": firms["alpha"]["firm_id"], "reason": "Stale form"})
                assert sql("SELECT status FROM firms WHERE firm_id=?", [firms["alpha"]["firm_id"]])[0]["status"] == "APPROVED"

                customer_ids = {}
                customer_forms = {}
                for name in ["alpha", "beta"]:
                    browser = browsers[name]
                    assert "Customer management" in browser.request("dashboard.php")[2], "Approval must refresh without a new login"
                    data = {
                        "action": "create", "national_id_number": "8" + str(secrets.randbelow(10**15)).zfill(15),
                        "full_name": PREFIX + name + " Customer", "phone_number": "+250788123456",
                        "email": name + "@example.invalid", "physical_address": "Kigali", "monthly_income_rwf": "0",
                        "firm_id": firms["beta" if name == "alpha" else "alpha"]["firm_id"], "registered_by": "1",
                    }
                    code, headers, _ = browser.submit("customer.php", "view/customers/registerCustomer.php", data)
                    assert code == 303 and headers["Location"].endswith("listCustomers.php?msg=created")
                    row = sql("SELECT * FROM customers WHERE national_id_number=?", [data["national_id_number"]])[0]
                    assert row["firm_id"] == firms[name]["firm_id"] and row["registered_by"] == firms[name]["user_id"]
                    customer_ids[name] = row["customer_id"]
                    customer_forms[name] = data

                alpha, beta = browsers["alpha"], browsers["beta"]
                assert alpha.request('view/dashboards/superAdmin.php')[0] == 403
                assert admin.request('view/dashboards/firm.php')[0] == 403
                scoped = php(
                    'session_save_path(sys_get_temp_dir()); require "PDO/includes/bootstrap.php";'
                    '$input=json_decode(stream_get_contents(STDIN),true); $_SESSION["user_id"]=$input["actor"];'
                    'require "PDO/modules/customerModel.php"; $model=new CustomerModel();'
                    'echo json_encode(['
                    '"count"=>$model->getTotalCount(),'
                    '"id"=>$model->getById($input["other_id"]),'
                    '"national"=>$model->getByNationalId($input["other_nid"]),'
                    '"email"=>$model->getByEmail("beta@example.invalid"),'
                    '"search"=>$model->search($input["other_nid"]),'
                    '"delete"=>$model->delete($input["other_id"])]);',
                    {"actor": firms["alpha"]["user_id"], "other_id": customer_ids["beta"],
                     "other_nid": customer_forms["beta"]["national_id_number"]},
                )
                assert int(scoped['count']) == 1 and not scoped['id'] and not scoped['national']
                assert not scoped['email'] and not scoped['search'] and not scoped['delete']['success']
                alpha_page = alpha.request("view/customers/listCustomers.php")[2]
                assert customer_forms["alpha"]["full_name"] in alpha_page and customer_forms["beta"]["full_name"] not in alpha_page
                beta_page = beta.request("view/customers/listCustomers.php")[2]
                assert customer_forms["beta"]["full_name"] in beta_page and customer_forms["alpha"]["full_name"] not in beta_page
                search = alpha.request("view/customers/listCustomers.php?" + urllib.parse.urlencode({"search": customer_forms["beta"]["national_id_number"]}))[2]
                assert "No customers found matching" in search
                for page in ["viewCustomer.php", "editCustomer.php"]:
                    assert alpha.request(f"view/customers/{page}?id={customer_ids['beta']}")[0] == 404
                forged = dict(customer_forms["alpha"], action="update", customer_id=customer_ids["beta"], full_name="FORGED")
                assert alpha.submit("customer.php", "view/customers/registerCustomer.php", forged)[0] == 404
                assert sql("SELECT full_name FROM customers WHERE customer_id=?", [customer_ids["beta"]])[0]["full_name"] != "FORGED"
                edit_path = "view/customers/editCustomer.php?id=" + str(customer_ids["alpha"])
                invalid = dict(customer_forms["alpha"], action="update", customer_id=customer_ids["alpha"], full_name="Keep edits <&>", email="bad-email")
                alpha.submit("customer.php", edit_path, invalid)
                page = alpha.request(edit_path)[2]
                assert "Invalid email format" in page and "Keep edits &lt;&amp;&gt;" in page
                updated = dict(customer_forms["alpha"], action="update", customer_id=customer_ids["alpha"], national_id_number=customer_forms["beta"]["national_id_number"], full_name="Updated Alpha", kyc_status="VERIFIED")
                assert alpha.submit("customer.php", edit_path, updated)[0] == 303
                row = sql("SELECT national_id_number, firm_id, full_name FROM customers WHERE customer_id=?", [customer_ids["alpha"]])[0]
                assert row["national_id_number"] == customer_forms["alpha"]["national_id_number"] and row["full_name"] == "Updated Alpha"
                assert row["firm_id"] == firms["alpha"]["firm_id"]
                # Search avoids depending on how many pre-existing customers the local installation has.
                assert "Updated Alpha" in admin.request("view/customers/listCustomers.php?search=Updated+Alpha")[2]
                assert customer_forms["beta"]["full_name"] in admin.request("view/customers/listCustomers.php?search=" + urllib.parse.quote(customer_forms["beta"]["national_id_number"]))[2]
                from customer_portal_checks import check_customer_portal
                check_customer_portal(Browser, sql, base, PREFIX, PASSWORD, browsers, customer_ids, admin)
                assert alpha.request("auth.php", method="GET")[0] == 405
                assert alpha.request("auth.php", {"action": "logout", "csrf_token": "bad"})[0] == 403
                assert alpha.submit("auth.php", "dashboard.php", {"action": "logout"})[0] == 303
                assert alpha.request("dashboard.php")[1]["Location"] == "/PDO/login.php"

                # Exercise account locking without touching a real user's account.
                locked = Browser(base)
                email = firms["rejected"]["email"]
                for _ in range(5):
                    locked.submit("auth.php", "login.php", {"action": "login", "email": email, "password": "incorrect"})
                assert sql("SELECT locked_until > NOW() AS locked FROM users WHERE email=?", [email])[0]["locked"] == 1
                assert locked.submit("auth.php", "login.php", {"action": "login", "email": email, "password": PASSWORD})[1]["Location"] == "/PDO/login.php"
                sql("UPDATE users SET locked_until=DATE_SUB(NOW(),INTERVAL 1 MINUTE) WHERE email=?", [email])
                locked.login(email)
                assert sql("SELECT failed_attempts FROM users WHERE email=?", [email])[0]["failed_attempts"] == 0
                sql("UPDATE users SET is_active=0 WHERE email=?", [email])
                assert locked.request("dashboard.php")[1]["Location"] == "/PDO/login.php"
                assert locked.submit("auth.php", "login.php", {"action": "login", "email": email, "password": PASSWORD})[1]["Location"] == "/PDO/login.php"
                print("PASS: firm workflow, customer account provisioning, customer login/logout, loan submission and validation, duplicate prevention, application and issued-loan details, customer isolation, role restrictions, CSRF, and account protection.")
            finally:
                server.terminate()
                server.wait(timeout=5)
                log.seek(0)
                errors = [line for line in log if re.search(r"(?:Warning|Fatal error|Deprecated|Notice):", line)]
                assert not errors, "".join(errors)


if __name__ == "__main__":
    try:
        main()
    finally:
        cleanup()
