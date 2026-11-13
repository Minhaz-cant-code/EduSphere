<?php
/**
 * Fee list & due list (FR-FEE-1, FR-FEE-2) - Admin
 * Paid and due amounts are calculated from the payments table.
 */
require_once __DIR__ . '/../includes/functions.php';
require_role('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action') === 'delete') {
    verify_csrf();
    $pdo->prepare('DELETE FROM fees WHERE id = ?')->execute([(int)post('id')]);
    flash('success', 'Fee entry and its payments deleted.');
    redirect('fees/index.php?' . http_build_query(array_filter([
        'student_id' => post('student_id'),
    ])));
}

$batch_id   = get_int('batch_id');
$student_id = get_int('student_id');
$status     = $_GET['status'] ?? '';
$type       = $_GET['type'] ?? '';
$month      = $_GET['month'] ?? '';

$sql = 'SELECT f.*, u.name AS student, s.roll_no, b.name AS batch,
               (SELECT COALESCE(SUM(p.amount_paid),0) FROM payments p WHERE p.fee_id = f.id) AS paid
          FROM fees f
          JOIN students s ON s.id = f.student_id
          JOIN users u ON u.id = s.user_id
          LEFT JOIN batches b ON b.id = s.batch_id
         WHERE 1 = 1';
$params = [];
if ($batch_id)   { $sql .= ' AND s.batch_id = ?';  $params[] = $batch_id; }
if ($student_id) { $sql .= ' AND f.student_id = ?'; $params[] = $student_id; }
if (in_array($type, ['Monthly', 'Admission', 'Exam', 'Fine'], true)) { $sql .= ' AND f.fee_type = ?'; $params[] = $type; }
if (preg_match('/^\d{4}-\d{2}$/', $month)) { $sql .= ' AND f.fee_month = ?'; $params[] = $month; }
$sql .= ' ORDER BY f.due_date DESC, f.id DESC';
$st = $pdo->prepare($sql);
$st->execute($params);
$fees = $st->fetchAll();

// Status filter is applied in PHP because it depends on the calculated paid amount
if ($status) {
    $fees = array_values(array_filter($fees, function ($f) use ($status) {
        $s = fee_status($f['amount'], $f['paid']);
        return $status === 'due' ? $s !== 'Paid' : $s === $status;
    }));
}
$sum_amount = array_sum(array_column($fees, 'amount'));
$sum_paid   = array_sum(array_column($fees, 'paid'));

$batches = $pdo->query('SELECT id, name FROM batches ORDER BY name')->fetchAll();

$student_name = '';
$payments = [];
if ($student_id) {
    $st = $pdo->prepare('SELECT u.name FROM students s JOIN users u ON u.id = s.user_id WHERE s.id = ?');
    $st->execute([$student_id]);
    $student_name = $st->fetchColumn();
    $st = $pdo->prepare('SELECT p.*, f.fee_type, f.fee_month FROM payments p JOIN fees f ON f.id = p.fee_id WHERE f.student_id = ? ORDER BY p.payment_date DESC');
    $st->execute([$student_id]);
    $payments = $st->fetchAll();
}

$page_title = 'Fees';
require __DIR__ . '/../includes/header.php';
$filtered = $batch_id || $type || $status || $month;
$actions = '<a href="' . url('fees/index.php?status=due') . '" class="btn btn-light"><i class="bi bi-hourglass-split"></i> Due list</a>'
    . '<a href="' . url('fees/add.php' . ($student_id ? '?student_id=' . $student_id : '')) . '" class="btn btn-brand"><i class="bi bi-plus-lg"></i> Add fee</a>';
?>
<?php if ($student_id): ?>
    <?php page_head('Fees · ' . $student_name, 'Every fee charged to this student and the payments received.', $actions, ['All fees', 'fees/index.php']); ?>
<?php else: ?>
    <?php page_head('Fees & payments', 'Charge fees, collect payments and track what is still due.', $actions); ?>
<?php endif; ?>

<section class="kpi-strip cols-3" aria-label="Totals for this list">
    <div class="kpi">
        <div class="kpi-label"><span class="kpi-icon is-primary"><i class="bi bi-receipt"></i></span> Charged</div>
        <div class="kpi-value"><?= money_kpi($sum_amount) ?></div>
        <div class="kpi-meta"><strong><?= count($fees) ?></strong> fee entr<?= count($fees) === 1 ? 'y' : 'ies' ?><?= $filtered ? ' in this view' : '' ?></div>
    </div>
    <div class="kpi">
        <div class="kpi-label"><span class="kpi-icon is-success"><i class="bi bi-check2-circle"></i></span> Collected</div>
        <div class="kpi-value"><?= money_kpi($sum_paid) ?></div>
        <div class="kpi-meta"><?= $sum_amount > 0 ? '<strong>' . round($sum_paid / $sum_amount * 100) . '%</strong> of charged amount' : 'Nothing charged yet' ?></div>
    </div>
    <div class="kpi">
        <div class="kpi-label"><span class="kpi-icon is-danger"><i class="bi bi-hourglass-split"></i></span> Still due</div>
        <div class="kpi-value"><?= money_kpi($sum_amount - $sum_paid) ?></div>
        <div class="kpi-meta"><a href="<?= url('fees/index.php?status=due' . ($student_id ? '&student_id=' . $student_id : '')) ?>">Show unpaid fees</a></div>
    </div>
</section>

<form class="filter-bar filter-form">
    <?php if ($student_id): ?><input type="hidden" name="student_id" value="<?= $student_id ?>"><?php endif; ?>
    <?php if (!$student_id): ?>
    <div class="grow"><label class="form-label" for="batch_id">Batch</label>
        <select name="batch_id" id="batch_id" class="form-select">
            <option value="">All batches</option>
            <?php foreach ($batches as $b): ?>
                <option value="<?= $b['id'] ?>" <?= $batch_id == $b['id'] ? 'selected' : '' ?>><?= e($b['name']) ?></option>
            <?php endforeach; ?>
        </select></div>
    <?php endif; ?>
    <div class="fixed" style="flex-basis:150px"><label class="form-label" for="type">Type</label>
        <select name="type" id="type" class="form-select">
            <option value="">All types</option>
            <?php foreach (['Monthly', 'Admission', 'Exam', 'Fine'] as $t): ?>
                <option <?= $type === $t ? 'selected' : '' ?>><?= $t ?></option>
            <?php endforeach; ?>
        </select></div>
    <div class="fixed"><label class="form-label" for="status">Status</label>
        <select name="status" id="status" class="form-select">
            <option value="">Any status</option>
            <?php foreach (['due' => 'Due (unpaid + partial)', 'Unpaid' => 'Unpaid', 'Partial' => 'Partial', 'Paid' => 'Paid'] as $k => $v): ?>
                <option value="<?= $k ?>" <?= $status === $k ? 'selected' : '' ?>><?= $v ?></option>
            <?php endforeach; ?>
        </select></div>
    <div class="fixed" style="flex-basis:170px"><label class="form-label" for="month">Fee month</label>
        <input type="month" name="month" id="month" class="form-control" value="<?= e($month) ?>"></div>
    <button class="btn btn-light"><i class="bi bi-funnel"></i> Apply</button>
    <?php if ($filtered): ?><a class="btn btn-ghost" href="<?= url('fees/index.php' . ($student_id ? '?student_id=' . $student_id : '')) ?>">Clear</a><?php endif; ?>
</form>

<div class="card mb-4">
    <div class="table-toolbar">
        <span class="table-count"><strong><?= count($fees) ?></strong> fee entr<?= count($fees) === 1 ? 'y' : 'ies' ?></span>
        <button class="btn btn-sm btn-light no-print" data-export-csv="#feeTable" data-filename="fees.csv"><i class="bi bi-filetype-csv"></i> Export CSV</button>
    </div>
    <div class="table-responsive">
        <table class="table table-hover table-stack" id="feeTable">
            <thead><tr><?php if (!$student_id): ?><th>Student</th><?php endif; ?><th>Fee</th><th>Due date</th><th class="num">Amount</th><th class="num">Paid</th><th class="num">Due</th><th>Status</th><th class="text-end no-export">Actions</th></tr></thead>
            <tbody>
            <?php foreach ($fees as $f): $due = $f['amount'] - $f['paid']; $fs = fee_status($f['amount'], $f['paid']);
                $overdue = $fs !== 'Paid' && $f['due_date'] && $f['due_date'] < today(); ?>
                <tr>
                    <?php if (!$student_id): ?>
                    <td><div class="person"><?= avatar($f['student'], null, 'sm') ?><div class="min-w-0">
                        <a class="person-name" href="?student_id=<?= $f['student_id'] ?>"><?= e($f['student']) ?></a>
                        <div class="person-sub"><?= e($f['batch']) ?> · <?= e($f['roll_no']) ?></div></div></div></td>
                    <?php endif; ?>
                    <td data-label="Fee"><div class="cell-main"><?= e($f['fee_type']) ?></div>
                        <div class="cell-sub"><?= e($f['fee_month'] ? date('F Y', strtotime($f['fee_month'] . '-01')) : $f['description']) ?></div></td>
                    <td data-label="Due date" class="<?= $overdue ? 'text-danger fw-medium' : '' ?>"><?= fmt_date($f['due_date']) ?><?= $overdue ? ' <i class="bi bi-exclamation-circle" title="Overdue"></i>' : '' ?></td>
                    <td class="num" data-label="Amount"><?= money($f['amount']) ?></td>
                    <td class="num" data-label="Paid"><?= money($f['paid']) ?></td>
                    <td class="num fw-semibold <?= $due > 0 ? 'text-dark' : 'text-muted' ?>" data-label="Due"><?= money($due) ?></td>
                    <td data-label="Status"><?= status_badge($fs) ?></td>
                    <td class="text-end no-export row-actions-cell">
                        <div class="row-actions">
                            <?php if ($due > 0): ?>
                                <a href="<?= url('fees/collect.php?fee_id=' . $f['id']) ?>" class="btn btn-sm btn-success"><i class="bi bi-cash"></i> Collect</a>
                            <?php endif; ?>
                            <form method="post">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= $f['id'] ?>">
                                <input type="hidden" name="student_id" value="<?= $student_id ?: '' ?>">
                                <button name="action" value="delete" class="btn btn-sm btn-ghost btn-icon is-danger" title="Delete fee" aria-label="Delete fee"
                                        data-confirm-title="Delete this fee?" data-confirm="The fee and every payment recorded against it will be removed."><i class="bi bi-trash"></i></button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$fees): ?>
                <?= $filtered
                    ? empty_row(8, 'search', 'No fees match these filters', 'Try another status or month.')
                    : empty_row(8, 'wallet2', 'No fees charged yet', 'Charge a monthly fee to a whole batch in one step.', '<a class="btn btn-sm btn-brand" href="' . url('fees/add.php') . '"><i class="bi bi-plus-lg"></i> Add fee</a>') ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($student_id): ?>
<div class="card">
    <div class="card-header"><div><h2 class="section-title">Payment history</h2><p class="section-sub">Open a receipt to print it again</p></div></div>
    <div class="table-responsive">
        <table class="table table-stack">
            <thead><tr><th>Receipt</th><th>Date</th><th>For</th><th>Method</th><th class="num">Amount</th></tr></thead>
            <tbody>
            <?php foreach ($payments as $p): ?>
                <tr>
                    <td><a class="cell-main" href="<?= url('fees/receipt.php?id=' . $p['id']) ?>"><i class="bi bi-receipt me-1"></i><?= e($p['receipt_no']) ?></a></td>
                    <td data-label="Date"><?= fmt_date($p['payment_date']) ?></td>
                    <td data-label="For"><?= e($p['fee_type']) ?> <?= $p['fee_month'] ? '· ' . date('M Y', strtotime($p['fee_month'] . '-01')) : '' ?></td>
                    <td data-label="Method"><span class="badge text-bg-light"><?= e($p['method']) ?></span></td>
                    <td class="num fw-semibold" data-label="Amount"><?= money($p['amount_paid']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$payments): ?><?= empty_row(5, 'receipt', 'No payments yet') ?><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
