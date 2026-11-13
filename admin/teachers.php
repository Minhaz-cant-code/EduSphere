<?php
/**
 * Teacher list (FR-TCH-1, FR-TCH-3)
 */
require_once __DIR__ . '/../includes/functions.php';
require_role('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $st = $pdo->prepare('SELECT user_id FROM teachers WHERE id = ?');
    $st->execute([(int)post('id')]);
    $uid = $st->fetchColumn();
    if ($uid) {
        if (post('action') === 'toggle') {
            $pdo->prepare("UPDATE users SET status = CASE WHEN status = 'active' THEN 'inactive' ELSE 'active' END WHERE id = ?")
                ->execute([$uid]);
            flash('success', 'Teacher status updated.');
        } elseif (post('action') === 'delete') {
            try {
                $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$uid]);
                flash('success', 'Teacher removed.');
            } catch (PDOException $ex) {
                flash('danger', 'Could not delete this teacher. Deactivate the account instead.');
            }
        }
    }
    redirect('admin/teachers.php');
}

// Teachers with the list of subjects they teach
$teachers = $pdo->query(
    'SELECT t.*, u.name, u.email, u.username, u.status FROM teachers t JOIN users u ON u.id = t.user_id ORDER BY u.name'
)->fetchAll();

$st = $pdo->prepare(
    'SELECT s.name, b.name AS batch FROM teacher_subjects ts
       JOIN subjects s ON s.id = ts.subject_id JOIN batches b ON b.id = s.batch_id
      WHERE ts.teacher_id = ? ORDER BY b.name, s.name'
);
foreach ($teachers as &$t) {
    $st->execute([$t['id']]);
    $t['subjects'] = $st->fetchAll();
}
unset($t);

$total_salary = array_sum(array_map(fn($t) => $t['status'] === 'active' ? $t['salary'] : 0, $teachers));

$page_title = 'Teachers';
require __DIR__ . '/../includes/header.php';
?>
<?php page_head('Teachers', 'Teaching staff, their subjects and monthly salary.',
    '<a href="' . url('admin/teacher_form.php') . '" class="btn btn-brand"><i class="bi bi-person-plus"></i> Add teacher</a>'); ?>

<div class="card">
    <div class="table-toolbar">
        <span class="table-count"><strong><?= count($teachers) ?></strong> teacher<?= count($teachers) === 1 ? '' : 's' ?></span>
        <div class="search-input"><i class="bi bi-search"></i>
            <input type="search" class="form-control form-control-sm" placeholder="Search teachers…" data-table-search="#teacherTable" aria-label="Search teachers">
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-hover table-stack" id="teacherTable">
            <thead>
            <tr><th>Teacher</th><th>Subjects</th><th>Phone</th><th class="num">Salary</th><th>Status</th><th class="text-end">Actions</th></tr>
            </thead>
            <tbody>
            <?php foreach ($teachers as $t): ?>
                <tr>
                    <td>
                        <div class="person">
                            <?= avatar($t['name']) ?>
                            <div class="min-w-0">
                                <a class="person-name" href="<?= url('admin/teacher_form.php?id=' . $t['id']) ?>"><?= e($t['name']) ?></a>
                                <div class="person-sub"><?= e($t['qualification'] ?: $t['email']) ?></div>
                            </div>
                        </div>
                    </td>
                    <td data-label="Subjects">
                        <div class="filter-chip-row">
                            <?php foreach ($t['subjects'] as $s): ?>
                                <span class="badge text-bg-light" title="<?= e($s['batch']) ?>"><?= e($s['name']) ?></span>
                            <?php endforeach; ?>
                            <?php if (!$t['subjects']): ?><span class="text-muted small">Not allocated</span><?php endif; ?>
                        </div>
                    </td>
                    <td data-label="Phone" class="tabular"><?= e($t['phone']) ?: '<span class="text-muted">—</span>' ?></td>
                    <td data-label="Salary" class="num"><?= money($t['salary']) ?></td>
                    <td data-label="Status"><?= status_badge($t['status']) ?></td>
                    <td class="text-end row-actions-cell">
                        <div class="row-actions">
                            <a href="<?= url('admin/teacher_form.php?id=' . $t['id']) ?>" class="btn btn-sm btn-ghost btn-icon is-primary" title="Edit" aria-label="Edit"><i class="bi bi-pencil"></i></a>
                            <form method="post">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= $t['id'] ?>">
                                <button name="action" value="toggle" class="btn btn-sm btn-ghost btn-icon"
                                        title="<?= $t['status'] === 'active' ? 'Deactivate' : 'Activate' ?>" aria-label="<?= $t['status'] === 'active' ? 'Deactivate' : 'Activate' ?>">
                                    <i class="bi bi-<?= $t['status'] === 'active' ? 'pause-circle' : 'play-circle' ?>"></i></button>
                                <button name="action" value="delete" class="btn btn-sm btn-ghost btn-icon is-danger" title="Delete" aria-label="Delete"
                                        data-confirm-title="Remove <?= e($t['name']) ?>?"
                                        data-confirm="Their login and all assignments they uploaded will be deleted. To keep their records, deactivate the account instead."><i class="bi bi-trash"></i></button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$teachers): ?>
                <?= empty_row(6, 'person-badge', 'No teachers yet', 'Add teachers and allocate their subjects so they can mark attendance and enter marks.',
                    '<a class="btn btn-sm btn-brand" href="' . url('admin/teacher_form.php') . '"><i class="bi bi-person-plus"></i> Add teacher</a>') ?>
            <?php endif; ?>
            </tbody>
            <?php if ($teachers): ?>
            <tfoot>
                <tr><td colspan="3" class="text-end text-muted fw-medium">Monthly salary, active teachers</td><td class="num"><?= money($total_salary) ?></td><td colspan="2"></td></tr>
            </tfoot>
            <?php endif; ?>
        </table>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
