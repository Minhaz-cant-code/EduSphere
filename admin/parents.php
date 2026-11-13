<?php
/**
 * Parent accounts (read-only access to a linked student)
 */
require_once __DIR__ . '/../includes/functions.php';
require_role('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action') === 'delete') {
    verify_csrf();
    $st = $pdo->prepare('SELECT user_id FROM parents WHERE id = ?');
    $st->execute([(int)post('id')]);
    if ($uid = $st->fetchColumn()) {
        $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$uid]);
        flash('success', 'Parent account deleted.');
    }
    redirect('admin/parents.php');
}

$parents = $pdo->query(
    'SELECT p.*, u.name, u.email, u.username, u.status, su.name AS student_name, s.roll_no, b.name AS batch
       FROM parents p
       JOIN users u ON u.id = p.user_id
       JOIN students s ON s.id = p.student_id
       JOIN users su ON su.id = s.user_id
       LEFT JOIN batches b ON b.id = s.batch_id
      ORDER BY u.name'
)->fetchAll();

$page_title = 'Parents';
require __DIR__ . '/../includes/header.php';
?>
<?php page_head('Parents', 'Read-only logins that let a parent follow one child\'s attendance, fees and results.',
    '<a href="' . url('admin/parent_form.php') . '" class="btn btn-brand"><i class="bi bi-person-plus"></i> Add parent</a>'); ?>
<div class="card">
    <div class="table-toolbar">
        <span class="table-count"><strong><?= count($parents) ?></strong> parent account<?= count($parents) === 1 ? '' : 's' ?></span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover table-stack">
            <thead><tr><th>Parent</th><th>Child</th><th>Relation</th><th>Phone</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
            <?php foreach ($parents as $p): ?>
                <tr>
                    <td><div class="person"><?= avatar($p['name']) ?><div class="min-w-0">
                        <a class="person-name" href="<?= url('admin/parent_form.php?id=' . $p['id']) ?>"><?= e($p['name']) ?></a>
                        <div class="person-sub">@<?= e($p['username']) ?> · <?= e($p['email']) ?></div></div></div></td>
                    <td data-label="Child"><a class="cell-main" href="<?= url('admin/student_profile.php?id=' . $p['student_id']) ?>"><?= e($p['student_name']) ?></a>
                        <div class="cell-sub"><?= e($p['batch']) ?> · Roll <?= e($p['roll_no']) ?></div></td>
                    <td data-label="Relation"><?= e($p['relation']) ?></td>
                    <td data-label="Phone" class="tabular"><?= e($p['phone']) ?: '<span class="text-muted">—</span>' ?></td>
                    <td data-label="Status"><?= status_badge($p['status']) ?></td>
                    <td class="text-end row-actions-cell">
                        <div class="row-actions">
                            <a href="<?= url('admin/parent_form.php?id=' . $p['id']) ?>" class="btn btn-sm btn-ghost btn-icon is-primary" title="Edit" aria-label="Edit"><i class="bi bi-pencil"></i></a>
                            <form method="post">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                <button name="action" value="delete" class="btn btn-sm btn-ghost btn-icon is-danger" title="Delete" aria-label="Delete"
                                        data-confirm-title="Delete parent account?" data-confirm="<?= e($p['name']) ?> will no longer be able to sign in. The child's records are not affected."><i class="bi bi-trash"></i></button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$parents): ?>
                <?= empty_row(6, 'person-hearts', 'No parent accounts yet', 'Give parents a login so they can follow their child\'s progress from home.',
                    '<a class="btn btn-sm btn-brand" href="' . url('admin/parent_form.php') . '"><i class="bi bi-person-plus"></i> Add parent</a>') ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
