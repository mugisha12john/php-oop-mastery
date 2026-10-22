<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../app/Lending/DocumentService.php';
$user = require_roles(['CUSTOMER', 'FIRM', 'MANAGER', 'SUPER_ADMIN']);
$service = new DocumentService(db(), $user);
$customerId = (int) ($_GET['customer_id'] ?? 0);
$isCustomer = $user['role_name'] === 'CUSTOMER';
$canReview = in_array($user['role_name'], ['FIRM', 'SUPER_ADMIN'], true);
try {
    if (isset($_GET['download'])) {
        $doc = $service->download((int) $_GET['download']);
        header('Content-Type: ' . $doc['mime_type']);
        header('Content-Disposition: attachment; filename="document-' . (int) $doc['document_id'] . '.' . ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png'][$doc['mime_type']] . '"');
        header('Content-Length: ' . filesize(DocumentService::storage() . '/' . $doc['file_path']));
        readfile(DocumentService::storage() . '/' . $doc['file_path']);
        exit;
    }
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        require_post();
        require_role('CUSTOMER');
        consume_nonce('document_upload');
        $service->upload(post_string('document_type'), $_FILES['document'] ?? []);
        flash('success', t('Your document was uploaded. Your firm will review it.'));
        redirect('portal/documents.php');
    }
    $pending = !$isCustomer && ($_GET['filter'] ?? '') !== 'all' && !$customerId;
    $documents = $service->documents($customerId > 0 ? $customerId : null, $pending);
    $profile = null;
    if ($isCustomer) {
        require_once __DIR__ . '/../app/Customers/CustomerPortalService.php';
        $profile = (new CustomerPortalService(db(), (int) $user['user_id']))->profile();
    } elseif ($customerId > 0) {
        $profile = $service->customer($customerId);
    }
} catch (OutOfBoundsException $error) {
    http_response_code(404);
    exit(e(t($error->getMessage())));
} catch (DomainException $error) {
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        flash('error', $error->getMessage());
        redirect('portal/documents.php');
    }
    http_response_code(403);
    exit(e(t($error->getMessage())));
} catch (Throwable $error) {
    error_log('Document operation failed: ' . $error->getMessage());
    flash('error', t('Your document could not be processed. Please try again or contact your firm.'));
    redirect('dashboard.php');
}
$title = $isCustomer ? t('My documents') : t('Document reviews');
require __DIR__ . '/../includes/header.php';
?>
<div class="page-heading"><div><span class="eyebrow"><?= $isCustomer ? t('Your verification') : t('Customer verification') ?></span><h1><?= e(t($title)) ?></h1><p><?= $isCustomer ? t('A few documents. One step closer to your plans.') : t('Review the latest customer documents and keep verification moving.') ?></p></div><?php if ($profile): ?><?= status_badge($profile['kyc_status']) ?><?php endif; ?></div>
<?php if ($isCustomer): ?>
<div class="verification-steps">
    <?php $latest = []; foreach ($documents as $doc) { if ($doc['is_latest']) { $latest[$doc['document_type']] = $doc; } } ?>
    <?php foreach (['NATIONAL_ID', 'PROOF_OF_INCOME', 'OTHER'] as $n => $type): ?><div class="card verification-step"><span class="step-number"><?= e('0' . ($n + 1)) ?></span><div><h3><?= e(t(DocumentService::TYPES[$type])) ?></h3><p class="muted"><?= $type === 'OTHER' ? t('For collateral or additional information') : t('Required to apply for a loan') ?></p><?= isset($latest[$type]) ? status_badge($latest[$type]['review_status']) : status_badge('NOT_UPLOADED') ?></div></div><?php endforeach; ?>
</div>
<div class="portal-grid">
<section class="card"><div class="section-heading"><h2><?= e(t('Upload a document')) ?></h2><span class="count-pill"><?= e(t('PDF · JPG · PNG')) ?></span></div>
<?php if (!$profile || $profile['firm_status'] !== 'APPROVED'): ?><p><?= e(t('Contact your firm to link your customer profile to an approved firm before uploading documents.')) ?></p><?php else: ?>
<form method="POST" enctype="multipart/form-data" action="<?= e(app_url('portal/documents.php')) ?>">
    <?= csrf_field() ?><?= form_nonce('document_upload') ?><input type="hidden" name="MAX_FILE_SIZE" value="5242880">
    <div class="field"><label for="document-type"><?= e(t('Document type')) ?></label><select id="document-type" name="document_type" required><?php foreach (DocumentService::TYPES as $key => $label): ?><option value="<?= e($key) ?>"><?= e(t($label)) ?></option><?php endforeach; ?></select></div>
    <div class="upload-box"><span class="upload-symbol" aria-hidden="true">↥</span><label for="document-file"><?= e(t('Choose your document')) ?></label><p class="muted"><?= e(t('A clear, readable copy. Maximum 5 MB.')) ?></p><input id="document-file" name="document" type="file" accept="application/pdf,image/jpeg,image/png" required></div>
    <p class="help"><?= e(t('Uploading a new version sends that document back for review. Your firm must approve your latest ID and income document before you can apply.')) ?></p>
    <button class="button" type="submit"><?= e(t('Upload for review ↗')) ?></button>
</form><?php endif; ?></section><aside class="card dark-card"><span class="eyebrow"><?= e(t('Good to know')) ?></span><h2><?= e(t('Your documents,')) ?><br><?= e(t('in one safe place.')) ?></h2><p><?= e(t('Your firm reviews your identity and income. Managers can consult your documents when reviewing your application.')) ?></p><p><?= e(t('Have a rejected document? Read the review note below and upload a new copy.')) ?></p><a class="button" href="<?= e(app_url('requestLoan.php')) ?>"><?= e(t('Explore loans →')) ?></a></aside>
</div>
<?php else: ?><nav class="tabs" aria-label="<?= e(t('Document filter')) ?>"><a class="<?= $pending ? 'active' : '' ?>" href="<?= e(app_url('portal/documents.php')) ?>"><?= e(t('Awaiting review')) ?></a><a class="<?= !$pending ? 'active' : '' ?>" href="<?= e(app_url('portal/documents.php?filter=all')) ?>"><?= e(t('All documents')) ?></a></nav><?php endif; ?>
<section class="card spaced"><div class="section-heading"><h2><?= $isCustomer ? t('Document history') : e($profile['full_name'] ?? t('Customer documents')) ?></h2><span class="count-pill"><?= e(t('Latest 200 documents')) ?></span></div>
<?php if (!$documents): ?><div class="empty"><span class="empty-symbol">↥</span><h3><?= $isCustomer ? t('Your documents belong here.') : t('No documents in this view.') ?></h3><p><?= $isCustomer ? t('Upload your ID and proof of income to get started.') : t('Customer uploads will appear here for review.') ?></p></div><?php endif; ?>
<div class="document-list"><?php foreach ($documents as $doc): ?><article class="document-row"><div class="file-icon" aria-hidden="true"><?= $doc['mime_type'] === 'application/pdf' ? 'PDF' : 'IMG' ?></div><div class="document-info"><strong><?= e(t(DocumentService::TYPES[$doc['document_type']])) ?></strong><span class="cell-note"><?= e($doc['original_name']) ?> · <?= e(round($doc['file_size_bytes'] / 1024)) ?> <?= e(t('KB ·')) ?> <?= e(format_date($doc['uploaded_at'])) ?></span><?php if (!$isCustomer): ?><span class="cell-note"><?= e($doc['full_name']) ?></span><?php endif; ?><?php if ($doc['review_notes']): ?><p class="review-note preserve-lines"><?= e($doc['review_notes']) ?></p><?php endif; ?></div><div class="document-actions"><?= $doc['is_latest'] ? status_badge($doc['review_status']) : status_badge('REPLACED') ?><a class="button secondary small" href="<?= e(app_url('portal/documents.php?download=' . $doc['document_id'])) ?>"><?= e(t('Download')) ?></a></div>
<?php if ($canReview && $doc['is_latest'] && $doc['review_status'] === 'PENDING'): ?><form class="document-review" method="POST" action="<?= e(app_url('workspace/action.php')) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="document"><input type="hidden" name="id" value="<?= e($doc['document_id']) ?>"><div class="field"><label for="notes-<?= e($doc['document_id']) ?>"><?= e(t('Review note (required to reject)')) ?></label><input id="notes-<?= e($doc['document_id']) ?>" name="notes" maxlength="1000" placeholder="<?= e(t('Explain any document issue')) ?>"></div><div class="review-actions"><button class="button small" name="decision" value="APPROVED"><?= e(t('Approve document')) ?></button><button class="button danger small" name="decision" value="REJECTED"><?= e(t('Reject')) ?></button></div></form><?php endif; ?>
</article><?php endforeach; ?></div></section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
