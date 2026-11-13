<?php
/** Teacher dashboard - included from dashboard.php */
if (!defined('IN_APP')) exit;

$tid      = my_teacher_id();
$subjects = teacher_subjects();
$batches  = manageable_batches();
$today    = today();

// Attendance status of my batches today
$batch_status = [];
foreach ($batches as $b) {
    $st = $pdo->prepare('SELECT COUNT(*) FROM attendance WHERE batch_id = ? AND att_date = ?');
    $st->execute([$b['id'], $today]);
    $b['marked'] = (int)$st->fetchColumn();
    $st = $pdo->prepare('SELECT COUNT(*) FROM students WHERE batch_id = ?');
    $st->execute([$b['id']]);
    $b['students'] = (int)$st->fetchColumn();
    $b['subjects'] = array_column(array_filter($subjects, fn($s) => (int)$s['batch_id'] === (int)$b['id']), 'name');
    $batch_status[] = $b;
}
$pending_att = count(array_filter($batch_status, fn($b) => !$b['marked'] && $b['students']));

// Upcoming exams in my subjects
$st = $pdo->prepare(
    'SELECT e.*, s.name AS subject, b.name AS batch
       FROM exams e
       JOIN subjects s ON s.id = e.subject_id
       JOIN batches  b ON b.id = s.batch_id
       JOIN teacher_subjects ts ON ts.subject_id = s.id
      WHERE ts.teacher_id = ? AND e.exam_date >= ?
      ORDER BY e.exam_date LIMIT 5'
);
$st->execute([$tid, $today]);
$upcoming = $st->fetchAll();

// Submissions waiting for marks
$st = $pdo->prepare(
    'SELECT COUNT(*) FROM submissions sb JOIN assignments a ON a.id = sb.assignment_id
      WHERE a.teacher_id = ? AND sb.marks IS NULL'
);
$st->execute([$tid]);
$to_grade = (int)$st->fetchColumn();

$notices = visible_notices(4);
$first_name = explode(' ', user()['name'])[0];
?>
<div class="page-head greeting">
    <div>
        <h1><?= e(greeting()) ?>, <?= e($first_name) ?></h1>
        <p class="page-sub">
            <?php if ($pending_att): ?>
                You have <strong class="text-dark"><?= $pending_att ?> batch<?= $pending_att > 1 ? 'es' : '' ?></strong> waiting for today's attendance.
            <?php elseif ($batch_status): ?>
                Attendance is done for all your batches today.
            <?php else: ?>
                Welcome to <?= e(APP_NAME) ?>.
            <?php endif; ?>
        </p>
    </div>
    <div class="page-actions">
        <a href="<?= url('assignments/form.php') ?>" class="btn btn-light"><i class="bi bi-upload"></i> New assignment</a>
        <a href="<?= url('exams/form.php') ?>" class="btn btn-brand"><i class="bi bi-plus-lg"></i> Create exam</a>
    </div>
</div>

<section class="kpi-strip" aria-label="Key figures">
    <div class="kpi">
        <div class="kpi-label"><span class="kpi-icon is-primary"><i class="bi bi-book"></i></span> My subjects</div>
        <div class="kpi-value"><?= count($subjects) ?></div>
        <div class="kpi-meta">across <strong><?= count($batches) ?></strong> batch<?= count($batches) === 1 ? '' : 'es' ?></div>
    </div>
    <a class="kpi" href="<?= url('attendance/mark.php') ?>">
        <div class="kpi-label"><span class="kpi-icon is-success"><i class="bi bi-check2-square"></i></span> Attendance today</div>
        <div class="kpi-value"><?= count($batch_status) - $pending_att ?><span class="unit">/<?= count($batch_status) ?></span></div>
        <div class="kpi-meta">batches marked</div>
    </a>
    <a class="kpi" href="<?= url('exams/index.php') ?>">
        <div class="kpi-label"><span class="kpi-icon is-warning"><i class="bi bi-journal-check"></i></span> Upcoming exams</div>
        <div class="kpi-value"><?= count($upcoming) ?></div>
        <div class="kpi-meta"><?= $upcoming ? 'Next on <strong>' . date('j M', strtotime($upcoming[0]['exam_date'])) . '</strong>' : 'Nothing scheduled' ?></div>
    </a>
    <a class="kpi" href="<?= url('assignments/index.php') ?>">
        <div class="kpi-label"><span class="kpi-icon <?= $to_grade ? 'is-danger' : 'is-info' ?>"><i class="bi bi-inbox"></i></span> To grade</div>
        <div class="kpi-value"><?= $to_grade ?></div>
        <div class="kpi-meta"><?= $to_grade ? 'submissions need marks' : 'You\'re all caught up' ?></div>
    </a>
</section>

<?php $risk_list = risk_list(array_column($batches, 'id')); require __DIR__ . '/risk_widget.php'; ?>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card mb-4">
            <div class="card-header">
                <div><h2 class="section-title">Today's classes</h2><p class="section-sub"><?= date('l, j F') ?></p></div>
                <a href="<?= url('attendance/report.php') ?>" class="link-more">Attendance report <i class="bi bi-arrow-right"></i></a>
            </div>
            <?php if ($batch_status): ?>
                <?php foreach ($batch_status as $b): ?>
                    <div class="class-row">
                        <div class="class-icon"><i class="bi bi-people"></i></div>
                        <div class="list-row-main">
                            <div class="list-row-title"><?= e($b['name']) ?></div>
                            <div class="list-row-sub"><?= $b['students'] ?> students · <?= e(implode(', ', $b['subjects'])) ?></div>
                        </div>
                        <?php if ($b['marked']): ?>
                            <span class="badge dot text-bg-success">Attendance taken</span>
                            <a class="btn btn-sm btn-ghost" href="<?= url('attendance/mark.php?batch_id=' . $b['id']) ?>">Edit</a>
                        <?php else: ?>
                            <a class="btn btn-sm btn-brand" href="<?= url('attendance/mark.php?batch_id=' . $b['id']) ?>"><i class="bi bi-check2-square"></i> Take attendance</a>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <?= empty_state('collection', 'No batch assigned yet', 'Ask the admin to allocate subjects to you. Your batches will appear here.') ?>
            <?php endif; ?>
        </div>

        <div class="card">
            <div class="card-header">
                <div><h2 class="section-title">Upcoming exams</h2><p class="section-sub">Your subjects only</p></div>
                <a href="<?= url('exams/index.php') ?>" class="link-more">All exams <i class="bi bi-arrow-right"></i></a>
            </div>
            <?php if ($upcoming): ?>
                <div class="list-rows">
                    <?php foreach ($upcoming as $ex): ?>
                        <a class="list-row" href="<?= url('exams/marks.php?id=' . $ex['id']) ?>">
                            <div class="date-block"><span class="m"><?= date('M', strtotime($ex['exam_date'])) ?></span><span class="d"><?= date('j', strtotime($ex['exam_date'])) ?></span></div>
                            <div class="list-row-main">
                                <div class="list-row-title"><?= e($ex['exam_name']) ?></div>
                                <div class="list-row-sub"><?= e($ex['batch']) ?> · <?= e($ex['subject']) ?> · <?= (int)$ex['total_marks'] ?> marks</div>
                            </div>
                            <span class="link-more">Enter marks <i class="bi bi-arrow-right"></i></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <?= empty_state('journal-check', 'No upcoming exams', 'Create an exam to start entering marks.', '<a class="btn btn-sm btn-light" href="' . url('exams/form.php') . '">Create exam</a>') ?>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-lg-5">
        <?php require __DIR__ . '/notices_widget.php'; ?>
    </div>
</div>
