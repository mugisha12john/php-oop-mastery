"""Firm-owned product CRUD, catalog isolation, and automatic-login rollback checks."""
import re
import secrets


def check_products(Browser, sql, base, prefix, password, browsers, firms, admin):
    def save(browser, **changes):
        product_id = changes.get('id', '')
        page = 'workspace/products.php' + ('?edit=' + str(product_id) if product_id else '')
        body = browser.request(page)[2]
        token = re.search(r'name="form_nonce" value="([a-f0-9]+)"', body)[1]
        fields = dict(action='product', id=product_id, name=prefix+'Shared product', description='Firm lending',
                      min='100', max='1000000', rate='10', default_term='3', term='12', collateral='0', active='1', form_nonce=token)
        fields.update(changes)
        return browser.submit('workspace/action.php', page, fields)

    alpha, beta = browsers['alpha'], browsers['beta']
    # Each firm may use the same product name; submitted ownership cannot change the actual owner.
    for name, browser in [('alpha',alpha),('beta',beta)]:
        other = 'beta' if name == 'alpha' else 'alpha'
        assert save(browser, description=prefix+name+'-private-product', firm_id=firms[other]['firm_id'])[0] == 303
    rows = sql('SELECT * FROM loan_products WHERE product_name=? ORDER BY firm_id',[prefix+'Shared product'])
    assert len(rows) == 2
    owned = {r['firm_id']:r for r in rows}
    a = owned[firms['alpha']['firm_id']]
    b = owned[firms['beta']['firm_id']]
    assert a['description'] == prefix+'alpha-private-product'
    assert b['description'] == prefix+'beta-private-product'
    assert alpha.request('workspace/products.php?edit='+str(b['product_id']))[0] == 404
    assert beta.request('workspace/products.php?edit='+str(a['product_id']))[0] == 404
    assert prefix+'beta-private-product' not in alpha.request('workspace/products.php')[2]
    clients = {}
    for name in ['alpha','beta']:
        clients[name] = Browser(base)
        clients[name].login(prefix+name+'-customer@example.invalid')
    assert prefix+'alpha-private-product' in clients['alpha'].request('requestLoan.php')[2]
    assert prefix+'beta-private-product' not in clients['alpha'].request('requestLoan.php')[2]
    assert prefix+'alpha-private-product' not in clients['beta'].request('dashboard.php')[2]
    assert prefix+'beta-private-product' in clients['beta'].request('requestLoan.php')[2]
    # Duplicate names within the same firm fail without creating an extra record.
    save(alpha)
    assert len(sql('SELECT product_id FROM loan_products WHERE firm_id=? AND product_name=?',[firms['alpha']['firm_id'],prefix+'Shared product'])) == 1
    save(alpha,id=a['product_id'],name=a['product_name'],description='Updated alpha product',firm_id=firms['beta']['firm_id'])
    assert sql('SELECT firm_id FROM loan_products WHERE product_id=?',[a['product_id']])[0]['firm_id'] == firms['alpha']['firm_id']
    # An unassigned legacy product is invisible until an administrator assigns its firm.
    legacy_name = prefix+'Unassigned product'
    sql('INSERT INTO loan_products (product_name,min_amount_rwf,max_amount_rwf,interest_rate_pct,default_term_months,max_term_months) VALUES (?,100,1000000,10,3,12)',[legacy_name])
    legacy = sql('SELECT product_id FROM loan_products WHERE product_name=?',[legacy_name])[0]['product_id']
    assert legacy_name not in clients['alpha'].request('requestLoan.php')[2]
    assert legacy_name not in clients['beta'].request('requestLoan.php')[2]
    assert save(admin,id=legacy,name=legacy_name,firm_id=firms['alpha']['firm_id'])[0] == 303
    assert legacy_name in clients['alpha'].request('requestLoan.php')[2]
    assert legacy_name not in clients['beta'].request('requestLoan.php')[2]
    # Inactive products cannot be submitted even by a verified, otherwise eligible customer.
    save(alpha,id=a['product_id'],name=a['product_name'],active='0')
    body = clients['alpha'].request('requestLoan.php')[2]
    assert 'Updated alpha product' not in body
    request_token = re.search(r'name="request_token" value="([a-f0-9]+)"',body)[1]
    before = len(sql('SELECT application_id FROM loan_applications WHERE customer_id=(SELECT customer_id FROM customers WHERE user_id=(SELECT user_id FROM users WHERE email=?))',[prefix+'alpha-customer@example.invalid']))
    clients['alpha'].submit('loan.php','requestLoan.php',{'request_token':request_token,'product_id':a['product_id'],'amount_requested_rwf':'500','term_months':'3','purpose':'Inactive product test'})
    after = len(sql('SELECT application_id FROM loan_applications WHERE customer_id=(SELECT customer_id FROM customers WHERE user_id=(SELECT user_id FROM users WHERE email=?))',[prefix+'alpha-customer@example.invalid']))
    assert before == after
    # Registering with a conflicting login email must roll back the customer profile as well.
    nid = '6'+str(secrets.randbelow(10**15)).zfill(15)
    alpha.submit('customer.php','view/customers/registerCustomer.php',{
        'action':'create','national_id_number':nid,'full_name':'Rollback customer','phone_number':'+250788123456',
        'email':firms['alpha']['email'],'physical_address':'Kigali','monthly_income_rwf':'500000',
    })
    assert not sql('SELECT customer_id FROM customers WHERE national_id_number=?',[nid])
    assert 'already has an account' in alpha.request('view/customers/registerCustomer.php')[2]
    print('PASS: firm-owned product creation/editing, same names across firms, customer catalog isolation, legacy assignment, inactive-product rejection, and automatic customer-account rollback.')
