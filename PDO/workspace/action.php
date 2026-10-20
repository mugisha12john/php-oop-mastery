<?php
require_once __DIR__ . '/bootstrap.php';
require_post();
$action = post_string('action');
$actionRoles = [
    'document' => ['SUPER_ADMIN', 'FIRM'], 'application' => ['SUPER_ADMIN', 'MANAGER'],
    'disburse' => ['SUPER_ADMIN', 'ACCOUNTANT'], 'payment' => ['SUPER_ADMIN', 'ACCOUNTANT'],
    'payment_review' => ['SUPER_ADMIN', 'ACCOUNTANT'], 'recovery' => ['SUPER_ADMIN', 'RECOVERY_OFFICER'],
    'user' => ['SUPER_ADMIN'], 'product' => ['SUPER_ADMIN', 'FIRM'],
];
require_roles($actionRoles[$action] ?? []);
$id = (int) post_string('id');
$return = 'workspace/index.php';
try {
    switch ($action) {
        case 'document':
            $return = 'portal/documents.php';
            (new DocumentService(db(), $user))->review($id, post_string('decision'), post_string('notes'));
            $message = t('Document reviewed. Customer verification has been updated.');
            break;
        case 'application':
            $return = 'workspace/application.php?id=' . $id;
            $lending->reviewApplication($id, post_string('decision'), post_string('amount'), post_string('notes'));
            $message = t('Application decision saved.');
            break;
        case 'disburse':
        case 'payment':
            $return = 'workspace/loan.php?id=' . $id;
            consume_nonce($action . '_' . $id);
            $input = [];
            foreach (['amount', 'date', 'method', 'reference'] as $key) {
                $input[$key] = trim(post_string($key));
            }
            if ($action === 'disburse') {
                $lending->disburse($id, $input);
                $message = t('Disbursement recorded. The monthly repayment schedule is ready.');
            } else {
                $lending->postPayment($id, $input);
                $message = t('Repayment posted. The loan balance and schedule have been updated.');
            }
            break;
        case 'payment_review':
            $return = 'workspace/index.php?tab=payments';
            $lending->reviewPayment($id, post_string('decision'), post_string('notes'));
            $message = t('Payment submission reviewed.');
            break;
        case 'recovery':
            $return = 'workspace/loan.php?id=' . $id;
            consume_nonce('recovery_' . $id);
            $input = [];
            foreach (['contact_type', 'notes', 'promised_date', 'followup_date'] as $key) {
                $input[$key] = trim(post_string($key));
            }
            $lending->logRecovery($id, $input);
            $message = t('Borrower contact recorded.');
            break;
        case 'user':
            $return = 'workspace/users.php';
            require_once __DIR__ . '/../app/Lending/AdminService.php';
            $input = [];
            foreach (['id', 'name', 'email', 'role', 'firm_id', 'password', 'active'] as $key) {
                $input[$key] = post_string($key);
            }
            consume_nonce('user_' . $id);
            (new AdminService(db(), $user))->saveUser($input);
            $message = t('User account saved.');
            break;
        case 'product':
            $return = 'workspace/products.php';
            require_once __DIR__ . '/../app/Lending/AdminService.php';
            $input = [];
            foreach (['id', 'name', 'description', 'min', 'max', 'rate', 'term', 'default_term', 'collateral', 'active', 'firm_id'] as $key) {
                $input[$key] = trim(post_string($key));
            }
            consume_nonce('product_' . $id);
            (new AdminService(db(), $user))->saveProduct($input);
            $message = t('Loan product saved. Existing issued loans keep their agreed terms.');
            break;
        default:
            http_response_code(400);
            exit(t('Unknown action.'));
    }
    flash('success', $message);
} catch (OutOfBoundsException $error) {
    http_response_code(404);
    exit(e(t($error->getMessage())));
} catch (DomainException $error) {
    flash('error', $error->getMessage());
} catch (PDOException $error) {
    error_log('Lending action failed: ' . $error->getMessage());
    flash('error', $error->getCode() === '23000' ? t('A duplicate or conflicting record prevented this action. Check the transaction reference, email, or product name and refresh.') : t('The action could not be saved. Please try again.'));
} catch (Throwable $error) {
    error_log('Lending action failed: ' . $error->getMessage());
    flash('error', t('The action could not be saved. Please try again.'));
}
redirect($return);
