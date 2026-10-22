"""Language defaults, persistence, translated workflows, and safe language selection."""
from html.parser import HTMLParser
import json
import os
from pathlib import Path
import subprocess
import urllib.request


class VisibleText(HTMLParser):
    def __init__(self):
        super().__init__()
        self.parts = []
        self.raw = False

    def handle_starttag(self, tag, attrs):
        if tag in ['script', 'style']:
            self.raw = True

    def handle_endtag(self, tag):
        if tag in ['script', 'style']:
            self.raw = False

    def handle_data(self, data):
        if not self.raw:
            self.parts.append(data)


def check_i18n(Browser, sql, base, prefix, password, browsers, firms, admin):
    def choose(browser, language, page='dashboard.php', return_to=None):
        response = browser.submit('actions/language.php', page, {
            'locale': language, 'return_to': return_to or page,
        })
        assert response[0] == 303, response
        return response

    def page(browser, path, language):
        code, _, body = browser.request(path)
        assert code == 200 and f'<html lang="{language}">' in body, (path, code)
        assert 'app-translations' in body and 'name="locale"' in body
        parser = VisibleText()
        parser.feed(body)
        return ' '.join(parser.parts), body

    anonymous = Browser(base)
    anonymous.client.addheaders = [('Accept-Language', 'fr-FR,fr;q=0.9')]
    text, _ = page(anonymous, 'index.php', 'en')
    assert 'Customer sign in' in text
    assert anonymous.request('actions/language.php')[0] == 405
    assert anonymous.request('actions/language.php', {'locale':'fr','return_to':'index.php'})[0] == 403
    for invalid in ['de','../fr','fr.php','FR']:
        assert anonymous.submit('actions/language.php','index.php',{'locale':invalid,'return_to':'index.php'})[0] == 400
    response = choose(anonymous,'fr','index.php',return_to='index.php?view=welcome')
    assert response[1]['Location'] == '/PDO/index.php?view=welcome'
    cookie = '\n'.join(response[1].get_all('Set-Cookie'))
    assert 'mlms_language=fr' in cookie and 'HttpOnly' in cookie and 'SameSite=Lax' in cookie
    text, _ = page(anonymous,'index.php','fr')
    assert 'Connexion client' in text and 'Customer sign in' not in text
    for target in ['https://example.invalid/','//example.invalid/','../login.php','index.php\r\nX-Test: injected']:
        assert choose(anonymous,'fr','index.php',return_to=target)[1]['Location'] == '/PDO/index.php'
    response = anonymous.submit('auth.php','customerLogin.php',{'action':'customer_login','email':prefix+'lending-customer@example.invalid','password':'incorrect'})
    assert response[0] == 303
    text, _ = page(anonymous,'customerLogin.php','fr')
    assert 'E-mail ou mot de passe incorrect' in text
    anonymous.login(prefix+'lending-customer@example.invalid')
    text, _ = page(anonymous,'dashboard.php','fr')
    assert 'Mes documents' in text
    loan = sql('SELECT l.* FROM loans l JOIN customers c ON c.customer_id=l.customer_id JOIN users u ON u.user_id=c.user_id WHERE u.email=?',[prefix+'lending-customer@example.invalid'])[0]
    payment = sql('SELECT payment_id FROM payments WHERE loan_id=? ORDER BY payment_id LIMIT 1',[loan['loan_id']])[0]
    customer_pages = ['dashboard.php','requestLoan.php','portal/documents.php','portal/password.php',
                      'portal/payments.php?loan_id='+str(loan['loan_id']), 'loan.php?id='+str(loan['application_id']),
                      'portal/receipt.php?id='+str(payment['payment_id'])]
    for language, nav, completed in [('fr','Mes documents','TERMINÉ'),('rw','Inyandiko zanjye','YARANGIYE')]:
        choose(anonymous,language)
        for path in customer_pages:
            text, _ = page(anonymous,path,language)
            assert nav in text, path
        text, body = page(anonymous,'loan.php?id='+str(loan['application_id']),language)
        assert completed in text and 'Business stock' in text
        if language == 'fr':
            assert '10 000,50 RWF' in text
        else:
            assert '10.000,50 RWF' in text and 'Ukwakira' in text
    for browser, paths in [
        (browsers['alpha'],['dashboard.php','workspace/products.php','portal/documents.php','view/customers/listCustomers.php','workspace/index.php?tab=applications']),
        (admin,['dashboard.php','workspace/users.php','workspace/products.php','workspace/reports.php','workspace/audit.php']),
    ]:
        for language in ['fr','rw']:
            choose(browser,language)
            for path in paths:
                page(browser,path,language)
        choose(browser,'en')
    if os.environ.get('RUN_BROWSER_QA') == '1':
        pages = []
        for language in ['fr', 'rw']:
            for name, browser, path in [
                ('customer',anonymous,'dashboard.php'),
                ('apply',anonymous,'requestLoan.php'),
                ('receipt',anonymous,'portal/receipt.php?id='+str(payment['payment_id'])),
                ('products',browsers['alpha'],'workspace/products.php'),
                ('users',admin,'workspace/users.php'),
            ]:
                handler = next(h for h in browser.client.handlers if isinstance(h, urllib.request.HTTPCookieProcessor))
                session = next(cookie.value for cookie in handler.cookiejar if cookie.name == 'PHPSESSID')
                pages.append({'name':name+'-'+language,'session':session,'path':path,'language':language})
        subprocess.run(['node',str(Path(__file__).with_name('browser_check.mjs'))],
                       input=json.dumps({'base':base,'pages':pages}),text=True,check=True,timeout=90)
        choose(browsers['alpha'],'en')
        choose(admin,'en')
    anonymous.submit('auth.php','dashboard.php',{'action':'logout'})
    text, _ = page(anonymous,'customerLogin.php','rw')
    assert 'Kwinjira nk’umukiriya' in text or 'Urubuga rw’umukiriya' in text
    choose(anonymous,'en','customerLogin.php')
    page(anonymous,'index.php','en')
    print('PASS: English default, French/Kinyarwanda screens and validation, translated dates/amounts/statuses, language persistence through login/logout, safe redirects, and unchanged user-entered data.')
