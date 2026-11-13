<?php
/**
 * Record a fee (monthly / admission / exam / fine) - FR-FEE-1
 * Can be added for one student or for every active student of a batch.
 */
require_once __DIR__ . '/../includes/functions.php';
require_role('admin');

$f = [
    'target' => get_int('student_id') ? 'student' : 'batch',
    'student_id' => get_int('student_id'), 'batch_id' => '',
    'fee_type' => 'Monthly', 'amount' => '', 'fee_month' => date('Y-m'),
    'due_date' => date('Y-m-10'), 'description' => '',
];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    foreach (array_keys($f) as $k) $f[$k] = post($k);

    if (!in_array($f['fee_type'], ['Monthly', 'Admission', 'Exam', 'Fine'], true)) $errors[] = 'Choose a fee type.';
    if (!is_numeric($f['amount']) || $f['amount'] <= 0) $errors[] = 'Amount must be greater than zero.';
    if ($f['fee_type'] === 'Monthly' && !preg_match('/^\d{4}-\d{2}$/', $f['fee_month'])) $errors[] = 'Select the month for a monthly fee.';
    if ($f['fee_type'] !== 'Monthly') $f['fee_month'] = '';

    // Which students?
    $student_ids = [];
    if ($f['target'] === 'student') {
        if ((int)$f['student_id']) $student_ids = [(int)$f['student_id']];
        else $errors[] = 'Select a student.';
    } else {
        $st = $pdo->prepare("SELECT s.id FROM students s JOIN users u ON u.id = s.user_id WHERE s.batch_id = ? AND u.status = 'active'");
        $st->execute([(int)$f['batch_id']]);
        $student_ids = $st->fetchAll(PDO::FETCH_COLUMN);
        if (!$student_ids) $errors[] = 'The selected batch has no active students.';
    }

    if (!$errors) {
        $dup = $pdo->prepare("SELECT COUNT(*) FROM fees WHERE student_id = ? AND fee_type = 'Monthly' AND fee_month = ?");
        $ins = $pdo->prepare('INSERT INTO fees (student_id, fee_type, amount, fee_month, due_date, description) VALUES (?,?,?,?,?,?)');
        $added = $skipped = 0;
        foreach ($student_ids as $sid) {
            // Do not charge the same monthly fee twice
            if ($f['fee_type'] === 'Monthly') {
                $dup->execute([$sid, $f['fee_month']]);
                if ($dup->fetchColumn() > 0) { $skipped++; continue; }
            }
            $ins->execute([$sid, $f['fee_type'], $f['amount'], $f['fee_month'] ?: null, $f['due_date'] ?: null,
                           $f['description'] ?: ($f['fee_type'] . ' fee' . ($f['fee_month'] ? ' ' . date('F Y', strtotime($f['fee_month'] . '-01')) : ''))]);
            $added++;
        }
        flash('success', "$added fee entr" . ($added === 1 ? 'y' : 'ies') . ' added.' . ($skipped ? " $skipped skipped (already charged for that month)." : ''));
        redirect('fees/index.php' . ($f['target'] === 'student' ? '?student_id=' . (int)$f['student_id'] : '?batch_id=' . (int)$f['batch_id']));
    }
}

$students = $pdo->query(
    "SELECT s.id, u.name, s.roll_no, b.name AS batch FROM students s JOIN users u ON u.id = s.user_id
       LEFT JOIN batches b ON b.id = s.batch_id WHERE u.status = 'active' ORDER BY b.name, s.roll_no"
)->fetchAll();
$batches = $pdo->query('SELECT id, name FROM batches ORDER BY name')->fetchAll();

$page_title = 'Add fee';
require __DIR__ . '/../includes/header.php';
?>
<?php page_head('Add fee', 'Charge a fee to one student, or to every active student in a batch at once.', '', ['Fees & payments', 'fees/index.php']); ?>
<?php form_errors($errors); ?>

<form method="post" class="card form-narrow">
    <?= csrf_field() ?>
    <div class="form-section">
        <div class="form-section-head"><h2>Who pays</h2></div>
        <div class="segmented mb-3" role="group" aria-label="Charge to">
            <input type="radio" class="btn-check" name="target" id="tBatch" value="batch" <?= $f['target'] === 'batch' ? 'checked' : '' ?>>
            <label class="btn" for="tBatch"><i class="bi bi-collection"></i> Whole batch</label>
            <input type="radio" class="btn-check" name="target" id="tStudent" value="student" <?= $f['target'] === 'student' ? 'checked' : '' ?>>
            <label class="btn" for="tStudent"><i class="bi bi-person"></i> One student</label>
        </div>
        <div id="batchBox">
            <label class="form-label" for="batch_id">Batch</label>
            <select name="batch_id" id="batch_id" class="form-select">
                <?php foreach ($batches as $b): ?>
                    <option value="<?= $b['id'] ?>" <?= $f['batch_id'] == $b['id'] ? 'selected' : '' ?>><?= e($b['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <span class="form-hint">Every active student in the batch gets this fee. Monthly fees already charged for the same month are skipped.</span>
        </div>
        <div id="studentBox">
            <label class="form-label" for="student_id">Student</label>
            <select name="student_id" id="student_id" class="form-select">
                <option value="">Select student</option>
                <?php foreach ($students as $s): ?>
                    <option value="<?= $s['id'] ?>" <?= $f['student_id'] == $s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?> (<?= e($s['roll_no']) ?>, <?= e($s['batch']) ?>)</option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    <div class="form-section">
        <div class="form-section-head"><h2>Fee details</h2></div>
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label" for="feeType">Fee type</label>
                <select name="fee_type" id="feeType" class="form-select">
                    <?php foreach (['Monthly', 'Admission', 'Exam', 'Fine'] as $t): ?>
                        <option <?= $f['fee_type'] === $t ? 'selected' : '' ?>><?= $t ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="amount">Amount<span class="req">*</span></label>
                <div class="input-group"><span class="input-group-text">৳</span>
                    <input type="number" step="0.01" min="1" name="amount" id="amount" class="form-control" value="<?= e($f['amount']) ?>" required></div>
            </div>
            <div class="col-md-4" id="monthBox">
                <label class="form-label" for="fee_month">For month</label>
                <input type="month" name="fee_month" id="fee_month" class="form-control" value="<?= e($f['fee_month']) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label" for="due_date">Due date</label>
                <input type="date" name="due_date" id="due_date" class="form-control" value="<?= e($f['due_date']) ?>">
            </div>
            <div class="col-md-8">
                <label class="form-label" for="description">Note</label>
                <input name="description" id="description" class="form-control" value="<?= e($f['description']) ?>" placeholder="Optional, e.g. reason for a fine">
            </div>
        </div>
    </div>
    <div class="form-actions">
        <button class="btn btn-brand"><i class="bi bi-check-lg"></i> Save fee</button>
        <a href="<?= url('fees/index.php') ?>" class="btn btn-light">Cancel</a>
    </div>
</form>
<?php
$extra_js = '<script>
function toggleBoxes() {
    const one = document.getElementById("tStudent").checked;
    document.getElementById("studentBox").style.display = one ? "" : "none";
    document.getElementById("batchBox").style.display = one ? "none" : "";
    document.getElementById("monthBox").style.display = document.getElementById("feeType").value === "Monthly" ? "" : "none";
}
document.querySelectorAll("input[name=target], #feeType").forEach(el => el.addEventListener("change", toggleBoxes));
toggleBoxes();
</script>';
require __DIR__ . '/../includes/footer.php';
