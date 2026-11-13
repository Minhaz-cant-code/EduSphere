<?php
/**
 * Student: download assignment and upload solution (FR-ASG-2)
 * Use case: Submit Assignment <<extend>> Upload Solution File
 */
require_once __DIR__ . '/../includes/functions.php';
require_role('student');

$sid = my_student_id();
$id  = get_int('id', (int)post('id'));

$st = $pdo->prepare(
    'SELECT a.*, s.name AS subject, u.name AS teacher
       FROM assignments a JOIN subjects s ON s.id = a.subject_id
       JOIN teachers t ON t.id = a.teacher_id JOIN users u ON u.id = t.user_id
      WHERE a.id = ? AND s.batch_id = (SELECT batch_id FROM students WHERE id = ?)'
);
$st->execute([$id, $sid]);
$a = $st->fetch();
if (!$a) {
    flash('danger', 'Assignment not found.');
    redirect('assignments/index.php');
}

$st = $pdo->prepare('SELECT * FROM submissions WHERE assignment_id = ? AND student_id = ?');
$st->execute([$id, $sid]);
$sub = $st->fetch();

$open = $a['deadline'] >= now();
$graded = $sub && $sub['marks'] !== null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (!$open) {
        flash('danger', 'The deadline has passed. Submissions are closed.');
    } elseif ($graded) {
        flash('danger', 'Your submission is already graded and cannot be changed.');
    } else {
        try {
            $file = save_upload('solution', 'submissions', ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'zip']);
            if (!$file) {
                flash('danger', 'Please choose a file to upload.');
            } else {
                if ($sub) {
                    delete_upload($sub['file_path']);
                    $pdo->prepare('UPDATE submissions SET file_path = ?, submitted_at = ? WHERE id = ?')
                        ->execute([$file, now(), $sub['id']]);
                    flash('success', 'Your solution was re-submitted.');
                } else {
                    $pdo->prepare('INSERT INTO submissions (assignment_id, student_id, file_path, submitted_at) VALUES (?,?,?,?)')
                        ->execute([$id, $sid, $file, now()]);
                    flash('success', 'Your solution was submitted successfully.');
                }
            }
        } catch (RuntimeException $ex) {
            flash('danger', $ex->getMessage());
        }
    }
    redirect('assignments/view.php?id=' . $id);
}

$page_title = $a['title'];
require __DIR__ . '/../includes/header.php';
$hrs_left = (strtotime($a['deadline']) - time()) / 3600;
?>
<?php page_head($a['title'], e($a['subject']) . ' · ' . e($a['teacher']), '', ['All assignments', 'assignments/index.php']); ?>
<div class="row g-4">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-body">
                <div class="d-flex flex-wrap gap-4 mb-4">
                    <div><div class="small text-muted">Deadline</div>
                        <div class="fw-semibold text-dark"><?= date('l, j F Y · g:i A', strtotime($a['deadline'])) ?></div></div>
                    <div><div class="small text-muted">Time left</div>
                        <div><?= $open ? '<span class="badge dot ' . ($hrs_left < 48 ? 'text-bg-danger' : 'text-bg-warning') . '">' . ($hrs_left < 24 ? 'Less than a day' : ceil($hrs_left / 24) . ' days') . '</span>'
                                       : '<span class="badge dot text-bg-secondary">Closed</span>' ?></div></div>
                </div>
                <h2 class="section-title mb-2">Instructions</h2>
                <?php if ($a['description']): ?>
                    <div class="text-body mb-4" style="max-width:70ch"><?= nl2br(e($a['description'])) ?></div>
                <?php else: ?>
                    <p class="text-muted">See the attached file.</p>
                <?php endif; ?>
                <?php if ($a['file_path']): ?>
                    <a href="<?= url('download.php?type=assignment&id=' . $a['id']) ?>" class="list-row border rounded-3" style="max-width:420px">
                        <div class="class-icon" style="background:var(--es-danger-soft);color:var(--es-danger)"><i class="bi bi-file-earmark-pdf"></i></div>
                        <div class="list-row-main"><div class="list-row-title">Assignment file</div><div class="list-row-sub">PDF · open or download</div></div>
                        <i class="bi bi-download text-muted"></i>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header"><h2 class="section-title">Your submission</h2>
                <?= $sub ? '<span class="badge dot text-bg-success">Submitted</span>' : ($open ? '<span class="badge dot text-bg-warning">Not submitted</span>' : '<span class="badge dot text-bg-danger">Missed</span>') ?></div>
            <div class="card-body">
                <?php if ($sub): ?>
                    <a href="<?= url('download.php?type=submission&id=' . $sub['id']) ?>" class="list-row border rounded-3 mb-3">
                        <div class="class-icon"><i class="bi bi-paperclip"></i></div>
                        <div class="list-row-main"><div class="list-row-title">Your file</div>
                            <div class="list-row-sub">Uploaded <?= date('j M, g:i A', strtotime($sub['submitted_at'])) ?></div></div>
                    </a>
                    <?php if ($graded): ?>
                        <div class="alert alert-success mb-0"><i class="bi bi-award"></i>
                            <div><strong>Marks: <?= (float)$sub['marks'] ?></strong>
                                <?php if ($sub['feedback']): ?><div class="mt-1">“<?= e($sub['feedback']) ?>”</div><?php endif; ?></div></div>
                    <?php elseif ($open): ?>
                        <p class="small text-muted mb-0">Not graded yet. You can replace your file until the deadline.</p>
                    <?php endif; ?>
                <?php elseif (!$open): ?>
                    <?= empty_state('clock-history', 'The deadline has passed', 'Submissions for this assignment are closed.', '', true) ?>
                <?php endif; ?>

                <?php if ($open && !$graded): ?>
                    <form method="post" enctype="multipart/form-data" class="<?= $sub ? 'mt-3 pt-3 border-top' : '' ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= $id ?>">
                        <label class="form-label" for="solution"><?= $sub ? 'Replace your solution' : 'Upload your solution' ?></label>
                        <input type="file" name="solution" id="solution" class="form-control mb-2" required>
                        <span class="form-hint mb-3">PDF, Word, image or ZIP · up to <?= MAX_UPLOAD_MB ?> MB</span>
                        <button class="btn btn-brand w-100"><i class="bi bi-upload"></i> <?= $sub ? 'Re-submit' : 'Submit solution' ?></button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
