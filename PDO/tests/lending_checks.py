"""End-to-end lending, document upload, repayment, and firm isolation checks."""
import re
import secrets
import urllib.request
import urllib.error
import os
import json
import subprocess
from pathlib import Path
from account_checks import sign_in_customer


def check_lending(Browser, sql, base, prefix, password, browsers, firms, admin):
    def nonce(browser, page, **values):
        body = browser.request(page)[2]
        match = re.search(r'name="form_nonce" value="([a-f0-9]+)"', body)
        assert match, page
        return dict(values, form_nonce=match[1])

    def multipart(browser, kind, content, filename="identity.pdf"):
        page = "portal/documents.php"
        data = nonce(browser, page, csrf_token=browser.token(page), document_type=kind)
        boundary = "MLMS" + secrets.token_hex(16)
        chunks = []
        for key, value in data.items():
            chunks.append(f'--{boundary}\r\nContent-Disposition: form-data; name="{key}"\r\n\r\n{value}\r\n'.encode())
        chunks.extend([
            f'--{boundary}\r\nContent-Disposition: form-data; name="document"; filename="{filename}"\r\nContent-Type: application/pdf\r\n\r\n'.encode(),
            content, f'\r\n--{boundary}--\r\n'.encode(),
        ])
        request = urllib.request.Request(base + page, b"".join(chunks), headers={"Content-Type": "multipart/form-data; boundary=" + boundary})
        try:
            response = browser.client.open(request)
        except urllib.error.HTTPError as error:
            response = error
        assert response.code == 303, (response.code, response.read())

    # Provision each staff role through the actual super admin interface.
    staff = {}
    for key, role in [("manager", "MANAGER"), ("accountant", "ACCOUNTANT"), ("recovery", "RECOVERY_OFFICER")]:
        email = prefix + key + "@example.invalid"
        fields = nonce(admin, "workspace/users.php", action="user", name=key.title(), email=email,
                       role=role, firm_id=firms["alpha"]["firm_id"], password=password)
        # The first nonce belongs to an existing user row; select the create form nonce.
        body = admin.request("workspace/users.php")[2]
        fields["form_nonce"] = re.search(r'id="new-user".*?name="form_nonce" value="([a-f0-9]+)"', body, re.S)[1]
        assert admin.submit("workspace/action.php", "workspace/users.php", fields)[0] == 303
        row = sql("SELECT u.user_id,u.firm_id,r.role_name FROM users u JOIN roles r ON r.role_id=u.role_id WHERE email=?", [email])[0]
        assert row["role_name"] == role and row["firm_id"] == firms["alpha"]["firm_id"]
        browser = Browser(base)
        browser.login(email)
        assert browser.request("workspace/index.php")[0] == 200
        assert browser.request("workspace/users.php")[0] == 403
        staff[key] = browser
    manager, accountant, recovery = [staff[k] for k in ["manager", "accountant", "recovery"]]
    assert manager.request("workspace/action.php", {"action": "disburse", "csrf_token": manager.token("workspace/index.php")})[0] == 403
    assert accountant.request("portal/documents.php")[0] == 403
    assert recovery.request("workspace/reports.php")[0] == 403

    # A customer has no access to money until documents, approval, and disbursement finish.
    owner = browsers["alpha"]
    customer_data = {
        "action": "create", "national_id_number": "7" + str(secrets.randbelow(10**15)).zfill(15),
        "full_name": "Aline Uwase", "phone_number": "+250788123456", "email": prefix + "lending-customer@example.invalid",
        "physical_address": "Kigali", "monthly_income_rwf": "500000",
    }
    assert owner.submit("customer.php", "view/customers/registerCustomer.php", customer_data)[0] == 303
    cid = sql("SELECT customer_id FROM customers WHERE national_id_number=?", [customer_data["national_id_number"]])[0]["customer_id"]
    account_path = "customerAccount.php?id=" + str(cid)
    email = prefix + "lending-customer@example.invalid"
    assert 'ACCOUNT LINKED' in owner.request(account_path)[2]
    customer = sign_in_customer(Browser, sql, base, email, password)
    assert customer.request("workspace/index.php")[0] == 403
    assert "profile needs attention" in customer.request("requestLoan.php")[2]
    multipart(customer, "NATIONAL_ID", b"<?php echo 'unsafe'; ?>", "fake.pdf")
    assert not sql("SELECT document_id FROM customer_documents WHERE customer_id=?", [cid])
    assert "Only PDF" in customer.request("portal/documents.php")[2]
    pdf = b"%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF\n"
    for kind in ["NATIONAL_ID", "PROOF_OF_INCOME"]:
        multipart(customer, kind, pdf)
    docs = sql("SELECT * FROM customer_documents WHERE customer_id=? ORDER BY document_id", [cid])
    for doc in docs:
        assert doc["review_status"] == "PENDING"
        assert browsers["beta"].request("portal/documents.php?download=" + str(doc["document_id"]))[0] == 404
        assert customer.request("portal/documents.php?download=" + str(doc["document_id"]))[2].startswith("%PDF")
        assert owner.submit("workspace/action.php", "portal/documents.php", {
            "action": "document", "id": doc["document_id"], "decision": "APPROVED", "notes": "Clear and readable",
        })[0] == 303
    assert sql("SELECT kyc_status FROM customers WHERE customer_id=?", [cid])[0]["kyc_status"] == "VERIFIED"
    # A replacement invalidates previous approval; rejection reason is visible to the customer.
    multipart(customer, "NATIONAL_ID", pdf)
    assert "profile needs attention" in customer.request("requestLoan.php")[2]
    replacement = sql("SELECT MAX(document_id) AS id FROM customer_documents WHERE customer_id=?", [cid])[0]["id"]
    owner.submit("workspace/action.php", "portal/documents.php", {"action":"document", "id":replacement, "decision":"REJECTED", "notes":"Upload both sides <script>"})
    assert "Upload both sides &lt;script&gt;" in customer.request("portal/documents.php")[2]
    multipart(customer, "NATIONAL_ID", pdf)
    replacement = sql("SELECT MAX(document_id) AS id FROM customer_documents WHERE customer_id=?", [cid])[0]["id"]
    owner.submit("workspace/action.php", "portal/documents.php", {"action":"document", "id":replacement, "decision":"APPROVED", "notes":""})

    sql("INSERT INTO loan_products (firm_id,product_name,min_amount_rwf,max_amount_rwf,interest_rate_pct,default_term_months,max_term_months) VALUES (?,?,100,1000000,10,3,12)", [firms['alpha']['firm_id'], prefix + "lifecycle"])
    pid = sql("SELECT product_id FROM loan_products WHERE product_name=?", [prefix + "lifecycle"])[0]["product_id"]
    body = customer.request("requestLoan.php")[2]
    token = re.search(r'name="request_token" value="([a-f0-9]+)"', body)[1]
    assert customer.submit("loan.php", "requestLoan.php", {"request_token":token,"product_id":pid,"amount_requested_rwf":"10000.50","term_months":"3","purpose":"Business stock"})[0] == 303
    app = sql("SELECT * FROM loan_applications WHERE customer_id=?", [cid])[0]
    app_page = "workspace/application.php?id=" + str(app["application_id"])
    assert browsers["beta"].request(app_page)[0] == 404
    assert manager.request(app_page)[0] == 200
    fields = {"action":"application","id":app["application_id"],"decision":"APPROVED","amount":"10000.50","notes":"Income checked"}
    # Eligibility is checked again when the manager decides, even for an older application.
    multipart(customer, "PROOF_OF_INCOME", pdf)
    manager.submit("workspace/action.php", app_page, fields)
    assert not sql("SELECT loan_id FROM loans WHERE customer_id=?", [cid])
    income = sql("SELECT MAX(document_id) AS id FROM customer_documents WHERE customer_id=?", [cid])[0]['id']
    owner.submit("workspace/action.php", "portal/documents.php", {"action":"document", "id":income, "decision":"APPROVED", "notes":"Updated income checked"})
    manager.submit("workspace/action.php", app_page, dict(fields, amount='10000.51'))
    assert not sql("SELECT loan_id FROM loans WHERE customer_id=?", [cid])
    assert manager.submit("workspace/action.php", app_page, fields)[0] == 303
    assert manager.submit("workspace/action.php", app_page, fields)[0] == 303
    loan = sql("SELECT * FROM loans WHERE customer_id=?", [cid])[0]
    lid = loan["loan_id"]
    loan_page = "workspace/loan.php?id=" + str(lid)
    assert loan["status"] == "APPROVED" and loan["interest_amount_rwf"] == "1000.05" and loan["total_payable_rwf"] == "11000.55"
    assert len(sql("SELECT loan_id FROM loans WHERE customer_id=?", [cid])) == 1
    # Product changes do not rewrite the agreed rate of an already approved loan.
    product_page = 'workspace/products.php?edit=' + str(pid)
    product_data = nonce(admin, product_page, action='product', id=pid, name=prefix+'lifecycle', description='Business funding', min='100', max='1000000', rate='20', default_term='3', term='12', collateral='0', active='1')
    admin.submit('workspace/action.php', product_page, product_data)
    assert sql('SELECT interest_rate_pct FROM loan_products WHERE product_id=?', [pid])[0]['interest_rate_pct'] == '20.00'
    assert sql('SELECT interest_rate_pct FROM loans WHERE loan_id=?', [lid])[0]['interest_rate_pct'] == '10.00'
    assert customer.request("portal/payments.php?loan_id=" + str(lid))[0] == 200
    assert browsers["beta"].request(loan_page)[0] == 404
    today = sql('SELECT CURDATE() AS today')[0]['today']
    reference = prefix + "disbursement"
    fields = nonce(accountant, loan_page, action="disburse", id=lid, date=today, method="BANK_TRANSFER", reference=reference)
    assert accountant.submit("workspace/action.php", loan_page, fields)[0] == 303
    schedule = sql("SELECT * FROM repayment_schedules WHERE loan_id=? ORDER BY installment_number", [lid])
    assert len(schedule) == 3
    assert sum(round(float(s["principal_due_rwf"])*100) for s in schedule) == 1000050
    assert sum(round(float(s["interest_due_rwf"])*100) for s in schedule) == 100005
    assert accountant.submit("workspace/action.php", loan_page, fields)[0] == 303
    assert len(sql("SELECT disbursement_id FROM disbursements WHERE loan_id=?", [lid])) == 1

    payment_page = "portal/payments.php?loan_id=" + str(lid)
    submission_ref = prefix + "mobile-001"
    fields = nonce(customer, payment_page, loan_id=lid, amount="2000.25", date=today, method="MOBILE_MONEY", reference=submission_ref)
    assert customer.submit("portal/payments.php", payment_page, fields)[0] == 303
    assert sql("SELECT outstanding_balance_rwf FROM loans WHERE loan_id=?", [lid])[0]["outstanding_balance_rwf"] == "11000.55"
    sub = sql("SELECT * FROM payment_submissions WHERE loan_id=?", [lid])[0]
    assert accountant.submit("workspace/action.php", "workspace/index.php?tab=payments", {"action":"payment_review","id":sub["submission_id"],"decision":"CONFIRMED","notes":"Transfer verified"})[0] == 303
    payment = sql("SELECT * FROM payments WHERE loan_id=?", [lid])[0]
    assert payment["amount_paid_rwf"] == "2000.25" and payment["balance_after_rwf"] == "9000.30"
    assert sql("SELECT outstanding_balance_rwf FROM loans WHERE loan_id=?", [lid])[0]["outstanding_balance_rwf"] == "9000.30"
    receipt_page = "portal/receipt.php?id=" + str(payment["payment_id"])
    assert "9,000.30" in customer.request(receipt_page)[2]
    assert browsers["beta"].request(receipt_page)[0] == 404
    assert accountant.submit("workspace/action.php", "workspace/index.php?tab=payments", {"action":"payment_review","id":sub["submission_id"],"decision":"CONFIRMED","notes":"Repeat"})[0] == 303
    assert len(sql("SELECT payment_id FROM payments WHERE loan_id=?", [lid])) == 1
    if os.environ.get('RUN_BROWSER_QA') == '1':
        def session(browser):
            handler = next(h for h in browser.client.handlers if isinstance(h, urllib.request.HTTPCookieProcessor))
            return next(c.value for c in handler.cookiejar if c.name == 'PHPSESSID')
        pages = [
            {'name':'customer-dashboard','path':'dashboard.php','session':session(customer)},
            {'name':'customer-documents','path':'portal/documents.php','session':session(customer)},
            {'name':'customer-products','path':'requestLoan.php?product='+str(pid),'session':session(customer)},
            {'name':'customer-payments','path':payment_page,'session':session(customer)},
            {'name':'customer-receipt','path':receipt_page,'session':session(customer)},
            {'name':'customer-password','path':'portal/password.php','session':session(customer)},
            {'name':'firm-products','path':'workspace/products.php','session':session(owner)},
            {'name':'manager-workspace','path':'workspace/index.php','session':session(manager)},
            {'name':'accountant-loan','path':loan_page,'session':session(accountant)},
            {'name':'admin-users','path':'workspace/users.php','session':session(admin)},
        ]
        subprocess.run(['node', str(Path(__file__).with_name('browser_check.mjs'))], input=json.dumps({'base':base,'pages':pages}), text=True, check=True, timeout=90)
    # Duplicate reference, overpayment, and earlier dates must leave all balances unchanged.
    for amount, ref, day in [("1",submission_ref,today),("9000.31",prefix+"too-much",today),("1",prefix+"early","2000-01-01")]:
        fields = nonce(accountant, loan_page, action="payment", id=lid, amount=amount, date=day, method="CASH", reference=ref)
        accountant.submit("workspace/action.php", loan_page, fields)
        assert len(sql("SELECT payment_id FROM payments WHERE loan_id=?", [lid])) == 1
    # Simulate time passing for a test installment, then verify recovery and overdue display.
    sql("UPDATE repayment_schedules SET due_date=DATE_SUB(CURDATE(),INTERVAL 3 DAY) WHERE schedule_id=?", [schedule[0]["schedule_id"]])
    assert "Aline Uwase" in recovery.request("workspace/index.php?tab=overdue")[2]
    fields = nonce(recovery, loan_page, action="recovery", id=lid, contact_type="PHONE_CALL", notes="Borrower promised to settle <&>", promised_date=today, followup_date=today)
    recovery.submit("workspace/action.php", loan_page, fields)
    assert "Borrower promised to settle &lt;&amp;&gt;" in recovery.request(loan_page)[2]
    assert "OVERDUE" in customer.request("loan.php?id=" + str(app["application_id"]))[2]
    fields = nonce(accountant, loan_page, action="payment", id=lid, amount="9000.30", date=today, method="BANK_TRANSFER", reference=prefix+"final-payment")
    accountant.submit("workspace/action.php", loan_page, fields)
    closed = sql("SELECT * FROM loans WHERE loan_id=?", [lid])[0]
    assert closed["status"] == "COMPLETED" and closed["outstanding_balance_rwf"] == "0.00"
    assert all(s["status"] == "PAID" for s in sql("SELECT status FROM repayment_schedules WHERE loan_id=?", [lid]))
    assert sql("SELECT SUM(amount_rwf) AS total FROM payment_allocations a JOIN payments p ON p.payment_id=a.payment_id WHERE p.loan_id=?", [lid])[0]["total"] == "11000.55"
    assert "11,000.55" in manager.request("workspace/reports.php")[2]
    assert "REPAYMENT POSTED" in admin.request("workspace/audit.php")[2]
    # Manager review and rejection leave no issued loan and expose only the decision reason.
    body = customer.request('requestLoan.php')[2]
    token = re.search(r'name="request_token" value="([a-f0-9]+)"', body)[1]
    customer.submit('loan.php','requestLoan.php', {'request_token':token,'product_id':pid,'amount_requested_rwf':'500.25','term_months':'3','purpose':'Another request'})
    rejected_id = sql('SELECT MAX(application_id) AS id FROM loan_applications WHERE customer_id=?',[cid])[0]['id']
    reject_page = 'workspace/application.php?id=' + str(rejected_id)
    manager.submit('workspace/action.php',reject_page,{'action':'application','id':rejected_id,'decision':'UNDER_REVIEW','amount':'','notes':'Internal assessment'})
    assert sql('SELECT status FROM loan_applications WHERE application_id=?',[rejected_id])[0]['status'] == 'UNDER_REVIEW'
    manager.submit('workspace/action.php',reject_page,{'action':'application','id':rejected_id,'decision':'REJECTED','amount':'','notes':''})
    assert sql('SELECT status FROM loan_applications WHERE application_id=?',[rejected_id])[0]['status'] == 'UNDER_REVIEW'
    manager.submit('workspace/action.php',reject_page,{'action':'application','id':rejected_id,'decision':'REJECTED','amount':'','notes':'Income commitments too high <&>'})
    assert 'Income commitments too high &lt;&amp;&gt;' in customer.request('loan.php?id='+str(rejected_id))[2]
    assert not sql('SELECT loan_id FROM loans WHERE application_id=?',[rejected_id])
    # Reassigning a staff member takes effect in their existing session.
    uid = sql('SELECT user_id FROM users WHERE email=?',[prefix+'manager@example.invalid'])[0]['user_id']
    body = admin.request('workspace/users.php')[2]
    form = next(f for f in re.findall(r'<form.*?</form>',body,re.S) if f'name="id" value="{uid}"' in f)
    change = {'action':'user','id':uid,'active':'1','firm_id':firms['beta']['firm_id'],'form_nonce':re.search(r'name="form_nonce" value="([a-f0-9]+)"',form)[1]}
    admin.submit('workspace/action.php','workspace/users.php',change)
    assert manager.request(app_page)[0] == 404
    assert manager.request('portal/documents.php?download='+str(replacement))[0] == 404
    print("PASS: private document uploads and replacement/rejection, firm document approval, staff provisioning and firm isolation, manager approval, one-time disbursement, exact schedules, confirmed repayments, receipts, overpayment/duplicate protection, recovery, reports, and full loan completion.")
