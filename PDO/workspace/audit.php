<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../app/Lending/AdminService.php';
require_role('SUPER_ADMIN');
$page = max(1,(int)($_GET['page'] ?? 1));
$logs = (new AdminService(db(),$user))->auditLogs($page);
$hasNext = count($logs)>30;
$logs = array_slice($logs,0,30);
$title = t('Audit trail');
require __DIR__ . '/../includes/header.php';
?>
<div class="page-heading"><div><span class="eyebrow"><?= e(t('Platform oversight')) ?></span><h1><?= e(t('A record of every step.')) ?></h1><p><?= e(t('Document reviews, lending decisions, money movements, and account changes.')) ?></p></div></div><section class="card"><div class="table-wrap"><table class="data-table"><thead><tr><th><?= e(t('Time')) ?></th><th><?= e(t('Person')) ?></th><th><?= e(t('Action')) ?></th><th><?= e(t('Record')) ?></th><th><?= e(t('Details')) ?></th></tr></thead><tbody><?php foreach ($logs as $log): ?><tr><td><?= e(format_date($log['created_at'], true)) ?></td><td><?= e($log['full_name'] ?? t('System')) ?></td><td><?= e(audit_label($log['action_performed'])) ?></td><td><?= e($log['entity_table']) ?> #<?= e($log['entity_id']) ?></td><td class="preserve-lines"><?= e($log['details'] ?? '—') ?></td></tr><?php endforeach; ?></tbody></table></div><?php if (!$logs): ?><div class="empty"><?= e(t('No audit entries yet.')) ?></div><?php endif; ?><nav class="pagination" aria-label="<?= e(t('Audit pages')) ?>"><?php if ($page>1): ?><a href="?page=<?= e($page-1) ?>"><?= e(t('Previous')) ?></a><?php endif; ?><span><?= e(t('Page')) ?> <?= e($page) ?></span><?php if ($hasNext): ?><a href="?page=<?= e($page+1) ?>"><?= e(t('Next')) ?></a><?php endif; ?></nav></section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
