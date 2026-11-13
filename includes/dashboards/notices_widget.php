<?php
/** Latest notices card, used on dashboards ($notices must be set) */
if (!defined('IN_APP')) exit;
$cat_colors = ['General' => '#64748b', 'Holiday' => '#15803d', 'Exam' => '#4338ca', 'Emergency' => '#b91c1c', 'Event' => '#0e7490', 'Routine' => '#b45309'];
?>
<div class="card h-100">
    <div class="card-header">
        <div><h2 class="section-title">Notice board</h2><p class="section-sub">Latest announcements</p></div>
        <a href="<?= url('notices/index.php') ?>" class="link-more">View all <i class="bi bi-arrow-right"></i></a>
    </div>
    <?php if ($notices): ?>
        <?php foreach ($notices as $n): ?>
            <div class="notice-item">
                <div class="d-flex align-items-center gap-2">
                    <span class="cat-dot" style="background:<?= $cat_colors[$n['category']] ?? '#64748b' ?>"></span>
                    <span class="notice-item-title"><?= e($n['title']) ?></span>
                </div>
                <div class="notice-item-meta"><?= e($n['category']) ?> · <?= fmt_date($n['created_at']) ?> · <?= e($n['author'] ?? 'Admin') ?></div>
                <div class="notice-item-body"><?= nl2br(e(mb_strimwidth($n['body'], 0, 150, '…'))) ?></div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <?= empty_state('megaphone', 'No notices right now', 'Announcements from the institute will appear here.') ?>
    <?php endif; ?>
</div>
