<?php
/**
 * Student list with search & filter (FR-STU-3, FR-STU-4)
 */
require_once __DIR__ . '/../includes/functions.php';
require_role('admin');

// ---- Actions: activate/deactivate or delete ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $sid = (int)post('id');
    $st = $pdo->prepare('SELECT user_id, photo FROM students WHERE id = ?');
    $st->execute([$sid]);
    $s = $st->fetch();
    if ($s) {
        if (post('action') === 'toggle') {
            $pdo->prepare("UPDATE users SET status = CASE WHEN status = 'active' THEN 'inactive' ELSE 'active' END WHERE id = ?")
                ->execute([$s['user_id']]);
            flash('success', 'Student status updated.');
        } elseif (post('action') === 'delete') {
            // Deleting the user cascades to student, attendance, fees, results...
            $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$s['user_id']]);
            delete_upload($s['photo']);
            flash('success', 'Student deleted permanently.');
        }
    }
    redirect('admin/students.php');
}

// ---- Search / filter ----
$q        = trim($_GET['q'] ?? '');
$batch_id = get_int('batch_id');
$status   = $_GET['status'] ?? '';

$sql = 'SELECT s.*, u.name, u.email, u.username, u.status, b.name AS batch_name
          FROM students s
          JOIN users u ON u.id = s.user_id
          LEFT JOIN batches b ON b.id = s.batch_id
         WHERE 1 = 1';
$params = [];
if ($q !== '') {
    $sql .= ' AND (u.name LIKE ? OR s.roll_no LIKE ? OR s.id = ? OR s.phone LIKE ?)';
    array_push($params, "%$q%", "%$q%", (int)$q, "%$q%");
}
if ($batch_id) {
    $sql .= ' AND s.batch_id = ?';
    $params[] = $batch_id;
}
if (in_array($status, ['active', 'inactive'], true)) {
    $sql .= ' AND u.status = ?';
    $params[] = $status;
}
$sql .= ' ORDER BY b.name, s.roll_no';
$st = $pdo->prepare($sql);
$st->execute($params);
$students = $st->fetchAll();

$batches = $pdo->query('SELECT id, name FROM batches ORDER BY name')->fetchAll();

$page_title = 'Students';
require __DIR__ . '/../includes/header.php';
?>
<?php page_head('Students', 'Everyone enrolled at the institute, across all batches.',
    '<a href="' . url('admin/student_form.php') . '" class="btn btn-brand"><i class="bi bi-person-plus"></i> Add student</a>'); ?>

<form class="filter-bar filter-form" role="search">
    <div class="grow">
        <label class="form-label" for="q">Search</label>
        <div class="search-input"><i class="bi bi-search"></i>
            <input type="search" id="q" name="q" value="<?= e($q) ?>" class="form-control" placeholder="Name, roll no, ID or phone">
        </div>
    </div>
    <div class="fixed">
        <label class="form-label" for="batch_id">Batch</label>
        <select name="batch_id" id="batch_id" class="form-select">
            <option value="">All batches</option>
            <?php foreach ($batches as $b): ?>
                <option value="<?= $b['id'] ?>" <?= $batch_id == $b['id'] ? 'selected' : '' ?>><?= e($b['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="fixed" style="flex-basis:150px">
        <label class="form-label" for="status">Status</label>
        <select name="status" id="status" class="form-select">
            <option value="">Any status</option>
            <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
            <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inactive</option>
        </select>
    </div>
    <button class="btn btn-light"><i class="bi bi-funnel"></i> Apply</button>
    <?php if ($q !== '' || $batch_id || $status): ?>
        <a href="<?= url('admin/students.php') ?>" class="btn btn-ghost">Clear</a>
    <?php endif; ?>
</form>

<div class="card">
    <div class="table-toolbar">
        <span class="table-count"><strong><?= count($students) ?></strong> student<?= count($students) === 1 ? '' : 's' ?></span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover table-stack">
            <thead>
            <tr><th>Student</th><th>Roll</th><th>Batch</th><th>Phone</th><th>Status</th><th class="text-end">Actions</th></tr>
            </thead>
            <tbody>
            <?php foreach ($students as $s): ?>
                <tr>
                    <td>
                        <div class="person">
                            <?= avatar($s['name'], $s['photo']) ?>
                            <div class="min-w-0">
                                <a href="<?= url('admin/student_profile.php?id=' . $s['id']) ?>" class="person-name"><?= e($s['name']) ?></a>
                                <div class="person-sub">ID <?= $s['id'] ?> · <?= e($s['email']) ?></div>
                            </div>
                        </div>
                    </td>
                    <td data-label="Roll" class="tabular"><?= e($s['roll_no']) ?></td>
                    <td data-label="Batch"><?= $s['batch_name'] ? e($s['batch_name']) : '<span class="text-muted">—</span>' ?></td>
                    <td data-label="Phone" class="tabular"><?= e($s['phone']) ?: '<span class="text-muted">—</span>' ?></td>
                    <td data-label="Status"><?= status_badge($s['status']) ?></td>
                    <td class="text-end row-actions-cell">
                        <div class="row-actions">
                            <a href="<?= url('admin/student_profile.php?id=' . $s['id']) ?>" class="btn btn-sm btn-ghost btn-icon" title="View profile" aria-label="View profile"><i class="bi bi-eye"></i></a>
                            <a href="<?= url('admin/student_form.php?id=' . $s['id']) ?>" class="btn btn-sm btn-ghost btn-icon is-primary" title="Edit" aria-label="Edit"><i class="bi bi-pencil"></i></a>
                            <form method="post">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= $s['id'] ?>">
                                <button name="action" value="toggle" class="btn btn-sm btn-ghost btn-icon"
                                        title="<?= $s['status'] === 'active' ? 'Deactivate' : 'Activate' ?>" aria-label="<?= $s['status'] === 'active' ? 'Deactivate' : 'Activate' ?>">
                                    <i class="bi bi-<?= $s['status'] === 'active' ? 'pause-circle' : 'play-circle' ?>"></i></button>
                                <button name="action" value="delete" class="btn btn-sm btn-ghost btn-icon is-danger" title="Delete" aria-label="Delete"
                                        data-confirm-title="Delete <?= e($s['name']) ?>?"
                                        data-confirm="This permanently removes the student and all related records: attendance, fees, payments and results. This cannot be undone."><i class="bi bi-trash"></i></button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$students): ?>
                <?= ($q !== '' || $batch_id || $status)
                    ? empty_row(6, 'search', 'No students match your filters', 'Try a different name or clear the filters.', '<a class="btn btn-sm btn-light" href="' . url('admin/students.php') . '">Clear filters</a>')
                    : empty_row(6, 'people', 'No students yet', 'Add your first student to start tracking attendance, fees and results.', '<a class="btn btn-sm btn-brand" href="' . url('admin/student_form.php') . '"><i class="bi bi-person-plus"></i> Add student</a>') ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
