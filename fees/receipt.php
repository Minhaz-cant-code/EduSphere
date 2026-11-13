<?php
/**
 * Printable money receipt (FR-FEE-3)
 * Admin can open any receipt; a student/parent only their own.
 * Use the browser's "Print" -> "Save as PDF" to download it.
 */
require_once __DIR__ . '/../includes/functions.php';
require_role('admin', 'student', 'parent');

$st = $pdo->prepare(
    'SELECT p.*, f.fee_type, f.fee_month, f.amount, f.description, f.student_id,
            u.name AS student, s.roll_no, b.name AS batch, r.name AS receiver
       FROM payments p
       JOIN fees f ON f.id = p.fee_id
       JOIN students s ON s.id = f.student_id
       JOIN users u ON u.id = s.user_id
       LEFT JOIN batches b ON b.id = s.batch_id
       LEFT JOIN users r ON r.id = p.received_by
      WHERE p.id = ?'
);
$st->execute([get_int('id')]);
$p = $st->fetch();

if (!$p || (role() !== 'admin' && (int)$p['student_id'] !== my_student_id())) {
    flash('danger', 'Receipt not found.');
    redirect('dashboard.php');
}

// Paid so far for this fee (up to and including this receipt)
$st = $pdo->prepare('SELECT COALESCE(SUM(amount_paid),0) FROM payments WHERE fee_id = ? AND id <= ?');
$st->execute([$p['fee_id'], $p['id']]);
$paid_so_far = (float)$st->fetchColumn();
$remaining = $p['amount'] - $paid_so_far;

$page_title = 'Receipt ' . $p['receipt_no'];
require __DIR__ . '/../includes/header.php';
?>
<div class="receipt">
    <div class="d-flex justify-content-between align-items-center mb-3 no-print">
        <a class="page-back mb-0" href="<?= role() === 'admin' ? url('fees/index.php?student_id=' . $p['student_id']) : url('fees/my.php') ?>"><i class="bi bi-arrow-left"></i> Back to fees</a>
        <button onclick="window.print()" class="btn btn-brand"><i class="bi bi-printer"></i> Print or save as PDF</button>
    </div>
    <div class="card">
        <div class="receipt-doc">
            <div class="d-flex flex-wrap justify-content-between gap-3 pb-4 mb-4 border-bottom">
                <div class="d-flex gap-3 align-items-center">
                    <span class="es-brand-mark" style="width:44px;height:44px;font-size:20px;border-radius:12px"><i class="bi bi-mortarboard-fill"></i></span>
                    <div>
                        <div class="h5 mb-0"><?= e(INSTITUTE_NAME) ?></div>
                        <div class="small text-muted"><?= e(INSTITUTE_ADDRESS) ?></div>
                    </div>
                </div>
                <div class="text-sm-end">
                    <div class="small fw-semibold text-muted text-uppercase" style="letter-spacing:.08em">Money receipt</div>
                    <div class="h6 mb-0 tabular"><?= e($p['receipt_no']) ?></div>
                    <div class="small text-muted"><?= fmt_date($p['payment_date']) ?></div>
                </div>
            </div>

            <div class="row g-3 mb-4 small">
                <div class="col-6 col-md-3"><div class="text-muted">Student</div><div class="fw-semibold text-dark"><?= e($p['student']) ?></div></div>
                <div class="col-6 col-md-3"><div class="text-muted">Roll</div><div class="fw-semibold text-dark"><?= e($p['roll_no']) ?></div></div>
                <div class="col-6 col-md-3"><div class="text-muted">Batch</div><div class="fw-semibold text-dark"><?= e($p['batch']) ?></div></div>
                <div class="col-6 col-md-3"><div class="text-muted">Paid by</div><div class="fw-semibold text-dark"><?= e($p['method']) ?></div></div>
            </div>

            <div class="border rounded-3 overflow-hidden">
                <table class="table mb-0">
                    <thead><tr><th>Description</th><th class="num">Fee amount</th><th class="num">Paid now</th></tr></thead>
                    <tbody>
                        <tr>
                            <td><div class="cell-main"><?= e($p['fee_type']) ?> fee<?= $p['fee_month'] ? ' · ' . date('F Y', strtotime($p['fee_month'] . '-01')) : '' ?></div>
                                <?php if ($p['description']): ?><div class="cell-sub"><?= e($p['description']) ?></div><?php endif; ?></td>
                            <td class="num"><?= money($p['amount']) ?></td>
                            <td class="num fw-semibold text-dark"><?= money($p['amount_paid']) ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="d-flex justify-content-end mt-3">
                <div style="min-width:260px">
                    <div class="d-flex justify-content-between py-1"><span class="text-muted">Received</span><span class="receipt-total"><?= money($p['amount_paid']) ?></span></div>
                    <div class="d-flex justify-content-between py-1 border-top"><span class="text-muted">Remaining for this fee</span><span class="fw-semibold <?= $remaining > 0 ? 'text-danger' : 'text-success' ?>"><?= money($remaining) ?></span></div>
                </div>
            </div>

            <div class="d-flex justify-content-between align-items-end mt-5 pt-3">
                <small class="text-muted">Computer-generated receipt · <?= e(APP_NAME) ?></small>
                <div class="text-center">
                    <div style="border-top:1px solid var(--es-text-2);width:190px"></div>
                    <small class="text-muted">Received by <?= e($p['receiver'] ?? 'Office') ?></small>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
