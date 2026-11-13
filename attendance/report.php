<?php
/**
 * Monthly attendance sheet per batch (FR-ATT-3) - Admin/Teacher
 * Rows = students, columns = days of the month, plus totals and %.
 */
require_once __DIR__ . '/../includes/functions.php';
require_role('teacher', 'admin');

$batches  = manageable_batches();
$batch_id = get_int('batch_id', $batches ? (int)$batches[0]['id'] : 0);
$month    = $_GET['month'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $month)) $month = date('Y-m');
if ($batch_id && !can_manage_batch($batch_id)) $batch_id = 0;

$from = $month . '-01';
$to   = date('Y-m-t', strtotime($from));
$days = (int)date('t', strtotime($from));

$students = [];
$grid = [];
$class_days = [];
if ($batch_id) {
    $st = $pdo->prepare('SELECT s.id, s.roll_no, u.name FROM students s JOIN users u ON u.id = s.user_id WHERE s.batch_id = ? ORDER BY s.roll_no');
    $st->execute([$batch_id]);
    $students = $st->fetchAll();

    $st = $pdo->prepare('SELECT student_id, att_date, status FROM attendance WHERE batch_id = ? AND att_date BETWEEN ? AND ?');
    $st->execute([$batch_id, $from, $to]);
    foreach ($st->fetchAll() as $r) {
        $d = (int)substr($r['att_date'], 8, 2);
        $grid[$r['student_id']][$d] = $r['status'];
        $class_days[$d] = true;
    }
}
$batch_name = '';
foreach ($batches as $b) if ($b['id'] == $batch_id) $batch_name = $b['name'];

$page_title = 'Attendance report';
require __DIR__ . '/../includes/header.php';
$actions = (role() === 'teacher' ? '<a href="' . url('attendance/mark.php') . '" class="btn btn-brand"><i class="bi bi-check2-square"></i> Mark attendance</a>' : '')
    . '<button class="btn btn-light" data-export-csv="#attReport" data-filename="attendance-' . e($month) . '.csv"><i class="bi bi-filetype-csv"></i> Export CSV</button>'
    . '<button class="btn btn-light" onclick="window.print()"><i class="bi bi-printer"></i> Print</button>';
?>
<?php page_head('Attendance report', 'Monthly attendance sheet for a batch. Late counts as attended.', $actions); ?>

<form class="filter-bar filter-form">
    <div class="grow"><label class="form-label" for="batch_id">Batch</label>
        <select name="batch_id" id="batch_id" class="form-select">
            <?php foreach ($batches as $b): ?>
                <option value="<?= $b['id'] ?>" <?= $batch_id == $b['id'] ? 'selected' : '' ?>><?= e($b['name']) ?></option>
            <?php endforeach; ?>
        </select></div>
    <div class="fixed"><label class="form-label" for="month">Month</label>
        <input type="month" name="month" id="month" class="form-control" value="<?= e($month) ?>"></div>
    <button class="btn btn-light"><i class="bi bi-funnel"></i> Show</button>
</form>

<div class="print-only mb-2">
    <h5><?= e(INSTITUTE_NAME) ?> · Attendance: <?= e($batch_name) ?>, <?= date('F Y', strtotime($from)) ?></h5>
</div>

<div class="card">
    <div class="table-toolbar">
        <div>
            <div class="section-title"><?= e($batch_name ?: 'No batch') ?></div>
            <div class="section-sub"><?= date('F Y', strtotime($from)) ?> · <?= count($class_days) ?> class day<?= count($class_days) === 1 ? '' : 's' ?></div>
        </div>
        <div class="att-summary">
            <span><span class="att-cell att-P">P</span> Present</span>
            <span><span class="att-cell att-L">L</span> Late</span>
            <span><span class="att-cell att-A">A</span> Absent</span>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-sm mb-0 text-center" id="attReport">
            <thead>
            <tr>
                <th class="text-start">Roll</th><th class="text-start">Student</th>
                <?php for ($d = 1; $d <= $days; $d++): if (!isset($class_days[$d])) continue; ?>
                    <th class="px-1"><?= $d ?></th>
                <?php endfor; ?>
                <th>P</th><th>L</th><th>A</th><th class="text-end">Rate</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($students as $s):
                $row = $grid[$s['id']] ?? [];
                $p = count(array_keys($row, 'Present'));
                $l = count(array_keys($row, 'Late'));
                $a = count(array_keys($row, 'Absent'));
                $tot = $p + $l + $a;
                $pct = $tot ? round(($p + $l) / $tot * 100, 1) : 0;
            ?>
                <tr>
                    <td class="text-start tabular text-muted"><?= e($s['roll_no']) ?></td>
                    <td class="text-start text-nowrap cell-main"><?= e($s['name']) ?></td>
                    <?php for ($d = 1; $d <= $days; $d++): if (!isset($class_days[$d])) continue; $v = $row[$d] ?? ''; ?>
                        <td class="px-1"><?= $v ? '<span class="att-cell att-' . $v[0] . '">' . $v[0] . '</span>' : '<span class="att-none">·</span>' ?></td>
                    <?php endfor; ?>
                    <td class="tabular"><?= $p ?></td><td class="tabular"><?= $l ?></td><td class="tabular"><?= $a ?></td>
                    <td class="text-end">
                        <?php if ($tot): ?>
                            <span class="badge <?= $pct >= 75 ? 'text-bg-success' : ($pct >= 50 ? 'text-bg-warning' : 'text-bg-danger') ?>"><?= $pct ?>%</span>
                        <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$students): ?><?= empty_row(6 + count($class_days), 'people', 'No students in this batch') ?><?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($students && !$class_days): ?>
        <div class="card-footer text-muted small"><i class="bi bi-info-circle"></i> No attendance was taken in <?= date('F Y', strtotime($from)) ?>.</div>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
