<?php
/**
 * Attendance history for a student, or a parent's child (FR-ATT-4)
 */
require_once __DIR__ . '/../includes/functions.php';
require_role('student', 'parent');

$sid = my_student_id();
$month = $_GET['month'] ?? '';
if ($month !== '' && !preg_match('/^\d{4}-\d{2}$/', $month)) $month = '';

$overall = attendance_summary($sid);

// Monthly breakdown
$st = $pdo->prepare(
    "SELECT SUBSTR(att_date, 1, 7) AS ym,
            SUM(status = 'Present') AS present, SUM(status = 'Late') AS late,
            SUM(status = 'Absent') AS absent, COUNT(*) AS total
       FROM attendance WHERE student_id = ? GROUP BY ym ORDER BY ym DESC"
);
$st->execute([$sid]);
$monthly = $st->fetchAll();

// Day-by-day history
$sql = 'SELECT att_date, status FROM attendance WHERE student_id = ?';
$params = [$sid];
if ($month) {
    $sql .= ' AND att_date BETWEEN ? AND ?';
    $params[] = $month . '-01';
    $params[] = date('Y-m-t', strtotime($month . '-01'));
}
$st = $pdo->prepare($sql . ' ORDER BY att_date DESC LIMIT 100');
$st->execute($params);
$history = $st->fetchAll();

$page_title = role() === 'parent' ? "Child's attendance" : 'My attendance';
require __DIR__ . '/../includes/header.php';
$att_cls = $overall['percent'] >= 75 ? 'is-success' : ($overall['percent'] >= 50 ? 'is-warning' : 'is-danger');
?>
<?php page_head($page_title, role() === 'parent' ? 'Your child\'s class attendance. Late counts as attended.' : 'Your class attendance. Late counts as attended.'); ?>

<section class="kpi-strip" aria-label="Attendance summary">
    <div class="kpi">
        <div class="kpi-label"><span class="kpi-icon is-primary"><i class="bi bi-graph-up"></i></span> Overall rate</div>
        <div class="kpi-value"><?= $overall['total'] ? $overall['percent'] . '<span class="unit">%</span>' : '—' ?></div>
        <?php if ($overall['total']): ?><div class="meter <?= $att_cls ?>"><span style="width:<?= min(100, $overall['percent']) ?>%"></span></div><?php endif; ?>
        <div class="kpi-meta"><?= $overall['total'] ?> classes recorded</div>
    </div>
    <div class="kpi">
        <div class="kpi-label"><span class="kpi-icon is-success"><i class="bi bi-check-circle"></i></span> Present</div>
        <div class="kpi-value"><?= $overall['present'] ?></div>
        <div class="kpi-meta">on time</div>
    </div>
    <div class="kpi">
        <div class="kpi-label"><span class="kpi-icon is-warning"><i class="bi bi-clock"></i></span> Late</div>
        <div class="kpi-value"><?= $overall['late'] ?></div>
        <div class="kpi-meta">counted as attended</div>
    </div>
    <div class="kpi">
        <div class="kpi-label"><span class="kpi-icon is-danger"><i class="bi bi-x-circle"></i></span> Absent</div>
        <div class="kpi-value"><?= $overall['absent'] ?></div>
        <div class="kpi-meta">classes missed</div>
    </div>
</section>

<div class="row g-4">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header"><div><h2 class="section-title">By month</h2><p class="section-sub">Select a month to see each day</p></div></div>
            <?php if ($monthly): ?>
                <div class="list-rows">
                    <?php foreach ($monthly as $m): $pct = round(($m['present'] + $m['late']) / $m['total'] * 100, 1); ?>
                        <a class="list-row <?= $month === $m['ym'] ? 'bg-body-tertiary' : '' ?>" href="?month=<?= e($m['ym']) ?>">
                            <div class="list-row-main">
                                <div class="list-row-title"><?= date('F Y', strtotime($m['ym'] . '-01')) ?></div>
                                <div class="list-row-sub">Present <?= (int)$m['present'] ?> · Late <?= (int)$m['late'] ?> · Absent <?= (int)$m['absent'] ?></div>
                                <div class="meter mt-2 <?= $pct >= 75 ? 'is-success' : ($pct >= 50 ? 'is-warning' : 'is-danger') ?>" style="max-width:260px"><span style="width:<?= $pct ?>%"></span></div>
                            </div>
                            <div class="list-row-end fw-semibold text-dark tabular"><?= $pct ?>%</div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <?= empty_state('calendar2', 'No attendance recorded yet', 'Records appear after the first class.') ?>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header">
                <div><h2 class="section-title"><?= $month ? date('F Y', strtotime($month . '-01')) : 'Recent days' ?></h2><p class="section-sub">Day-by-day history</p></div>
                <?php if ($month): ?><a href="?" class="link-more">Show latest</a><?php endif; ?>
            </div>
            <div class="table-responsive">
                <table class="table">
                    <thead><tr><th>Date</th><th>Day</th><th class="text-end">Status</th></tr></thead>
                    <tbody>
                    <?php foreach ($history as $h): ?>
                        <tr><td class="cell-main"><?= fmt_date($h['att_date']) ?></td><td class="text-muted"><?= date('l', strtotime($h['att_date'])) ?></td><td class="text-end"><?= status_badge($h['status']) ?></td></tr>
                    <?php endforeach; ?>
                    <?php if (!$history): ?><?= empty_row(3, 'calendar2', 'No records for this period') ?><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
