<?php

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/modules/firmModel.php';
$admin = require_role('SUPER_ADMIN');
require_post();

$action = post_string('action');
$firmId = filter_var(post_string('firm_id'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$firmId || !in_array($action, ['approve', 'reject'], true)) {
    http_response_code(400);
    exit('Invalid firm review');
}
try {
    $firms = new FirmModel(db());
    $changed = $firms->review($firmId, $action === 'approve' ? 'APPROVED' : 'REJECTED', (int) $admin['user_id'], post_string('reason'));
    flash($changed ? 'success' : 'error', $changed
        ? ($action === 'approve' ? 'Firm approved. Its account can now manage customers.' : 'Firm registration rejected.')
        : 'Firm not found or already reviewed. Refresh the list.');
} catch (InvalidArgumentException $error) {
    flash('error', $error->getMessage());
} catch (Throwable $error) {
    error_log('Firm review failed: ' . $error->getMessage());
    flash('error', 'Could not save the review. Please try again.');
}
redirect('dashboard.php');
