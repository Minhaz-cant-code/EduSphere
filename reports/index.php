<?php
/**
 * Reports (FR-REP-1..4) - Admin
 *   ?type=attendance | income | dues | students | teachers
 * Every report can be printed (browser "Save as PDF") or exported to CSV.
 */
require_once __DIR__ . '/../includes/functions.php';
require_role('admin');

$types = [
    'attendance' => ['Attendance', 'calendar-check'],
    'income'     => ['Income', 'cash-stack'],
    'dues'       => ['Due fees', 'exclamation-circle'],
    'students'   => ['Student summary', 'people'],
    'teachers'   => ['Teacher summary', 'person-badge'],
];
$type = $_GET['type'] ?? 'attendance';
if (!isset($types[$type])) $type = 'attendance';

$batch_id = get_int('batch_id');
$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to'] ?? today();
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) $from = date('Y-m-01');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to))   $to = today();

$batches = $pdo->query('SELECT id, name FROM batches ORDER BY name')->fetchAll();
$batch_sql = $batch_id ? ' AND s.batch_id = ' . $batch_id : '';   // integer, safe

$headers = [];
$rows = [];
$footer = null;

switch ($type) {
    case 'attendance':
        $headers = ['Roll', 'Student', 'Batch', 'Present', 'Late', 'Absent', 'Classes', 'Attendance %'];
        $st = $pdo->prepare(
            "SELECT s.roll_no, u.name, b.name AS batch,
                    SUM(a.status = 'Present') AS p, SUM(a.status = 'Late') AS l,
                    SUM(a.status = 'Absent') AS ab, COUNT(a.id) AS total
               FROM students s
               JOIN users u ON u.id = s.user_id
               LEFT JOIN batches b ON b.id = s.batch_id
               LEFT JOIN attendance a ON a.student_id = s.id AND a.att_date BETWEEN ? AND ?
              WHERE 1 = 1 $batch_sql
              GROUP BY s.id, s.roll_no, u.name, b.name
              ORDER BY b.name, s.roll_no"
        );
        $st->execute([$from, $to]);
        foreach ($st->fetchAll() as $r) {
            $pct = $r['total'] ? round(($r['p'] + $r['l']) / $r['total'] * 100, 1) . '%' : '-';
            $rows[] = [$r['roll_no'], $r['name'], $r['batch'], (int)$r['p'], (int)$r['l'], (int)$r['ab'], (int)$r['total'], $pct];
        }
        break;

    case 'income':
        $headers = ['Date', 'Receipt', 'Student', 'Batch', 'Fee type', 'Method', 'Amount'];
        $st = $pdo->prepare(
            "SELECT p.payment_date, p.receipt_no, u.name, b.name AS batch, f.fee_type, p.method, p.amount_paid
               FROM payments p
               JOIN fees f ON f.id = p.fee_id
               JOIN students s ON s.id = f.student_id
               JOIN users u ON u.id = s.user_id
               LEFT JOIN batches b ON b.id = s.batch_id
              WHERE p.payment_date BETWEEN ? AND ? $batch_sql
              ORDER BY p.payment_date, p.id"
        );
        $st->execute([$from, $to]);
        $total = 0;
        $by_type = [];
        foreach ($st->fetchAll() as $r) {
            $rows[] = [fmt_date($r['payment_date']), $r['receipt_no'], $r['name'], $r['batch'], $r['fee_type'], $r['method'], money($r['amount_paid'])];
            $total += $r['amount_paid'];
            $by_type[$r['fee_type']] = ($by_type[$r['fee_type']] ?? 0) + $r['amount_paid'];
        }
        $footer = ['Total income', money($total)];
        break;

    case 'dues':
        $headers = ['Roll', 'Student', 'Batch', 'Phone', 'Total fees', 'Paid', 'Due'];
        $st = $pdo->query(
            "SELECT s.id, s.roll_no, u.name, s.phone, b.name AS batch,
                    (SELECT COALESCE(SUM(amount),0) FROM fees f WHERE f.student_id = s.id) AS total,
                    (SELECT COALESCE(SUM(p.amount_paid),0) FROM payments p JOIN fees f ON f.id = p.fee_id WHERE f.student_id = s.id) AS paid
               FROM students s JOIN users u ON u.id = s.user_id LEFT JOIN batches b ON b.id = s.batch_id
              WHERE 1 = 1 $batch_sql
              ORDER BY b.name, s.roll_no"
        );
        $sum = 0;
        foreach ($st->fetchAll() as $r) {
            $due = $r['total'] - $r['paid'];
            if ($due <= 0) continue;
            $rows[] = [$r['roll_no'], $r['name'], $r['batch'], $r['phone'], money($r['total']), money($r['paid']), money($due)];
            $sum += $due;
        }
        $footer = ['Total due', money($sum)];
        break;

    case 'students':
        $headers = ['Roll', 'Student', 'Batch', 'Status', 'Attendance %', 'Exams', 'Avg GPA', 'Fee due'];
        $st = $pdo->query(
            "SELECT s.id, s.roll_no, u.name, u.status, b.name AS batch,
                    (SELECT COUNT(*) FROM results r WHERE r.student_id = s.id) AS exams,
                    (SELECT AVG(gpa) FROM results r WHERE r.student_id = s.id) AS gpa
               FROM students s JOIN users u ON u.id = s.user_id LEFT JOIN batches b ON b.id = s.batch_id
              WHERE 1 = 1 $batch_sql
              ORDER BY b.name, s.roll_no"
        );
        foreach ($st->fetchAll() as $r) {
            $att = attendance_summary((int)$r['id']);
            $fee = fee_summary((int)$r['id']);
            $rows[] = [$r['roll_no'], $r['name'], $r['batch'], ucfirst($r['status']),
                       $att['total'] ? $att['percent'] . '%' : '-', (int)$r['exams'],
                       $r['gpa'] !== null ? number_format($r['gpa'], 2) : '-', money($fee['due'])];
        }
        break;

    case 'teachers':
        $headers = ['Teacher', 'Qualification', 'Phone', 'Subjects', 'Exams created', 'Assignments', 'Salary', 'Status'];
        $st = $pdo->query(
            "SELECT t.id, u.id AS uid, u.name, u.status, t.qualification, t.phone, t.salary,
                    (SELECT COUNT(*) FROM teacher_subjects ts WHERE ts.teacher_id = t.id) AS subjects,
                    (SELECT COUNT(*) FROM exams e WHERE e.created_by = u.id) AS exams,
                    (SELECT COUNT(*) FROM assignments a WHERE a.teacher_id = t.id) AS assignments
               FROM teachers t JOIN users u ON u.id = t.user_id ORDER BY u.name"
        );
        $sum = 0;
        foreach ($st->fetchAll() as $r) {
            $rows[] = [$r['name'], $r['qualification'], $r['phone'], (int)$r['subjects'], (int)$r['exams'],
                       (int)$r['assignments'], money($r['salary']), ucfirst($r['status'])];
            if ($r['status'] === 'active') $sum += $r['salary'];
        }
        $footer = ['Monthly salary (active)', money($sum)];
        break;
}

$uses_dates = in_array($type, ['attendance', 'income'], true);
$uses_batch = $type !== 'teachers';

$page_title = 'Reports';
require __DIR__ . '/../includes/header.php';
// Right-align money and number columns
$is_num = fn($v) => is_int($v) || is_float($v) || str_starts_with((string)$v, '৳') || preg_match('/^\d+(\.\d+)?%$/', (string)$v);
$num_cols = [];
foreach ($headers as $i => $h) {
    $num_cols[$i] = $rows ? count(array_filter($rows, fn($r) => $is_num($r[$i]) || $r[$i] === '-')) === count($rows) : false;
}
$actions = '<button class="btn btn-light" data-export-csv="#report" data-filename="' . e($type) . '-report.csv"><i class="bi bi-filetype-csv"></i> Export CSV</button>'
    . '<button class="btn btn-light" onclick="window.print()"><i class="bi bi-printer"></i> Print / PDF</button>';
?>
<div class="print-only mb-2"><h5><?= e(INSTITUTE_NAME) ?> · <?= e($types[$type][0]) ?> report</h5></div>
<?php page_head('Reports', 'Generate, print or export institute reports.', $actions); ?>

<nav class="tabs-bar nav nav-pills" aria-label="Report type">
    <?php foreach ($types as $k => [$label, $icon]): ?>
        <a class="nav-link <?= $k === $type ? 'active' : '' ?>" href="?type=<?= $k ?>" <?= $k === $type ? 'aria-current="page"' : '' ?>><i class="bi bi-<?= $icon ?>"></i> <?= $label ?></a>
    <?php endforeach; ?>
</nav>

<?php if ($uses_batch || $uses_dates): ?>
<form class="filter-bar filter-form">
    <input type="hidden" name="type" value="<?= e($type) ?>">
    <?php if ($uses_batch): ?>
        <div class="grow"><label class="form-label" for="batch_id">Batch</label>
            <select name="batch_id" id="batch_id" class="form-select">
                <option value="">All batches</option>
                <?php foreach ($batches as $b): ?>
                    <option value="<?= $b['id'] ?>" <?= $batch_id == $b['id'] ? 'selected' : '' ?>><?= e($b['name']) ?></option>
                <?php endforeach; ?>
            </select></div>
    <?php endif; ?>
    <?php if ($uses_dates): ?>
        <div class="fixed"><label class="form-label" for="from">From</label><input type="date" name="from" id="from" class="form-control" value="<?= e($from) ?>"></div>
        <div class="fixed"><label class="form-label" for="to">To</label><input type="date" name="to" id="to" class="form-control" value="<?= e($to) ?>"></div>
    <?php endif; ?>
    <button class="btn btn-light"><i class="bi bi-arrow-repeat"></i> Generate</button>
</form>
<?php endif; ?>

<div class="row g-4">
    <div class="<?= ($type === 'income' && !empty($by_type)) ? 'col-xl-9' : 'col-12' ?>">
        <div class="card">
            <div class="table-toolbar">
                <div>
                    <div class="section-title"><?= e($types[$type][0]) ?> report</div>
                    <div class="section-sub"><?= $uses_dates ? fmt_date($from) . ' – ' . fmt_date($to) . ' · ' : '' ?><?= count($rows) ?> row<?= count($rows) === 1 ? '' : 's' ?> · generated <?= date('j M Y, g:i A') ?></div>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-hover" id="report">
                    <thead><tr><?php foreach ($headers as $i => $h): ?><th class="<?= $num_cols[$i] ? 'num' : '' ?>"><?= e($h) ?></th><?php endforeach; ?></tr></thead>
                    <tbody>
                    <?php foreach ($rows as $r): ?>
                        <tr><?php foreach ($r as $i => $c): ?><td class="<?= $num_cols[$i] ? 'num' : '' ?> <?= $i === 1 ? 'cell-main' : '' ?>"><?= e($c) ?></td><?php endforeach; ?></tr>
                    <?php endforeach; ?>
                    <?php if (!$rows): ?><?= empty_row(count($headers), 'bar-chart-line', 'No data for this selection', 'Try another batch or date range.') ?><?php endif; ?>
                    </tbody>
                    <?php if ($footer && $rows): ?>
                        <tfoot><tr><td colspan="<?= count($headers) - 1 ?>" class="text-end text-muted fw-medium"><?= e($footer[0]) ?></td><td class="num"><?= e($footer[1]) ?></td></tr></tfoot>
                    <?php endif; ?>
                </table>
            </div>
        </div>
    </div>
    <?php if ($type === 'income' && !empty($by_type)): $by_total = array_sum($by_type); ?>
        <div class="col-xl-3">
            <div class="card">
                <div class="card-header"><h2 class="section-title">By fee type</h2></div>
                <div class="card-body d-grid gap-3">
                    <?php arsort($by_type); foreach ($by_type as $k => $v): ?>
                        <div>
                            <div class="d-flex justify-content-between small mb-1"><span class="fw-medium text-dark"><?= e($k) ?></span><span class="tabular"><?= money($v) ?></span></div>
                            <div class="meter"><span style="width:<?= $by_total > 0 ? round($v / $by_total * 100) : 0 ?>%"></span></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
