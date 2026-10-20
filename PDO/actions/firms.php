<?php

require_once dirname(__DIR__, 1) . '/includes/bootstrap.php';
require_once dirname(__DIR__, 1) . '/app/Firms/FirmModel.php';
$admin = require_role('SUPER_ADMIN');
require_post();

$action = post_string('action');
$firmId = filter_var(post_string('firm_id'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$firmId || !in_array($action, ['approve', 'reject'], true)) {
    http_response_code(400);
    exit(t('Invalid firm review'));
}
try {
    $firms = new FirmModel(db());
    $changed = $firms->review($firmId, $action === 'approve' ? 'APPROVED' : 'REJECTED', (int) $admin['user_id'], post_string('reason'));
    flash($changed ? 'success' : 'error', $changed
        ? ($action === 'approve' ? t('Firm approved. Its account can now manage customers.') : t('Firm registration rejected.'))
        : t('Firm not found or already reviewed. Refresh the list.'));
} catch (InvalidArgumentException $error) {
    flash('error', $error->getMessage());
} catch (Throwable $error) {
    error_log('Firm review failed: ' . $error->getMessage());
    flash('error', t('Could not save the review. Please try again.'));
}
redirect('dashboard.php');
