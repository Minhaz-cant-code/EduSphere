<?php
/**
 * Result sheets (FR-EXM-4)
 *   ?id=EXAM_ID       -> merit list of one exam
 *   ?batch_id=BATCH   -> all exams of a batch: one row per student, average GPA
 */
require_once __DIR__ . '/../includes/functions.php';
require_role('admin', 'teacher');

$exam_id  = get_int('id');
$batch_id = get_int('batch_id');

if ($exam_id) {
    // ---------- One exam ----------
    $st = $pdo->prepare('SELECT e.*, s.name AS subject, b.name AS batch FROM exams e JOIN subjects s ON s.id = e.subject_id JOIN batches b ON b.id = s.batch_id WHERE e.id = ?');
    $st->execute([$exam_id]);
    $exam = $st->fetch();
    if (!$exam || !can_manage_subject((int)$exam['subject_id'])) {
        flash('danger', 'Exam not found.');
        redirect('exams/index.php');
    }
    $st = $pdo->prepare(
        'SELECT r.*, s.roll_no, u.name FROM results r JOIN students s ON s.id = r.student_id JOIN users u ON u.id = s.user_id
          WHERE r.exam_id = ? ORDER BY r.marks DESC, s.roll_no'
    );
    $st->execute([$exam_id]);
    $rows = $st->fetchAll();

    $marks = array_map('floatval', array_column($rows, 'marks'));
    $stats = [
        'Highest' => $marks ? max($marks) : 0,
        'Lowest'  => $marks ? min($marks) : 0,
        'Average' => $marks ? round(array_sum($marks) / count($marks), 2) : 0,
        'Pass rate' => $rows ? round(count(array_filter($rows, fn($r) => $r['grade'] !== 'F')) / count($rows) * 100) . '%' : '-',
    ];
    $title = $exam['exam_name'];
    $subtitle = $exam['batch'] . ' · ' . $exam['subject'] . ' · ' . fmt_date($exam['exam_date']) . ' · Total ' . $exam['total_marks'];
} elseif ($batch_id && can_manage_batch($batch_id)) {
    // ---------- Whole batch ----------
    $st = $pdo->prepare('SELECT name FROM batches WHERE id = ?');
    $st->execute([$batch_id]);
    $title = 'Batch result sheet';
    $subtitle = $st->fetchColumn();

    $st = $pdo->prepare('SELECT e.id, e.exam_name, e.total_marks, s.name AS subject FROM exams e JOIN subjects s ON s.id = e.subject_id WHERE s.batch_id = ? ORDER BY e.exam_date');
    $st->execute([$batch_id]);
    $exams = $st->fetchAll();

    $st = $pdo->prepare('SELECT s.id, s.roll_no, u.name FROM students s JOIN users u ON u.id = s.user_id WHERE s.batch_id = ? ORDER BY s.roll_no');
    $st->execute([$batch_id]);
    $students = $st->fetchAll();

    $st = $pdo->prepare('SELECT r.* FROM results r JOIN exams e ON e.id = r.exam_id JOIN subjects s ON s.id = e.subject_id WHERE s.batch_id = ?');
    $st->execute([$batch_id]);
    $res = [];
    foreach ($st->fetchAll() as $r) $res[$r['student_id']][$r['exam_id']] = $r;
} else {
    redirect('exams/index.php');
}

$page_title = $title;
require __DIR__ . '/../includes/header.php';
$actions = '<button class="btn btn-light" data-export-csv="#resultTable" data-filename="results.csv"><i class="bi bi-filetype-csv"></i> Export CSV</button>'
    . '<button class="btn btn-light" onclick="window.print()"><i class="bi bi-printer"></i> Print</button>';
?>
<div class="print-only mb-2"><h5><?= e(INSTITUTE_NAME) ?></h5></div>
<?php page_head($title, e($subtitle), $actions, ['All exams', 'exams/index.php']); ?>

<?php if ($exam_id): ?>
    <section class="kpi-strip" aria-label="Exam statistics">
        <?php foreach ([['Highest', $stats['Highest'], 'arrow-up', 'is-success'], ['Lowest', $stats['Lowest'], 'arrow-down', 'is-danger'],
                        ['Average', $stats['Average'], 'bar-chart', 'is-primary'], ['Pass rate', $stats['Pass rate'], 'check2-circle', 'is-info']] as [$k, $v, $icon, $tone]): ?>
            <div class="kpi">
                <div class="kpi-label"><span class="kpi-icon <?= $tone ?>"><i class="bi bi-<?= $icon ?>"></i></span> <?= $k ?></div>
                <div class="kpi-value"><?= $rows ? e($v) : '—' ?></div>
                <div class="kpi-meta"><?= $k === 'Pass rate' ? count($rows) . ' students graded' : 'out of ' . (int)$exam['total_marks'] ?></div>
            </div>
        <?php endforeach; ?>
    </section>
    <div class="card">
        <div class="table-responsive">
            <table class="table table-stack" id="resultTable">
                <thead><tr><th style="width:70px">Pos.</th><th>Student</th><th class="num">Marks</th><th class="num">%</th><th>Grade</th><th class="num">GPA</th></tr></thead>
                <tbody>
                <?php foreach ($rows as $i => $r): ?>
                    <tr>
                        <td data-label="Position"><?= $i < 3 ? '<span class="badge text-bg-primary">#' . ($i + 1) . '</span>' : '<span class="text-muted tabular">#' . ($i + 1) . '</span>' ?></td>
                        <td><div class="cell-main"><?= e($r['name']) ?></div><div class="cell-sub">Roll <?= e($r['roll_no']) ?></div></td>
                        <td class="num" data-label="Marks"><?= (float)$r['marks'] ?></td>
                        <td class="num" data-label="%"><?= round($r['marks'] / $exam['total_marks'] * 100, 1) ?>%</td>
                        <td data-label="Grade"><span class="badge <?= $r['grade'] === 'F' ? 'text-bg-danger' : 'text-bg-success' ?>"><?= e($r['grade']) ?></span></td>
                        <td class="num" data-label="GPA"><?= number_format($r['gpa'], 2) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$rows): ?><?= empty_row(6, 'pencil-square', 'No marks entered yet', 'Enter marks to see the merit list.', '<a class="btn btn-sm btn-brand" href="' . url('exams/marks.php?id=' . $exam_id) . '">Enter marks</a>') ?><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php else: ?>
    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm mb-0" id="resultTable">
                <thead>
                <tr><th>Roll</th><th>Student</th>
                    <?php foreach ($exams as $ex): ?>
                        <th class="text-center" style="text-transform:none;letter-spacing:0;font-size:12px"><?= e($ex['exam_name']) ?><div class="fw-normal text-muted"><?= e($ex['subject']) ?> · /<?= (int)$ex['total_marks'] ?></div></th>
                    <?php endforeach; ?>
                    <th class="text-center">Avg GPA</th></tr>
                </thead>
                <tbody>
                <?php foreach ($students as $s):
                    $gpas = array_map(fn($r) => (float)$r['gpa'], $res[$s['id']] ?? []); ?>
                    <tr>
                        <td class="tabular text-muted"><?= e($s['roll_no']) ?></td>
                        <td class="text-nowrap cell-main"><?= e($s['name']) ?></td>
                        <?php foreach ($exams as $ex): $r = $res[$s['id']][$ex['id']] ?? null; ?>
                            <td class="text-center tabular"><?= $r ? (float)$r['marks'] . ' <span class="badge ' . ($r['grade'] === 'F' ? 'text-bg-danger' : 'text-bg-light') . '">' . e($r['grade']) . '</span>' : '<span class="text-muted">—</span>' ?></td>
                        <?php endforeach; ?>
                        <td class="text-center fw-semibold text-dark tabular"><?= $gpas ? number_format(array_sum($gpas) / count($gpas), 2) : '—' ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$students): ?><?= empty_row(count($exams) + 3, 'people', 'No students in this batch') ?><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
