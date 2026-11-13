<?php
/**
 * Collect a payment against a fee (manual entry - no payment gateway in V1)
 * Activity diagram: Record Fees & Payments -> Validate & Save -> Update Dues & Generate Receipt
 */
require_once __DIR__ . '/../includes/functions.php';
require_role('admin');

$fee_id = get_int('fee_id', (int)post('fee_id'));
$st = $pdo->prepare(
    'SELECT f.*, u.name AS student, s.roll_no, b.name AS batch,
            (SELECT COALESCE(SUM(amount_paid),0) FROM payments p WHERE p.fee_id = f.id) AS paid
       FROM fees f JOIN students s ON s.id = f.student_id JOIN users u ON u.id = s.user_id
       LEFT JOIN batches b ON b.id = s.batch_id WHERE f.id = ?'
);
$st->execute([$fee_id]);
$fee = $st->fetch();
if (!$fee) {
    flash('danger', 'Fee not found.');
    redirect('fees/index.php');
}
$due = round($fee['amount'] - $fee['paid'], 2);

$errors = [];
$amount = $due;
$date   = today();
$method = 'Cash';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $amount = (float)post('amount');
    $date   = post('payment_date');
    $method = post('method');

    if ($amount <= 0) $errors[] = 'Amount must be greater than zero.';
    if ($amount > $due) $errors[] = 'Amount cannot be more than the due amount (' . money($due) . ').';
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || $date > today()) $errors[] = 'Invalid payment date.';
    if (!in_array($method, ['Cash', 'bKash', 'Nagad', 'Rocket', 'Bank', 'Card'], true)) $errors[] = 'Invalid payment method.';

    if (!$errors) {
        $pdo->beginTransaction();
        try {
            $pdo->prepare('INSERT INTO payments (fee_id, amount_paid, payment_date, method, receipt_no, received_by) VALUES (?,?,?,?,?,?)')
                ->execute([$fee_id, $amount, $date, $method, uniqid('TMP'), user_id()]);
            $pid = (int)$pdo->lastInsertId();
            // Receipt number based on date + payment id, e.g. RCPT-20261026-0012
            $receipt = 'RCPT-' . str_replace('-', '', $date) . '-' . str_pad((string)$pid, 4, '0', STR_PAD_LEFT);
            $pdo->prepare('UPDATE payments SET receipt_no = ? WHERE id = ?')->execute([$receipt, $pid]);
            $pdo->commit();
            flash('success', 'Payment saved. Receipt ' . $receipt . ' generated.');
            redirect('fees/receipt.php?id=' . $pid);
        } catch (PDOException $ex) {
            $pdo->rollBack();
            error_log($ex->getMessage());
            $errors[] = 'Could not save the payment.';
        }
    }
}

$page_title = 'Collect payment';
require __DIR__ . '/../includes/header.php';
?>
<?php page_head('Collect payment', 'Record money received. A receipt is created as soon as you save.', '', ['Back to fees', 'fees/index.php?student_id=' . $fee['student_id']]); ?>
<?php form_errors($errors); ?>
<div class="row g-4">
    <div class="col-lg-5 order-lg-2">
        <div class="card">
            <div class="card-header"><h2 class="section-title">Fee summary</h2><?= status_badge(fee_status($fee['amount'], $fee['paid'])) ?></div>
            <div class="card-body pt-3">
                <div class="person mb-3"><?= avatar($fee['student']) ?><div class="min-w-0">
                    <div class="person-name"><?= e($fee['student']) ?></div>
                    <div class="person-sub"><?= e($fee['batch']) ?> · Roll <?= e($fee['roll_no']) ?></div></div></div>
                <dl class="detail-list">
                    <div><dt>Fee</dt><dd><?= e($fee['fee_type']) ?><?= $fee['fee_month'] ? ' · ' . date('F Y', strtotime($fee['fee_month'] . '-01')) : '' ?></dd></div>
                    <?php if ($fee['description']): ?><div><dt>Note</dt><dd><?= e($fee['description']) ?></dd></div><?php endif; ?>
                    <div><dt>Amount</dt><dd class="tabular"><?= money($fee['amount']) ?></dd></div>
                    <div><dt>Already paid</dt><dd class="tabular"><?= money($fee['paid']) ?></dd></div>
                </dl>
            </div>
            <div class="card-footer d-flex justify-content-between align-items-center">
                <span class="fw-medium">Due now</span>
                <span class="receipt-total <?= $due > 0 ? 'text-danger' : 'text-success' ?>"><?= money($due) ?></span>
            </div>
        </div>
    </div>
    <div class="col-lg-7 order-lg-1">
        <?php if ($due <= 0): ?>
            <div class="card"><?= empty_state('check2-circle', 'This fee is fully paid', 'There is nothing left to collect.', '<a class="btn btn-sm btn-light" href="' . url('fees/index.php?student_id=' . $fee['student_id']) . '">Back to fees</a>') ?></div>
        <?php else: ?>
        <form method="post" class="card">
            <?= csrf_field() ?>
            <input type="hidden" name="fee_id" value="<?= $fee_id ?>">
            <div class="form-section">
                <div class="form-section-head"><h2>Payment</h2><p>Mobile banking and bank payments are recorded by hand in Version 1.</p></div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="amount">Amount received<span class="req">*</span></label>
                        <div class="input-group"><span class="input-group-text">৳</span>
                            <input type="number" step="0.01" min="1" max="<?= $due ?>" name="amount" id="amount" class="form-control" value="<?= e($amount) ?>" required></div>
                        <span class="form-hint">Up to <?= money($due) ?>. Enter less for a partial payment.</span>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="payment_date">Payment date</label>
                        <input type="date" name="payment_date" id="payment_date" class="form-control" value="<?= e($date) ?>" max="<?= today() ?>" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label d-block">Method</label>
                        <div class="segmented flex-wrap" role="group" aria-label="Payment method">
                            <?php foreach (['Cash', 'bKash', 'Nagad', 'Rocket', 'Bank', 'Card'] as $m): ?>
                                <input type="radio" class="btn-check" name="method" id="m<?= $m ?>" value="<?= $m ?>" <?= $method === $m ? 'checked' : '' ?>>
                                <label class="btn" for="m<?= $m ?>"><?= $m ?></label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="form-actions">
                <button class="btn btn-success"><i class="bi bi-check-lg"></i> Save &amp; create receipt</button>
                <a href="<?= url('fees/index.php?student_id=' . $fee['student_id']) ?>" class="btn btn-light">Cancel</a>
            </div>
        </form>
        <?php endif; ?>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
