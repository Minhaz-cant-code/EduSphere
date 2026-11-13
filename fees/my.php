<?php
/**
 * Fee dues & payment history for a student / parent (FR-FEE-4)
 */
require_once __DIR__ . '/../includes/functions.php';
require_role('student', 'parent');

$sid  = my_student_id();
$sum  = fee_summary($sid);
$fees = student_fees($sid);

$st = $pdo->prepare('SELECT p.*, f.fee_type, f.fee_month FROM payments p JOIN fees f ON f.id = p.fee_id WHERE f.student_id = ? ORDER BY p.payment_date DESC, p.id DESC');
$st->execute([$sid]);
$payments = $st->fetchAll();

$page_title = role() === 'parent' ? "Child's fees" : 'My fees';
require __DIR__ . '/../includes/header.php';
?>
<?php page_head($page_title, 'Fees are paid at the institute office. Each payment has a printable receipt.'); ?>

<section class="kpi-strip cols-3" aria-label="Fee summary">
    <div class="kpi">
        <div class="kpi-label"><span class="kpi-icon is-primary"><i class="bi bi-receipt"></i></span> Total charged</div>
        <div class="kpi-value"><?= money_kpi($sum['total']) ?></div>
        <div class="kpi-meta"><?= count($fees) ?> fee entr<?= count($fees) === 1 ? 'y' : 'ies' ?></div>
    </div>
    <div class="kpi">
        <div class="kpi-label"><span class="kpi-icon is-success"><i class="bi bi-check2-circle"></i></span> Paid</div>
        <div class="kpi-value"><?= money_kpi($sum['paid']) ?></div>
        <div class="kpi-meta"><?= count($payments) ?> payment<?= count($payments) === 1 ? '' : 's' ?></div>
    </div>
    <div class="kpi">
        <div class="kpi-label"><span class="kpi-icon <?= $sum['due'] > 0 ? 'is-danger' : 'is-success' ?>"><i class="bi bi-hourglass-split"></i></span> Current due</div>
        <div class="kpi-value"><?= money_kpi($sum['due']) ?></div>
        <div class="kpi-meta"><?= $sum['due'] > 0 ? 'Please pay at the office' : 'Nothing to pay' ?></div>
    </div>
</section>

<div class="card mb-4">
    <div class="card-header"><h2 class="section-title">Fees</h2></div>
    <div class="table-responsive">
        <table class="table table-stack">
            <thead><tr><th>Fee</th><th>Due date</th><th class="num">Amount</th><th class="num">Paid</th><th class="num">Due</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($fees as $f): ?>
                <tr>
                    <td><div class="cell-main"><?= e($f['fee_type']) ?></div><div class="cell-sub"><?= e($f['fee_month'] ? date('F Y', strtotime($f['fee_month'] . '-01')) : $f['description']) ?></div></td>
                    <td data-label="Due date"><?= fmt_date($f['due_date']) ?></td>
                    <td class="num" data-label="Amount"><?= money($f['amount']) ?></td>
                    <td class="num" data-label="Paid"><?= money($f['paid']) ?></td>
                    <td class="num fw-semibold" data-label="Due"><?= money($f['due']) ?></td>
                    <td data-label="Status"><?= status_badge(fee_status($f['amount'], $f['paid'])) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$fees): ?><?= empty_row(6, 'wallet2', 'No fees yet', 'Fees charged by the office will appear here.') ?><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <div class="card-header"><div><h2 class="section-title">Payments &amp; receipts</h2><p class="section-sub">Open a receipt to print or save it</p></div></div>
    <?php if ($payments): ?>
        <div class="list-rows">
            <?php foreach ($payments as $p): ?>
                <a class="list-row" href="<?= url('fees/receipt.php?id=' . $p['id']) ?>">
                    <div class="class-icon" style="width:34px;height:34px;font-size:15px;background:var(--es-success-soft);color:var(--es-success)"><i class="bi bi-receipt"></i></div>
                    <div class="list-row-main">
                        <div class="list-row-title"><?= e($p['receipt_no']) ?></div>
                        <div class="list-row-sub"><?= e($p['fee_type']) ?> fee<?= $p['fee_month'] ? ' · ' . date('M Y', strtotime($p['fee_month'] . '-01')) : '' ?> · <?= e($p['method']) ?></div>
                    </div>
                    <div class="list-row-end">
                        <div class="fw-semibold text-dark tabular"><?= money($p['amount_paid']) ?></div>
                        <div class="list-row-sub"><?= fmt_date($p['payment_date']) ?></div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <?= empty_state('receipt', 'No payments yet', 'Receipts appear here after a payment at the office.') ?>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
