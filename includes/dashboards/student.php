<?php
/**
 * Student dashboard - also used by parents (shows the linked child).
 * Included from dashboard.php
 */
if (!defined('IN_APP')) exit;

$sid = my_student_id();
$is_parent = role() === 'parent';

if (!$sid) {
    echo '<div class="card">' . empty_state('person-x', 'No student profile linked',
        'Your account is not linked to a student profile yet. Please contact the institute office.') . '</div>';
    return;
}

$st = $pdo->prepare(
    'SELECT s.*, u.name, b.name AS batch_name
       FROM students s JOIN users u ON u.id = s.user_id
       LEFT JOIN batches b ON b.id = s.batch_id WHERE s.id = ?'
);
$st->execute([$sid]);
$stu = $st->fetch();

$att  = attendance_summary($sid);
$fee  = fee_summary($sid);

// Next exam for the student's batch
$st = $pdo->prepare(
    'SELECT e.*, s.name AS subject FROM exams e JOIN subjects s ON s.id = e.subject_id
      WHERE s.batch_id = ? AND e.exam_date >= ? ORDER BY e.exam_date LIMIT 1'
);
$st->execute([(int)$stu['batch_id'], today()]);
$next_exam = $st->fetch();

// Latest published results
$st = $pdo->prepare(
    'SELECT r.*, e.exam_name, e.exam_date, e.total_marks, s.name AS subject
       FROM results r JOIN exams e ON e.id = r.exam_id JOIN subjects s ON s.id = e.subject_id
      WHERE r.student_id = ? AND e.is_published = 1
      ORDER BY e.exam_date DESC LIMIT 5'
);
$st->execute([$sid]);
$results = $st->fetchAll();
$recent_gpa = $results ? array_sum(array_column($results, 'gpa')) / count($results) : null;

// Pending assignments (students only)
$pending = [];
if (!$is_parent) {
    $st = $pdo->prepare(
        'SELECT a.*, s.name AS subject FROM assignments a JOIN subjects s ON s.id = a.subject_id
          WHERE s.batch_id = ? AND a.deadline >= ?
            AND a.id NOT IN (SELECT assignment_id FROM submissions WHERE student_id = ?)
          ORDER BY a.deadline'
    );
    $st->execute([(int)$stu['batch_id'], now(), $sid]);
    $pending = $st->fetchAll();
}

$notices = visible_notices(4);
$att_cls = $att['percent'] >= 75 ? 'is-success' : ($att['percent'] >= 50 ? 'is-warning' : 'is-danger');
$first_name = explode(' ', user()['name'])[0];
$child_first = explode(' ', $stu['name'])[0];
?>
<div class="page-head greeting">
    <div>
        <h1><?= e(greeting()) ?>, <?= e($first_name) ?></h1>
        <p class="page-sub"><?= $is_parent ? 'Here\'s how ' . e($child_first) . ' is doing at ' . e(INSTITUTE_NAME) . '.' : 'Here\'s your progress at a glance.' ?></p>
    </div>
</div>

<?php if ($is_parent):
    $risk = risk_score($sid);
    if ($risk['enough'] && $risk['level'] !== 'Low'): ?>
    <div class="alert <?= $risk['level'] === 'High' ? 'alert-danger' : 'alert-warning' ?> mb-4" role="alert">
        <i class="bi bi-exclamation-triangle"></i>
        <div>
            <strong><?= e($child_first) ?> may need extra support (<?= e($risk['level']) ?> risk).</strong>
            <div class="mt-1"><?= e(implode(' · ', $risk['reasons'])) ?></div>
            <div class="small mt-1">Please talk with your child or contact the institute office.</div>
        </div>
    </div>
<?php endif; endif; ?>

<div class="card mb-4">
    <div class="card-body profile-strip">
        <?= avatar($stu['name'], $stu['photo'], 'lg') ?>
        <div class="min-w-0">
            <?php if ($is_parent): ?><div class="small text-muted fw-semibold text-uppercase" style="letter-spacing:.05em">Your child</div><?php endif; ?>
            <div class="h5 mb-1"><?= e($stu['name']) ?></div>
            <div class="facts">
                <span><i class="bi bi-collection"></i> <?= e($stu['batch_name'] ?? 'No batch') ?></span>
                <span><i class="bi bi-hash"></i> Roll <?= e($stu['roll_no']) ?></span>
                <?php if ($stu['admission_date']): ?><span><i class="bi bi-calendar3"></i> Since <?= date('M Y', strtotime($stu['admission_date'])) ?></span><?php endif; ?>
            </div>
        </div>
    </div>
</div>

<section class="kpi-strip" aria-label="Key figures">
    <a class="kpi" href="<?= url('attendance/my.php') ?>">
        <div class="kpi-label"><span class="kpi-icon is-success"><i class="bi bi-calendar2-check"></i></span> Attendance</div>
        <?php if ($att['total']): ?>
            <div class="kpi-value"><?= $att['percent'] ?><span class="unit">%</span></div>
            <div class="meter <?= $att_cls ?>"><span style="width:<?= min(100, $att['percent']) ?>%"></span></div>
            <div class="kpi-meta"><strong><?= $att['present'] + $att['late'] ?></strong> of <?= $att['total'] ?> classes attended</div>
        <?php else: ?>
            <div class="kpi-value is-text">No classes yet</div>
            <div class="kpi-meta">Attendance appears after the first class</div>
        <?php endif; ?>
    </a>
    <a class="kpi" href="<?= url('fees/my.php') ?>">
        <div class="kpi-label"><span class="kpi-icon <?= $fee['due'] > 0 ? 'is-danger' : 'is-success' ?>"><i class="bi bi-wallet2"></i></span> Fee due</div>
        <div class="kpi-value"><?= money_kpi($fee['due']) ?></div>
        <div class="kpi-meta"><?= $fee['due'] > 0 ? 'of <strong>' . money($fee['total']) . '</strong> charged' : 'All fees are paid' ?></div>
    </a>
    <a class="kpi" href="<?= url('exams/my.php') ?>">
        <div class="kpi-label"><span class="kpi-icon is-warning"><i class="bi bi-journal-check"></i></span> Next exam</div>
        <?php if ($next_exam): ?>
            <div class="kpi-value is-text" title="<?= e($next_exam['exam_name']) ?>"><?= e($next_exam['exam_name']) ?></div>
            <div class="kpi-meta"><strong><?= date('D, j M', strtotime($next_exam['exam_date'])) ?></strong> · <?= e($next_exam['subject']) ?></div>
        <?php else: ?>
            <div class="kpi-value is-text">None scheduled</div>
            <div class="kpi-meta">No upcoming exams</div>
        <?php endif; ?>
    </a>
    <?php if ($is_parent): ?>
        <a class="kpi" href="<?= url('exams/my.php') ?>">
            <div class="kpi-label"><span class="kpi-icon is-primary"><i class="bi bi-award"></i></span> Recent GPA</div>
            <div class="kpi-value"><?= $recent_gpa !== null ? number_format($recent_gpa, 2) : '—' ?></div>
            <div class="kpi-meta"><?= $results ? 'Average of last ' . count($results) . ' result' . (count($results) > 1 ? 's' : '') : 'No published results yet' ?></div>
        </a>
    <?php else: ?>
        <a class="kpi" href="<?= url('assignments/index.php') ?>">
            <div class="kpi-label"><span class="kpi-icon is-primary"><i class="bi bi-file-earmark-text"></i></span> Assignments</div>
            <div class="kpi-value"><?= count($pending) ?></div>
            <div class="kpi-meta"><?= $pending ? 'waiting for your submission' : 'Nothing pending' ?></div>
        </a>
    <?php endif; ?>
</section>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card <?= $is_parent ? 'h-100' : 'mb-4' ?>">
            <div class="card-header">
                <div><h2 class="section-title">Recent results</h2><p class="section-sub">Published by teachers</p></div>
                <a class="link-more" href="<?= url('exams/my.php') ?>">All results <i class="bi bi-arrow-right"></i></a>
            </div>
            <?php if ($results): ?>
                <div class="table-responsive">
                    <table class="table table-stack">
                        <thead><tr><th>Exam</th><th class="num">Marks</th><th>Grade</th></tr></thead>
                        <tbody>
                        <?php foreach ($results as $r): ?>
                            <tr>
                                <td><div class="cell-main"><?= e($r['exam_name']) ?></div><div class="cell-sub"><?= e($r['subject']) ?> · <?= fmt_date($r['exam_date']) ?></div></td>
                                <td class="num" data-label="Marks"><?= (float)$r['marks'] ?> <span class="text-muted">/ <?= (int)$r['total_marks'] ?></span></td>
                                <td data-label="Grade"><span class="badge <?= $r['grade'] === 'F' ? 'text-bg-danger' : 'text-bg-success' ?>"><?= e($r['grade']) ?> · <?= number_format($r['gpa'], 2) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <?= empty_state('award', 'No results yet', 'Results appear here once teachers publish them.') ?>
            <?php endif; ?>
        </div>

        <?php if (!$is_parent): ?>
        <div class="card">
            <div class="card-header">
                <div><h2 class="section-title">Pending assignments</h2><p class="section-sub">Submit before the deadline</p></div>
                <a class="link-more" href="<?= url('assignments/index.php') ?>">All assignments <i class="bi bi-arrow-right"></i></a>
            </div>
            <?php if ($pending): ?>
                <div class="list-rows">
                    <?php foreach ($pending as $a): $hrs = (strtotime($a['deadline']) - time()) / 3600; ?>
                        <a class="list-row" href="<?= url('assignments/view.php?id=' . $a['id']) ?>">
                            <div class="class-icon" style="width:34px;height:34px;font-size:15px"><i class="bi bi-file-earmark-text"></i></div>
                            <div class="list-row-main">
                                <div class="list-row-title"><?= e($a['title']) ?></div>
                                <div class="list-row-sub"><?= e($a['subject']) ?> · due <?= date('j M, g:i A', strtotime($a['deadline'])) ?></div>
                            </div>
                            <span class="badge <?= $hrs < 48 ? 'text-bg-danger' : 'text-bg-warning' ?>"><?= $hrs < 24 ? 'Due today' : ($hrs < 48 ? 'Due tomorrow' : ceil($hrs / 24) . ' days left') ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <?= empty_state('check2-circle', 'You\'re all caught up', 'There are no assignments waiting for you.') ?>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
    <div class="col-lg-5">
        <?php require __DIR__ . '/notices_widget.php'; ?>
    </div>
</div>
