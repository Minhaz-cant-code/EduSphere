<?php
/**
 * Review & grade submissions (FR-ASG-3) - Teacher (admin can view)
 */
require_once __DIR__ . '/../includes/functions.php';
require_role('teacher', 'admin');

$id = get_int('id', (int)post('id'));
$st = $pdo->prepare('SELECT a.*, s.name AS subject, s.batch_id, b.name AS batch FROM assignments a JOIN subjects s ON s.id = a.subject_id JOIN batches b ON b.id = s.batch_id WHERE a.id = ?');
$st->execute([$id]);
$a = $st->fetch();
if (!$a || (role() === 'teacher' && (int)$a['teacher_id'] !== my_teacher_id())) {
    flash('danger', 'Assignment not found.');
    redirect('assignments/index.php');
}
$can_grade = role() === 'teacher';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $can_grade) {
    verify_csrf();
    $sub_id = (int)post('submission_id');
    $marks  = post('marks');
    if ($marks !== '' && (!is_numeric($marks) || $marks < 0 || $marks > 1000)) {
        flash('danger', 'Enter valid marks.');
    } else {
        $pdo->prepare('UPDATE submissions SET marks = ?, feedback = ? WHERE id = ? AND assignment_id = ?')
            ->execute([$marks === '' ? null : $marks, mb_substr(post('feedback'), 0, 255), $sub_id, $id]);
        flash('success', 'Grade saved.');
    }
    redirect('assignments/submissions.php?id=' . $id);
}

// Every student of the batch, with submission if any
$st = $pdo->prepare(
    "SELECT s.id AS student_id, s.roll_no, u.name, sb.id, sb.submitted_at, sb.marks, sb.feedback
       FROM students s JOIN users u ON u.id = s.user_id
       LEFT JOIN submissions sb ON sb.student_id = s.id AND sb.assignment_id = ?
      WHERE s.batch_id = ? AND u.status = 'active'
      ORDER BY s.roll_no"
);
$st->execute([$id, $a['batch_id']]);
$rows = $st->fetchAll();

$page_title = 'Submissions';
require __DIR__ . '/../includes/header.php';
$submitted = count(array_filter($rows, fn($r) => $r['id']));
$graded_n  = count(array_filter($rows, fn($r) => $r['id'] && $r['marks'] !== null));
?>
<?php page_head($a['title'], e($a['batch']) . ' · ' . e($a['subject']) . ' · deadline ' . date('j M Y, g:i A', strtotime($a['deadline'])), '', ['All assignments', 'assignments/index.php']); ?>

<section class="kpi-strip cols-3" aria-label="Submission summary">
    <div class="kpi"><div class="kpi-label"><span class="kpi-icon is-primary"><i class="bi bi-inbox"></i></span> Submitted</div>
        <div class="kpi-value"><?= $submitted ?><span class="unit">/<?= count($rows) ?></span></div><div class="kpi-meta">students</div></div>
    <div class="kpi"><div class="kpi-label"><span class="kpi-icon is-success"><i class="bi bi-check2-circle"></i></span> Graded</div>
        <div class="kpi-value"><?= $graded_n ?></div><div class="kpi-meta"><?= $submitted - $graded_n ?> waiting for marks</div></div>
    <div class="kpi"><div class="kpi-label"><span class="kpi-icon is-danger"><i class="bi bi-person-x"></i></span> Not submitted</div>
        <div class="kpi-value"><?= count($rows) - $submitted ?></div><div class="kpi-meta"><?= $a['deadline'] < now() ? 'deadline passed' : 'deadline not reached' ?></div></div>
</section>

<div class="card">
    <div class="table-responsive">
        <table class="table table-stack">
            <thead><tr><th>Student</th><th>Submitted</th><th>File</th><th style="min-width:320px">Marks &amp; feedback</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><div class="person"><?= avatar($r['name'], null, 'sm') ?><div class="min-w-0"><div class="person-name"><?= e($r['name']) ?></div><div class="person-sub">Roll <?= e($r['roll_no']) ?></div></div></div></td>
                    <td data-label="Submitted">
                        <?php if ($r['id']): ?>
                            <span class="text-nowrap"><?= date('j M, g:i A', strtotime($r['submitted_at'])) ?></span>
                            <?php if ($r['submitted_at'] > $a['deadline']): ?><span class="badge text-bg-warning ms-1">Late</span><?php endif; ?>
                        <?php else: ?><span class="badge dot text-bg-secondary">Not submitted</span><?php endif; ?>
                    </td>
                    <td data-label="File"><?php if ($r['id']): ?><a href="<?= url('download.php?type=submission&id=' . $r['id']) ?>" class="btn btn-sm btn-light"><i class="bi bi-download"></i> Open</a><?php else: ?><span class="text-muted">—</span><?php endif; ?></td>
                    <td data-label="Grade">
                        <?php if ($r['id'] && $can_grade): ?>
                            <form method="post" class="d-flex gap-2 align-items-center">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= $id ?>">
                                <input type="hidden" name="submission_id" value="<?= $r['id'] ?>">
                                <input type="number" step="0.5" min="0" name="marks" class="form-control form-control-sm" style="width:84px" placeholder="Marks" aria-label="Marks" value="<?= $r['marks'] !== null ? (float)$r['marks'] : '' ?>">
                                <input name="feedback" class="form-control form-control-sm" placeholder="Feedback (optional)" aria-label="Feedback" value="<?= e($r['feedback']) ?>">
                                <button class="btn btn-sm <?= $r['marks'] !== null ? 'btn-light' : 'btn-success' ?> btn-icon" title="Save grade" aria-label="Save grade"><i class="bi bi-check-lg"></i></button>
                            </form>
                        <?php elseif ($r['id']): ?>
                            <?= $r['marks'] !== null ? '<span class="fw-semibold text-dark">' . (float)$r['marks'] . '</span>' : '<span class="text-muted">Not graded</span>' ?>
                            <span class="small text-muted"><?= e($r['feedback']) ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?><?= empty_row(4, 'people', 'No students in this batch') ?><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
