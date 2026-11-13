<?php
/**
 * Published results for a student / parent (FR-EXM-5)
 */
require_once __DIR__ . '/../includes/functions.php';
require_role('student', 'parent');

$sid = my_student_id();
$st = $pdo->prepare(
    'SELECT r.*, e.exam_name, e.exam_type, e.exam_date, e.total_marks, s.name AS subject
       FROM results r JOIN exams e ON e.id = r.exam_id JOIN subjects s ON s.id = e.subject_id
      WHERE r.student_id = ? AND e.is_published = 1
      ORDER BY e.exam_date DESC'
);
$st->execute([$sid]);
$results = $st->fetchAll();

// Average GPA per subject
$by_subject = [];
foreach ($results as $r) {
    $by_subject[$r['subject']][] = (float)$r['gpa'];
}
$overall = $results ? array_sum(array_column($results, 'gpa')) / count($results) : 0;

// Upcoming exams of the batch
$st = $pdo->prepare(
    'SELECT e.exam_name, e.exam_type, e.exam_date, e.total_marks, s.name AS subject
       FROM exams e JOIN subjects s ON s.id = e.subject_id
      WHERE s.batch_id = (SELECT batch_id FROM students WHERE id = ?) AND e.exam_date >= ?
      ORDER BY e.exam_date'
);
$st->execute([$sid, today()]);
$upcoming = $st->fetchAll();

$page_title = role() === 'parent' ? "Child's results" : 'My results';
require __DIR__ . '/../includes/header.php';
?>
<?php page_head($page_title, 'Published results and upcoming exams.', '<button class="btn btn-light" onclick="window.print()"><i class="bi bi-printer"></i> Print</button>'); ?>

<div class="row g-4 mb-4">
    <div class="col-md-4">
        <div class="card h-100"><div class="card-body">
            <div class="kpi-label"><span class="kpi-icon is-primary"><i class="bi bi-award"></i></span> Overall average GPA</div>
            <div class="kpi-value"><?= $results ? number_format($overall, 2) : '—' ?><span class="unit">/ 5.00</span></div>
            <div class="kpi-meta">From <?= count($results) ?> published result<?= count($results) === 1 ? '' : 's' ?></div>
        </div></div>
    </div>
    <div class="col-md-8">
        <div class="card h-100">
            <div class="card-header"><h2 class="section-title">By subject</h2></div>
            <div class="card-body">
                <?php if ($by_subject): ?>
                    <div class="d-grid gap-3">
                    <?php foreach ($by_subject as $sub => $g): $avg = array_sum($g) / count($g); ?>
                        <div class="d-flex align-items-center gap-3">
                            <div style="width:40%" class="text-truncate fw-medium text-dark"><?= e($sub) ?></div>
                            <div class="meter flex-grow-1"><span style="width:<?= $avg / 5 * 100 ?>%"></span></div>
                            <div class="tabular fw-semibold text-dark" style="width:44px;text-align:right"><?= number_format($avg, 2) ?></div>
                        </div>
                    <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-muted small">Subject averages appear once results are published.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header"><h2 class="section-title">Published results</h2></div>
    <div class="table-responsive">
        <table class="table table-stack">
            <thead><tr><th>Exam</th><th>Date</th><th class="num">Marks</th><th class="num">%</th><th>Grade</th><th class="num">GPA</th></tr></thead>
            <tbody>
            <?php foreach ($results as $r): ?>
                <tr>
                    <td><div class="cell-main"><?= e($r['exam_name']) ?></div><div class="cell-sub"><?= e($r['subject']) ?> · <?= e($r['exam_type']) ?></div></td>
                    <td data-label="Date"><?= fmt_date($r['exam_date']) ?></td>
                    <td class="num" data-label="Marks"><?= (float)$r['marks'] ?> <span class="text-muted">/ <?= (int)$r['total_marks'] ?></span></td>
                    <td class="num" data-label="%"><?= round($r['marks'] / $r['total_marks'] * 100, 1) ?>%</td>
                    <td data-label="Grade"><span class="badge <?= $r['grade'] === 'F' ? 'text-bg-danger' : 'text-bg-success' ?>"><?= e($r['grade']) ?></span></td>
                    <td class="num fw-semibold" data-label="GPA"><?= number_format($r['gpa'], 2) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$results): ?><?= empty_row(6, 'award', 'No published results yet', 'Teachers publish results after marking.') ?><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card no-print">
    <div class="card-header"><h2 class="section-title">Upcoming exams</h2></div>
    <?php if ($upcoming): ?>
        <div class="list-rows">
            <?php foreach ($upcoming as $u): ?>
                <div class="list-row">
                    <div class="date-block"><span class="m"><?= date('M', strtotime($u['exam_date'])) ?></span><span class="d"><?= date('j', strtotime($u['exam_date'])) ?></span></div>
                    <div class="list-row-main">
                        <div class="list-row-title"><?= e($u['exam_name']) ?></div>
                        <div class="list-row-sub"><?= e($u['subject']) ?> · <?= e($u['exam_type']) ?> · <?= (int)$u['total_marks'] ?> marks</div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <?= empty_state('calendar2', 'No upcoming exams', '', '', true) ?>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
