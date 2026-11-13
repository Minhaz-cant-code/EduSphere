<?php
/**
 * Batch management (FR-BAT-1) - add, edit and delete on one page
 */
require_once __DIR__ . '/../includes/functions.php';
require_role('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = post('action');
    $id     = (int)post('id');

    if ($action === 'save') {
        $name = post('name');
        $session = post('session');
        $desc = post('description');
        if ($name === '' || $session === '') {
            flash('danger', 'Batch name and session are required.');
        } elseif ($id) {
            $pdo->prepare('UPDATE batches SET name = ?, session = ?, description = ? WHERE id = ?')
                ->execute([$name, $session, $desc, $id]);
            flash('success', 'Batch updated.');
        } else {
            $pdo->prepare('INSERT INTO batches (name, session, description) VALUES (?,?,?)')
                ->execute([$name, $session, $desc]);
            flash('success', 'Batch created. Now add its subjects.');
        }
    } elseif ($action === 'delete') {
        $st = $pdo->prepare('SELECT COUNT(*) FROM students WHERE batch_id = ?');
        $st->execute([$id]);
        if ($st->fetchColumn() > 0) {
            flash('danger', 'This batch still has students. Move them to another batch first.');
        } else {
            $pdo->prepare('DELETE FROM batches WHERE id = ?')->execute([$id]);
            flash('success', 'Batch deleted.');
        }
    }
    redirect('admin/batches.php');
}

$edit = ['id' => 0, 'name' => '', 'session' => date('Y'), 'description' => ''];
if ($eid = get_int('edit')) {
    $st = $pdo->prepare('SELECT * FROM batches WHERE id = ?');
    $st->execute([$eid]);
    $edit = $st->fetch() ?: $edit;
}

$batches = $pdo->query(
    'SELECT b.*,
            (SELECT COUNT(*) FROM students s WHERE s.batch_id = b.id) AS students,
            (SELECT COUNT(*) FROM subjects s WHERE s.batch_id = b.id) AS subjects
       FROM batches b ORDER BY b.session DESC, b.name'
)->fetchAll();

$page_title = 'Batches';
require __DIR__ . '/../includes/header.php';
?>
<?php page_head('Batches', 'Groups of students who study together. Each batch has its own subjects.'); ?>
<div class="row g-4">
    <div class="col-lg-4">
        <form method="post" class="card needs-validation" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?= (int)$edit['id'] ?>">
            <div class="form-section">
                <div class="form-section-head"><h2><?= $edit['id'] ? 'Edit batch' : 'New batch' ?></h2>
                    <p><?= $edit['id'] ? 'Changes apply to all students in this batch.' : 'Add subjects to it after saving.' ?></p></div>
                <div class="mb-3"><label class="form-label" for="name">Batch name<span class="req">*</span></label>
                    <input id="name" name="name" class="form-control" value="<?= e($edit['name']) ?>" placeholder="e.g. HSC Science - Batch A" required>
                    <div class="invalid-feedback">Enter a name.</div></div>
                <div class="mb-3"><label class="form-label" for="session">Session<span class="req">*</span></label>
                    <input id="session" name="session" class="form-control" value="<?= e($edit['session']) ?>" required></div>
                <div><label class="form-label" for="description">Description</label>
                    <input id="description" name="description" class="form-control" value="<?= e($edit['description']) ?>" placeholder="Optional"></div>
            </div>
            <div class="form-actions">
                <button class="btn btn-brand"><i class="bi bi-check-lg"></i> <?= $edit['id'] ? 'Save changes' : 'Create batch' ?></button>
                <?php if ($edit['id']): ?><a href="<?= url('admin/batches.php') ?>" class="btn btn-light">Cancel</a><?php endif; ?>
            </div>
        </form>
    </div>
    <div class="col-lg-8">
        <div class="card">
            <div class="table-toolbar"><span class="table-count"><strong><?= count($batches) ?></strong> batch<?= count($batches) === 1 ? '' : 'es' ?></span></div>
            <div class="table-responsive">
                <table class="table table-hover table-stack">
                    <thead><tr><th>Batch</th><th>Session</th><th class="num">Students</th><th class="num">Subjects</th><th class="text-end">Actions</th></tr></thead>
                    <tbody>
                    <?php foreach ($batches as $b): ?>
                        <tr class="<?= (int)$edit['id'] === (int)$b['id'] ? 'table-active' : '' ?>">
                            <td><div class="cell-main"><?= e($b['name']) ?></div><?php if ($b['description']): ?><div class="cell-sub"><?= e($b['description']) ?></div><?php endif; ?></td>
                            <td data-label="Session"><span class="badge text-bg-light"><?= e($b['session']) ?></span></td>
                            <td class="num" data-label="Students"><a href="<?= url('admin/students.php?batch_id=' . $b['id']) ?>"><?= $b['students'] ?></a></td>
                            <td class="num" data-label="Subjects"><a href="<?= url('admin/subjects.php?batch_id=' . $b['id']) ?>"><?= $b['subjects'] ?></a></td>
                            <td class="text-end row-actions-cell">
                                <div class="row-actions">
                                    <a href="?edit=<?= $b['id'] ?>" class="btn btn-sm btn-ghost btn-icon is-primary" title="Edit" aria-label="Edit"><i class="bi bi-pencil"></i></a>
                                    <form method="post">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="id" value="<?= $b['id'] ?>">
                                        <button name="action" value="delete" class="btn btn-sm btn-ghost btn-icon is-danger" title="Delete" aria-label="Delete"
                                                data-confirm-title="Delete <?= e($b['name']) ?>?"
                                                data-confirm="Its subjects, exams, results and assignments will also be deleted. Batches that still have students cannot be deleted."><i class="bi bi-trash"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$batches): ?><?= empty_row(5, 'collection', 'No batches yet', 'Create your first batch using the form.') ?><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
