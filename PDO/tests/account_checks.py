"""Customer initial-password and mandatory password-change HTTP checks."""
import os
import json
import subprocess
import urllib.request
from pathlib import Path

DEFAULT_PASSWORD = 'customer123456789'


def sign_in_customer(Browser, sql, base, email, password, security_checks=False):
    client = Browser(base)
    response = client.submit('auth.php', 'customerLogin.php', {
        'action': 'customer_login', 'email': email, 'password': DEFAULT_PASSWORD,
    })
    assert response[0] == 303 and response[1]['Location'] == '/PDO/portal/password.php'
    row = sql('SELECT must_change_password,password_version,password_hash FROM users WHERE email=?', [email])[0]
    assert row['must_change_password'] == 1 and row['password_version'] == 0
    assert row['password_hash'] != DEFAULT_PASSWORD
    for path in ['dashboard.php','requestLoan.php','portal/documents.php','portal/payments.php']:
        code, headers, _ = client.request(path)
        assert code == 303 and headers['Location'] == '/PDO/portal/password.php', path
    stale = None
    if security_checks:
        stale = Browser(base)
        assert stale.submit('auth.php','customerLogin.php',{'action':'customer_login','email':email,'password':DEFAULT_PASSWORD})[1]['Location'] == '/PDO/portal/password.php'
        if os.environ.get('RUN_BROWSER_QA') == '1':
            handler = next(h for h in client.client.handlers if isinstance(h, urllib.request.HTTPCookieProcessor))
            session = next(c.value for c in handler.cookiejar if c.name == 'PHPSESSID')
            pages = [{'name':'customer-first-password','path':'portal/password.php','session':session}]
            subprocess.run(['node',str(Path(__file__).with_name('browser_check.mjs'))],input=json.dumps({'base':base,'pages':pages}),text=True,check=True,timeout=60)
        assert client.request('portal/password.php', {'password':password})[0] == 403
        cases = [
            ({'current_password':'wrong-password','password':password,'password_confirmation':password}, 'current password is incorrect'),
            ({'current_password':DEFAULT_PASSWORD,'password':'short','password_confirmation':'short'}, 'between 12 and 72'),
            ({'current_password':DEFAULT_PASSWORD,'password':password,'password_confirmation':'mismatch'}, 'Passwords do not match'),
            ({'current_password':DEFAULT_PASSWORD,'password':DEFAULT_PASSWORD,'password_confirmation':DEFAULT_PASSWORD}, 'new personal password'),
        ]
        for fields, message in cases:
            response = client.submit('portal/password.php','portal/password.php',fields)
            assert response[0] == 200 and message in response[2]
            assert password not in response[2]
            assert sql('SELECT must_change_password FROM users WHERE email=?',[email])[0]['must_change_password'] == 1
    response = client.submit('portal/password.php','portal/password.php', {
        'current_password':DEFAULT_PASSWORD,'password':password,'password_confirmation':password,
        'user_id':'1','must_change_password':'1','role':'SUPER_ADMIN',
    })
    assert response[0] == 303 and response[1]['Location'] == '/PDO/dashboard.php'
    row = sql('SELECT must_change_password,password_version,password_changed_at FROM users WHERE email=?',[email])[0]
    assert row['must_change_password'] == 0 and row['password_version'] == 1 and row['password_changed_at']
    assert client.request('dashboard.php')[0] == 200
    if security_checks:
        assert stale.request('dashboard.php')[1]['Location'] == '/PDO/login.php'
        rejected = Browser(base)
        response = rejected.submit('auth.php','customerLogin.php',{'action':'customer_login','email':email,'password':DEFAULT_PASSWORD})
        assert response[1]['Location'] == '/PDO/customerLogin.php'
        assert rejected.request('dashboard.php')[0] == 303
        rejected.login(email)
        assert rejected.request('dashboard.php')[0] == 200
        print('PASS: customer default password, mandatory first password change, current-password verification, default reuse prevention, and older-session invalidation.')
    return client
