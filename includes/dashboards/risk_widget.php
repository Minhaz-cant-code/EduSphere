<?php
/**
 * "Students needing attention" card (FR-DSH-4)
 * Used on the admin and teacher dashboards. $risk_list must be set before including.
 */
if (!defined('IN_APP')) exit;

// Keep only Medium and High students, top 5
$attention = array_filter($risk_list, fn($s) => $s['risk']['enough'] && $s['risk']['level'] !== 'Low');
$top5 = array_slice($attention, 0, 5);
?>
<div class="card mb-4">
    <div class="card-header">
        <div>
            <h2 class="section-title"><i class="bi bi-exclamation-triangle text-warning"></i> Students needing attention</h2>
            <p class="section-sub">Smart early warning from attendance, marks and fees</p>
        </div>
        <a href="<?= url('admin/at_risk.php') ?>" class="link-more">View all (<?= count($attention) ?>) <i class="bi bi-arrow-right"></i></a>
    </div>
    <?php if ($top5): ?>
        <div class="list-rows">
            <?php foreach ($top5 as $s): ?>
                <a class="list-row" href="<?= url('admin/at_risk.php') ?>">
                    <?= avatar($s['name'], $s['photo']) ?>
                    <div class="list-row-main">
                        <div class="list-row-title"><?= e($s['name']) ?> <span class="text-muted fw-normal small">· <?= e($s['batch']) ?></span></div>
                        <div class="list-row-sub"><?= e(implode(' · ', $s['risk']['reasons'])) ?></div>
                    </div>
                    <div class="list-row-end">
                        <?= risk_badge($s['risk']['level']) ?>
                        <div class="list-row-sub mt-1">Score <?= $s['risk']['score'] ?></div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <?= empty_state('emoji-smile', 'No students at risk right now', 'Students with low attendance, falling marks or overdue fees will appear here.', '', true) ?>
    <?php endif; ?>
</div>
