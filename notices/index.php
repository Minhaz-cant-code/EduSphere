<?php
/**
 * Notice board (FR-NOT-1, FR-NOT-2)
 * Everyone sees notices for their role; Admin/Teacher can post.
 */
require_once __DIR__ . '/../includes/functions.php';
require_login();

$can_post = in_array(role(), ['admin', 'teacher'], true);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $can_post && post('action') === 'delete') {
    verify_csrf();
    // Admin can delete any notice, a teacher only their own
    $sql = 'DELETE FROM notices WHERE id = ?';
    $params = [(int)post('id')];
    if (role() === 'teacher') {
        $sql .= ' AND posted_by = ?';
        $params[] = user_id();
    }
    $pdo->prepare($sql)->execute($params);
    flash('success', 'Notice deleted.');
    redirect('notices/index.php');
}

$notices = visible_notices();
$colors = ['General' => 'secondary', 'Holiday' => 'success', 'Exam' => 'primary', 'Emergency' => 'danger', 'Event' => 'info', 'Routine' => 'warning'];

$page_title = 'Notice Board';
require __DIR__ . '/../includes/header.php';
$cat_colors = ['General' => '#64748b', 'Holiday' => '#15803d', 'Exam' => '#4338ca', 'Emergency' => '#b91c1c', 'Event' => '#0e7490', 'Routine' => '#b45309'];
$targets = ['all' => 'Everyone', 'student' => 'Students', 'parent' => 'Parents', 'teacher' => 'Teachers'];
?>
<?php page_head('Notice Board', 'Announcements from the institute office and teachers.',
    $can_post ? '<a href="' . url('notices/form.php') . '" class="btn btn-brand"><i class="bi bi-plus-lg"></i> Post Notice</a>' : ''); ?>

<div class="card">
    <?php foreach ($notices as $n): ?>
        <article class="notice-card">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                <div class="min-w-0">
                    <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
                        <span class="badge text-bg-light"><span class="cat-dot" style="background:<?= $cat_colors[$n['category']] ?? '#64748b' ?>"></span><?= e($n['category']) ?></span>
                        <?php if ($can_post): ?><span class="badge text-bg-light"><i class="bi bi-eye"></i> <?= e($targets[$n['target_role']] ?? ucfirst($n['target_role'])) ?></span><?php endif; ?>
                    </div>
                    <h2 class="h6 mb-1 fw-semibold"><?= e($n['title']) ?></h2>
                    <div class="small text-muted">
                        <?= e($n['author'] ?? 'Admin') ?> · <?= date('j M Y, g:i A', strtotime($n['created_at'])) ?>
                        <?= $n['expires_on'] ? ' · visible until ' . fmt_date($n['expires_on']) : '' ?>
                    </div>
                </div>
                <?php if (role() === 'admin' || (role() === 'teacher' && (int)$n['posted_by'] === user_id())): ?>
                    <div class="row-actions no-print">
                        <a href="<?= url('notices/form.php?id=' . $n['id']) ?>" class="btn btn-sm btn-ghost btn-icon is-primary" title="Edit" aria-label="Edit"><i class="bi bi-pencil"></i></a>
                        <form method="post">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= $n['id'] ?>">
                            <button name="action" value="delete" class="btn btn-sm btn-ghost btn-icon is-danger" title="Delete" aria-label="Delete"
                                    data-confirm-title="Delete this notice?" data-confirm="It will disappear from everyone's notice board."><i class="bi bi-trash"></i></button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
            <div class="notice-body"><?= nl2br(e($n['body'])) ?></div>
        </article>
    <?php endforeach; ?>
    <?php if (!$notices): ?>
        <?= empty_state('megaphone', 'No notices right now', $can_post ? 'Post holidays, exam schedules and other announcements here.' : 'Announcements from the institute will appear here.',
            $can_post ? '<a class="btn btn-sm btn-brand" href="' . url('notices/form.php') . '"><i class="bi bi-plus-lg"></i> Post Notice</a>' : '') ?>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
