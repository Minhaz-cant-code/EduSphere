<?php
/**
 * Assignments list (FR-ASG-1, FR-ASG-2)
 *  - Teacher: own assignments with submission counts
 *  - Admin:   all assignments (view only)
 *  - Student: assignments of own batch with submission status
 */
require_once __DIR__ . '/../includes/functions.php';
require_role('admin', 'teacher', 'student');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action') === 'delete' && role() === 'teacher') {
    verify_csrf();
    $st = $pdo->prepare('SELECT * FROM assignments WHERE id = ? AND teacher_id = ?');
    $st->execute([(int)post('id'), my_teacher_id()]);
    if ($a = $st->fetch()) {
        // remove uploaded files too
        $files = $pdo->prepare('SELECT file_path FROM submissions WHERE assignment_id = ?');
        $files->execute([$a['id']]);
        foreach ($files->fetchAll(PDO::FETCH_COLUMN) as $f) delete_upload($f);
        delete_upload($a['file_path']);
        $pdo->prepare('DELETE FROM assignments WHERE id = ?')->execute([$a['id']]);
        flash('success', 'Assignment deleted.');
    }
    redirect('assignments/index.php');
}

$base = 'SELECT a.*, s.name AS subject, b.name AS batch, u.name AS teacher,
                (SELECT COUNT(*) FROM submissions sb WHERE sb.assignment_id = a.id) AS submitted,
                (SELECT COUNT(*) FROM submissions sb WHERE sb.assignment_id = a.id AND sb.marks IS NULL) AS ungraded,
                (SELECT COUNT(*) FROM students st WHERE st.batch_id = s.batch_id) AS students
           FROM assignments a
           JOIN subjects s ON s.id = a.subject_id
           JOIN batches b ON b.id = s.batch_id
           JOIN teachers t ON t.id = a.teacher_id
           JOIN users u ON u.id = t.user_id';

if (role() === 'teacher') {
    $st = $pdo->prepare($base . ' WHERE a.teacher_id = ? ORDER BY a.deadline DESC');
    $st->execute([my_teacher_id()]);
} elseif (role() === 'admin') {
    $st = $pdo->query($base . ' ORDER BY a.deadline DESC');
} else {
    $sid = my_student_id();
    $st = $pdo->prepare(
        'SELECT a.*, s.name AS subject, u.name AS teacher, sb.submitted_at, sb.marks, sb.feedback
           FROM assignments a
           JOIN subjects s ON s.id = a.subject_id
           JOIN teachers t ON t.id = a.teacher_id
           JOIN users u ON u.id = t.user_id
           LEFT JOIN submissions sb ON sb.assignment_id = a.id AND sb.student_id = ?
          WHERE s.batch_id = (SELECT batch_id FROM students WHERE id = ?)
          ORDER BY a.deadline DESC'
    );
    $st->execute([$sid, $sid]);
}
$assignments = $st->fetchAll();

$page_title = 'Assignments';
require __DIR__ . '/../includes/header.php';
?>
<?php page_head('Assignments',
    role() === 'student' ? 'Download the work, upload your solution before the deadline.' : (role() === 'teacher' ? 'Upload work for your batches and grade submissions.' : 'All assignments across the institute.'),
    role() === 'teacher' ? '<a href="' . url('assignments/form.php') . '" class="btn btn-brand"><i class="bi bi-upload"></i> New assignment</a>' : ''); ?>

<div class="card">
    <div class="table-toolbar"><span class="table-count"><strong><?= count($assignments) ?></strong> assignment<?= count($assignments) === 1 ? '' : 's' ?></span></div>
    <div class="table-responsive">
        <table class="table table-hover table-stack">
            <?php if (role() === 'student'): ?>
                <thead><tr><th>Assignment</th><th>Deadline</th><th>Status</th><th class="num">Marks</th><th class="text-end"></th></tr></thead>
                <tbody>
                <?php foreach ($assignments as $a): $late = $a['deadline'] < now(); ?>
                    <tr>
                        <td><a class="cell-main" href="<?= url('assignments/view.php?id=' . $a['id']) ?>"><?= e($a['title']) ?></a>
                            <div class="cell-sub"><?= e($a['subject']) ?> · <?= e($a['teacher']) ?></div></td>
                        <td data-label="Deadline" class="text-nowrap <?= !$late && !$a['submitted_at'] ? 'text-dark fw-medium' : 'text-muted' ?>"><?= date('j M Y, g:i A', strtotime($a['deadline'])) ?></td>
                        <td data-label="Status">
                            <?php if ($a['submitted_at']): ?><span class="badge dot text-bg-success">Submitted</span>
                            <?php elseif ($late): ?><span class="badge dot text-bg-danger">Missed</span>
                            <?php else: ?><span class="badge dot text-bg-warning">Pending</span><?php endif; ?>
                        </td>
                        <td class="num" data-label="Marks"><?= $a['marks'] !== null ? '<span class="fw-semibold text-dark">' . (float)$a['marks'] . '</span>' : '<span class="text-muted">—</span>' ?></td>
                        <td class="text-end row-actions-cell"><a href="<?= url('assignments/view.php?id=' . $a['id']) ?>" class="btn btn-sm btn-light">Open <i class="bi bi-arrow-right"></i></a></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$assignments): ?><?= empty_row(5, 'file-earmark-text', 'No assignments yet', 'Your teachers\' assignments will appear here.') ?><?php endif; ?>
                </tbody>
            <?php else: ?>
                <thead><tr><th>Assignment</th><?php if (role() === 'admin'): ?><th>Teacher</th><?php endif; ?><th>Deadline</th><th>Submitted</th><th>To grade</th><th class="text-end">Actions</th></tr></thead>
                <tbody>
                <?php foreach ($assignments as $a): $open = $a['deadline'] >= now(); ?>
                    <tr>
                        <td><a class="cell-main" href="<?= url('assignments/submissions.php?id=' . $a['id']) ?>"><?= e($a['title']) ?></a>
                            <?php if ($a['file_path']): ?><a href="<?= url('download.php?type=assignment&id=' . $a['id']) ?>" class="ms-1 text-muted" title="Download file" aria-label="Download file"><i class="bi bi-paperclip"></i></a><?php endif; ?>
                            <div class="cell-sub"><?= e($a['batch']) ?> · <?= e($a['subject']) ?></div></td>
                        <?php if (role() === 'admin'): ?><td data-label="Teacher"><?= e($a['teacher']) ?></td><?php endif; ?>
                        <td data-label="Deadline" class="text-nowrap"><?= date('j M, g:i A', strtotime($a['deadline'])) ?>
                            <div class="cell-sub"><?= $open ? 'Open' : 'Closed' ?></div></td>
                        <td data-label="Submitted" style="min-width:130px">
                            <?php $pct = $a['students'] ? min(100, round($a['submitted'] / $a['students'] * 100)) : 0; ?>
                            <div class="d-flex align-items-center gap-2"><div class="meter flex-grow-1" style="max-width:90px"><span style="width:<?= $pct ?>%"></span></div>
                                <span class="small tabular text-muted"><?= (int)$a['submitted'] ?>/<?= (int)$a['students'] ?></span></div>
                        </td>
                        <td data-label="To grade"><?= $a['ungraded'] ? '<span class="badge text-bg-warning">' . (int)$a['ungraded'] . ' waiting</span>' : '<span class="text-muted small">None</span>' ?></td>
                        <td class="text-end row-actions-cell">
                            <div class="row-actions">
                                <a href="<?= url('assignments/submissions.php?id=' . $a['id']) ?>" class="btn btn-sm btn-light"><i class="bi bi-inbox"></i> Submissions</a>
                                <?php if (role() === 'teacher'): ?>
                                    <a href="<?= url('assignments/form.php?id=' . $a['id']) ?>" class="btn btn-sm btn-ghost btn-icon is-primary" title="Edit" aria-label="Edit"><i class="bi bi-pencil"></i></a>
                                    <form method="post">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="id" value="<?= $a['id'] ?>">
                                        <button name="action" value="delete" class="btn btn-sm btn-ghost btn-icon is-danger" title="Delete" aria-label="Delete"
                                                data-confirm-title="Delete this assignment?" data-confirm="All student submissions and their files will be deleted too."><i class="bi bi-trash"></i></button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$assignments): ?>
                    <?= empty_row(6, 'file-earmark-text', 'No assignments yet', role() === 'teacher' ? 'Upload a PDF or write instructions for a batch.' : 'Assignments uploaded by teachers will appear here.',
                        role() === 'teacher' ? '<a class="btn btn-sm btn-brand" href="' . url('assignments/form.php') . '"><i class="bi bi-upload"></i> New assignment</a>' : '') ?>
                <?php endif; ?>
                </tbody>
            <?php endif; ?>
        </table>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
