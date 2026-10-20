<?php
require_once __DIR__ . '/bootstrap.php';
require_roles(['SUPER_ADMIN','MANAGER','ACCOUNTANT']);
$from = is_string($_GET['from'] ?? null) ? $_GET['from'] : date('Y-m-01');
$to = is_string($_GET['to'] ?? null) ? $_GET['to'] : date('Y-m-d');
$error = null;
try { $report = $lending->report($from, $to); }
catch (DomainException $exception) { $error = $exception->getMessage(); $report = ['collections'=>[], 'disbursed'=>0]; }
$summary = $lending->summary();
$title = t('Lending reports');
require __DIR__ . '/../includes/header.php';
?>
<div class="page-heading"><div><span class="eyebrow"><?= e(t('Lending insights')) ?></span><h1><?= e(t('Follow the money.')) ?></h1><p><?= e(t('Collections, disbursements, and the current portfolio.')) ?></p></div><button class="button secondary no-print" data-print><?= e(t('Print report')) ?></button></div>
<form class="card report-filter no-print" method="GET"><div class="field"><label for="from"><?= e(t('From')) ?></label><input id="from" name="from" type="date" value="<?= e($from) ?>" required></div><div class="field"><label for="to"><?= e(t('To')) ?></label><input id="to" name="to" type="date" value="<?= e($to) ?>" required></div><button class="button"><?= e(t('Update report')) ?></button></form>
<?php if ($error): ?><div class="notice error"><?= e(t($error)) ?></div><?php endif; ?>
<div class="metrics spaced"><div class="card metric"><span class="label"><?= e(t('Collections in period')) ?></span><div class="number compact-number"><?= e(money(array_sum(array_column($report['collections'],'amount')))) ?></div></div><div class="card metric"><span class="label"><?= e(t('Disbursed in period')) ?></span><div class="number compact-number"><?= e(money($report['disbursed'])) ?></div></div><div class="card metric"><span class="label"><?= e(t('Current outstanding portfolio')) ?></span><div class="number compact-number"><?= e(money($summary['outstanding'])) ?></div></div></div>
<section class="card"><div class="section-heading"><h2><?= e(t('Daily collections')) ?></h2><span class="count-pill"><?= e(format_date($from)) ?> — <?= e(format_date($to)) ?></span></div><?php if (!$report['collections']): ?><div class="empty"><p><?= e(t('No confirmed payments in this period.')) ?></p></div><?php else: ?><div class="table-wrap"><table class="data-table"><thead><tr><th><?= e(t('Date')) ?></th><th><?= e(t('Method')) ?></th><th><?= e(t('Transactions')) ?></th><th><?= e(t('Collected')) ?></th></tr></thead><tbody><?php foreach ($report['collections'] as $row): ?><tr><td><?= e(format_date($row['payment_date'])) ?></td><td><?= e(enum_label($row['payment_method'])) ?></td><td><?= e($row['transactions']) ?></td><td class="amount"><?= e(money($row['amount'])) ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
