<?php
/**
 * Exam list (FR-EXM-1) - Admin sees all, Teacher sees own subjects
 */
require_once __DIR__ . '/../includes/functions.php';
require_role('admin', 'teacher');

$subjects = manageable_subjects();
$allowed  = array_map('intval', array_column($subjects, 'id'));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $st = $pdo->prepare('SELECT subject_id FROM exams WHERE id = ?');
    $st->execute([(int)post('id')]);
    $sub = (int)$st->fetchColumn();
    if ($sub && in_array($sub, $allowed, true)) {
        if (post('action') === 'publish') {
            $pdo->prepare('UPDATE exams SET is_published = 1 - is_published WHERE id = ?')->execute([(int)post('id')]);
            flash('success', 'Result publish status changed.');
        } elseif (post('action') === 'delete') {
            $pdo->prepare('DELETE FROM exams WHERE id = ?')->execute([(int)post('id')]);
            flash('success', 'Exam and its results deleted.');
        }
    }
    redirect('exams/index.php');
}

$exams = [];
if ($allowed) {
    $in = implode(',', $allowed); // integers only, safe to inline
    $exams = $pdo->query(
        "SELECT e.*, s.name AS subject, b.name AS batch, b.id AS batch_id,
                (SELECT COUNT(*) FROM results r WHERE r.exam_id = e.id) AS marked,
                (SELECT COUNT(*) FROM students st WHERE st.batch_id = s.batch_id) AS students
           FROM exams e JOIN subjects s ON s.id = e.subject_id JOIN batches b ON b.id = s.batch_id
          WHERE e.subject_id IN ($in)
          ORDER BY e.exam_date DESC"
    )->fetchAll();
}
$batches = manageable_batches();

$page_title = 'Exams';
require __DIR__ . '/../includes/header.php';
ob_start(); ?>
    <div class="dropdown">
        <button class="btn btn-light dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false"><i class="bi bi-table"></i> Batch result sheet</button>
        <ul class="dropdown-menu dropdown-menu-end">
            <?php foreach ($batches as $b): ?>
                <li><a class="dropdown-item" href="<?= url('exams/result_sheet.php?batch_id=' . $b['id']) ?>"><i class="bi bi-collection"></i> <?= e($b['name']) ?></a></li>
            <?php endforeach; ?>
            <?php if (!$batches): ?><li><span class="dropdown-item-text small text-muted px-3">No batches</span></li><?php endif; ?>
        </ul>
    </div>
    <?php if ($subjects): ?>
        <a href="<?= url('exams/form.php') ?>" class="btn btn-brand"><i class="bi bi-plus-lg"></i> Create exam</a>
    <?php endif; ?>
<?php $actions = ob_get_clean(); ?>
<?php page_head('Exams & results', 'Create exams, enter marks and publish results. Grades and GPA are calculated for you.', $actions); ?>

<div class="card">
    <div class="table-toolbar">
        <span class="table-count"><strong><?= count($exams) ?></strong> exam<?= count($exams) === 1 ? '' : 's' ?></span>
        <div class="search-input"><i class="bi bi-search"></i>
            <input type="search" class="form-control form-control-sm" placeholder="Search exams…" data-table-search="#examTable" aria-label="Search exams"></div>
    </div>
    <div class="table-responsive">
        <table class="table table-hover table-stack" id="examTable">
            <thead><tr><th>Exam</th><th>Date</th><th>Marks entered</th><th>Result</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
            <?php foreach ($exams as $ex): $pct = $ex['students'] ? min(100, round($ex['marked'] / $ex['students'] * 100)) : 0; ?>
                <tr>
                    <td>
                        <a class="cell-main" href="<?= url('exams/marks.php?id=' . $ex['id']) ?>"><?= e($ex['exam_name']) ?></a>
                        <span class="badge text-bg-light ms-1"><?= e($ex['exam_type']) ?></span>
                        <div class="cell-sub"><?= e($ex['batch']) ?> · <?= e($ex['subject']) ?> · out of <?= (int)$ex['total_marks'] ?></div>
                    </td>
                    <td data-label="Date" class="text-nowrap"><?= fmt_date($ex['exam_date']) ?></td>
                    <td data-label="Marks entered" style="min-width:150px">
                        <div class="d-flex align-items-center gap-2">
                            <div class="meter flex-grow-1 <?= $pct >= 100 ? 'is-success' : '' ?>" style="max-width:110px"><span style="width:<?= $pct ?>%"></span></div>
                            <span class="small tabular text-muted"><?= (int)$ex['marked'] ?>/<?= (int)$ex['students'] ?></span>
                        </div>
                    </td>
                    <td data-label="Result"><?= $ex['is_published'] ? '<span class="badge dot text-bg-success">Published</span>' : '<span class="badge dot text-bg-secondary">Draft</span>' ?></td>
                    <td class="text-end row-actions-cell">
                        <div class="row-actions">
                            <a href="<?= url('exams/marks.php?id=' . $ex['id']) ?>" class="btn btn-sm btn-light"><i class="bi bi-pencil-square"></i> Marks</a>
                            <a href="<?= url('exams/result_sheet.php?id=' . $ex['id']) ?>" class="btn btn-sm btn-ghost btn-icon" title="Result sheet" aria-label="Result sheet"><i class="bi bi-list-ol"></i></a>
                            <a href="<?= url('exams/form.php?id=' . $ex['id']) ?>" class="btn btn-sm btn-ghost btn-icon is-primary" title="Edit exam" aria-label="Edit exam"><i class="bi bi-gear"></i></a>
                            <form method="post">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= $ex['id'] ?>">
                                <button name="action" value="publish" class="btn btn-sm btn-ghost btn-icon"
                                        title="<?= $ex['is_published'] ? 'Unpublish result' : 'Publish result' ?>" aria-label="<?= $ex['is_published'] ? 'Unpublish result' : 'Publish result' ?>">
                                    <i class="bi bi-<?= $ex['is_published'] ? 'eye-slash' : 'send' ?>"></i></button>
                                <button name="action" value="delete" class="btn btn-sm btn-ghost btn-icon is-danger" title="Delete" aria-label="Delete"
                                        data-confirm-title="Delete <?= e($ex['exam_name']) ?>?" data-confirm="All marks entered for this exam will be deleted too."><i class="bi bi-trash"></i></button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$exams): ?>
                <?= empty_row(5, 'journal-check', 'No exams yet', $subjects ? 'Create an exam, then enter marks for each student.' : 'No subjects are allocated to you yet.',
                    $subjects ? '<a class="btn btn-sm btn-brand" href="' . url('exams/form.php') . '"><i class="bi bi-plus-lg"></i> Create exam</a>' : '') ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
