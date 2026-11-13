<?php
/**
 * Subject management - each subject belongs to one batch (FR-BAT-3)
 * Teachers are allocated to subjects from the teacher form.
 */
require_once __DIR__ . '/../includes/functions.php';
require_role('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $id = (int)post('id');
    if (post('action') === 'save') {
        $batch_id = (int)post('batch_id');
        $name = post('name');
        $code = post('code');
        if (!$batch_id || $name === '') {
            flash('danger', 'Batch and subject name are required.');
        } elseif ($id) {
            $pdo->prepare('UPDATE subjects SET batch_id = ?, name = ?, code = ? WHERE id = ?')->execute([$batch_id, $name, $code, $id]);
            flash('success', 'Subject updated.');
        } else {
            $pdo->prepare('INSERT INTO subjects (batch_id, name, code) VALUES (?,?,?)')->execute([$batch_id, $name, $code]);
            flash('success', 'Subject added.');
        }
    } elseif (post('action') === 'delete') {
        $pdo->prepare('DELETE FROM subjects WHERE id = ?')->execute([$id]);
        flash('success', 'Subject deleted.');
    }
    redirect('admin/subjects.php');
}

$filter = get_int('batch_id');
$edit = ['id' => 0, 'batch_id' => $filter, 'name' => '', 'code' => ''];
if ($eid = get_int('edit')) {
    $st = $pdo->prepare('SELECT * FROM subjects WHERE id = ?');
    $st->execute([$eid]);
    $edit = $st->fetch() ?: $edit;
}

$batches = $pdo->query('SELECT id, name FROM batches ORDER BY name')->fetchAll();

$sql = 'SELECT s.*, b.name AS batch FROM subjects s JOIN batches b ON b.id = s.batch_id';
$params = [];
if ($filter) {
    $sql .= ' WHERE s.batch_id = ?';
    $params[] = $filter;
}
$st = $pdo->prepare($sql . ' ORDER BY b.name, s.name');
$st->execute($params);
$subjects = $st->fetchAll();

// Teacher names per subject
$tq = $pdo->prepare('SELECT u.name FROM teacher_subjects ts JOIN teachers t ON t.id = ts.teacher_id JOIN users u ON u.id = t.user_id WHERE ts.subject_id = ?');
foreach ($subjects as &$s) {
    $tq->execute([$s['id']]);
    $s['teachers'] = $tq->fetchAll(PDO::FETCH_COLUMN);
}
unset($s);

$page_title = 'Subjects';
require __DIR__ . '/../includes/header.php';
?>
<?php page_head('Subjects', 'Each subject belongs to one batch. Allocate teachers from the teacher\'s profile.'); ?>
<div class="row g-4">
    <div class="col-lg-4">
        <?php if (!$batches): ?>
            <div class="card"><?= empty_state('collection', 'Create a batch first', 'Subjects are added to a batch.', '<a class="btn btn-sm btn-brand" href="' . url('admin/batches.php') . '">Go to batches</a>') ?></div>
        <?php else: ?>
        <form method="post" class="card needs-validation" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?= (int)$edit['id'] ?>">
            <div class="form-section">
                <div class="form-section-head"><h2><?= $edit['id'] ? 'Edit subject' : 'New subject' ?></h2></div>
                <div class="mb-3"><label class="form-label" for="batch_id">Batch<span class="req">*</span></label>
                    <select id="batch_id" name="batch_id" class="form-select" required>
                        <option value="">Select batch</option>
                        <?php foreach ($batches as $b): ?>
                            <option value="<?= $b['id'] ?>" <?= $edit['batch_id'] == $b['id'] ? 'selected' : '' ?>><?= e($b['name']) ?></option>
                        <?php endforeach; ?>
                    </select></div>
                <div class="mb-3"><label class="form-label" for="name">Subject name<span class="req">*</span></label>
                    <input id="name" name="name" class="form-control" value="<?= e($edit['name']) ?>" required>
                    <div class="invalid-feedback">Enter a name.</div></div>
                <div><label class="form-label" for="code">Code</label>
                    <input id="code" name="code" class="form-control" value="<?= e($edit['code']) ?>" placeholder="e.g. PHY"></div>
            </div>
            <div class="form-actions">
                <button class="btn btn-brand"><i class="bi bi-check-lg"></i> <?= $edit['id'] ? 'Save changes' : 'Add subject' ?></button>
                <?php if ($edit['id']): ?><a href="<?= url('admin/subjects.php') ?>" class="btn btn-light">Cancel</a><?php endif; ?>
            </div>
        </form>
        <?php endif; ?>
    </div>
    <div class="col-lg-8">
        <div class="card">
            <div class="table-toolbar">
                <span class="table-count"><strong><?= count($subjects) ?></strong> subject<?= count($subjects) === 1 ? '' : 's' ?></span>
                <form class="filter-form m-0">
                    <select name="batch_id" class="form-select form-select-sm" onchange="this.form.submit()" aria-label="Filter by batch">
                        <option value="">All batches</option>
                        <?php foreach ($batches as $b): ?>
                            <option value="<?= $b['id'] ?>" <?= $filter == $b['id'] ? 'selected' : '' ?>><?= e($b['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>
            <div class="table-responsive">
                <table class="table table-hover table-stack">
                    <thead><tr><th>Subject</th><th>Batch</th><th>Teacher(s)</th><th class="text-end">Actions</th></tr></thead>
                    <tbody>
                    <?php foreach ($subjects as $s): ?>
                        <tr class="<?= (int)$edit['id'] === (int)$s['id'] ? 'table-active' : '' ?>">
                            <td><span class="cell-main"><?= e($s['name']) ?></span> <?php if ($s['code']): ?><span class="badge text-bg-light ms-1"><?= e($s['code']) ?></span><?php endif; ?></td>
                            <td data-label="Batch"><?= e($s['batch']) ?></td>
                            <td data-label="Teacher(s)"><?= $s['teachers'] ? e(implode(', ', $s['teachers'])) : '<span class="badge text-bg-warning">Not assigned</span>' ?></td>
                            <td class="text-end row-actions-cell">
                                <div class="row-actions">
                                    <a href="?edit=<?= $s['id'] ?>" class="btn btn-sm btn-ghost btn-icon is-primary" title="Edit" aria-label="Edit"><i class="bi bi-pencil"></i></a>
                                    <form method="post">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="id" value="<?= $s['id'] ?>">
                                        <button name="action" value="delete" class="btn btn-sm btn-ghost btn-icon is-danger" title="Delete" aria-label="Delete"
                                                data-confirm-title="Delete <?= e($s['name']) ?>?" data-confirm="Its exams, results and assignments will also be deleted."><i class="bi bi-trash"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$subjects): ?><?= empty_row(4, 'book', 'No subjects', $filter ? 'This batch has no subjects yet.' : 'Add subjects using the form.') ?><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
