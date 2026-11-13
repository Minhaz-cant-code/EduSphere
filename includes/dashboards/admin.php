<?php
/** Admin dashboard - included from dashboard.php */
if (!defined('IN_APP')) exit;

$today = today();

$total_students = $pdo->query("SELECT COUNT(*) FROM students s JOIN users u ON u.id = s.user_id WHERE u.status = 'active'")->fetchColumn();
$total_teachers = $pdo->query("SELECT COUNT(*) FROM teachers t JOIN users u ON u.id = t.user_id WHERE u.status = 'active'")->fetchColumn();
$total_batches  = $pdo->query('SELECT COUNT(*) FROM batches')->fetchColumn();

// Today's attendance
$st = $pdo->prepare("SELECT COUNT(*) AS marked, SUM(status <> 'Absent') AS attended FROM attendance WHERE att_date = ?");
$st->execute([$today]);
$att_today = $st->fetch();
$att_today_pct = $att_today['marked'] ? round($att_today['attended'] / $att_today['marked'] * 100) : null;

// Today's income
$st = $pdo->prepare('SELECT COALESCE(SUM(amount_paid),0) FROM payments WHERE payment_date = ?');
$st->execute([$today]);
$income_today = $st->fetchColumn();

// Total dues (all fees - all payments)
$total_due = $pdo->query('SELECT COALESCE(SUM(amount),0) FROM fees')->fetchColumn()
           - $pdo->query('SELECT COALESCE(SUM(amount_paid),0) FROM payments')->fetchColumn();

// How many students still owe money
$students_with_due = (int)$pdo->query(
    'SELECT COUNT(*) FROM (
        SELECT f.student_id
          FROM fees f
          LEFT JOIN (SELECT fee_id, SUM(amount_paid) AS paid FROM payments GROUP BY fee_id) p ON p.fee_id = f.id
         GROUP BY f.student_id
        HAVING SUM(f.amount - COALESCE(p.paid, 0)) > 0
     ) d'
)->fetchColumn();

// Upcoming exams
$st = $pdo->prepare(
    'SELECT e.*, s.name AS subject, b.name AS batch
       FROM exams e JOIN subjects s ON s.id = e.subject_id JOIN batches b ON b.id = s.batch_id
      WHERE e.exam_date >= ? ORDER BY e.exam_date LIMIT 5'
);
$st->execute([$today]);
$upcoming = $st->fetchAll();

// Recent payments
$recent_payments = $pdo->query(
    'SELECT p.*, u.name AS student, f.fee_type, f.fee_month
       FROM payments p JOIN fees f ON f.id = p.fee_id
       JOIN students s ON s.id = f.student_id JOIN users u ON u.id = s.user_id
      ORDER BY p.payment_date DESC, p.id DESC LIMIT 6'
)->fetchAll();

// ---- Chart 1: income for the last 6 months ----
$months = [];
for ($i = 5; $i >= 0; $i--) {
    $months[date('Y-m', strtotime("first day of -$i month"))] = 0;
}
$st = $pdo->prepare(
    'SELECT SUBSTR(payment_date, 1, 7) AS ym, SUM(amount_paid) AS total
       FROM payments WHERE payment_date >= ? GROUP BY ym'
);
$st->execute([array_key_first($months) . '-01']);
foreach ($st->fetchAll() as $r) {
    if (isset($months[$r['ym']])) $months[$r['ym']] = (float)$r['total'];
}
$income_6m    = array_sum($months);
$income_month = $months[date('Y-m')] ?? 0;

// ---- Chart 2: attendance % for the last 7 days ----
$days = [];
for ($i = 6; $i >= 0; $i--) {
    $days[date('Y-m-d', strtotime("-$i day"))] = null;
}
$st = $pdo->prepare(
    "SELECT att_date, COUNT(*) AS total, SUM(status <> 'Absent') AS attended
       FROM attendance WHERE att_date >= ? GROUP BY att_date"
);
$st->execute([array_key_first($days)]);
foreach ($st->fetchAll() as $r) {
    if (array_key_exists($r['att_date'], $days)) {
        $days[$r['att_date']] = round($r['attended'] / $r['total'] * 100, 1);
    }
}
$att_days = array_filter($days, fn($v) => $v !== null);
$att_avg  = $att_days ? round(array_sum($att_days) / count($att_days)) : null;

$first_name = explode(' ', user()['name'])[0];
$meter_cls  = $att_today_pct === null ? '' : ($att_today_pct >= 75 ? 'is-success' : ($att_today_pct >= 50 ? 'is-warning' : 'is-danger'));
?>
<div class="page-head greeting">
    <div>
        <h1><?= e(greeting()) ?>, <?= e($first_name) ?></h1>
        <p class="page-sub">Here's how <?= e(INSTITUTE_NAME) ?> is doing today.</p>
    </div>
    <div class="page-actions">
        <a href="<?= url('admin/student_form.php') ?>" class="btn btn-light"><i class="bi bi-person-plus"></i> Add student</a>
        <a href="<?= url('fees/index.php?status=due') ?>" class="btn btn-brand"><i class="bi bi-cash-coin"></i> Collect fees</a>
    </div>
</div>

<!-- Key figures -->
<section class="kpi-strip" aria-label="Key figures">
    <a class="kpi" href="<?= url('admin/students.php') ?>">
        <div class="kpi-label"><span class="kpi-icon is-primary"><i class="bi bi-people"></i></span> Active students</div>
        <div class="kpi-value"><?= number_format($total_students) ?></div>
        <div class="kpi-meta"><strong><?= (int)$total_teachers ?></strong> teachers · <strong><?= (int)$total_batches ?></strong> batches</div>
    </a>
    <a class="kpi" href="<?= url('attendance/report.php') ?>">
        <div class="kpi-label"><span class="kpi-icon is-success"><i class="bi bi-calendar2-check"></i></span> Today's attendance</div>
        <?php if ($att_today_pct !== null): ?>
            <div class="kpi-value"><?= $att_today_pct ?><span class="unit">%</span></div>
            <div class="meter <?= $meter_cls ?>"><span style="width:<?= $att_today_pct ?>%"></span></div>
            <div class="kpi-meta"><strong><?= (int)$att_today['attended'] ?></strong> of <?= (int)$att_today['marked'] ?> marked students present</div>
        <?php else: ?>
            <div class="kpi-value is-text">Not taken yet</div>
            <div class="kpi-meta">Nothing marked today</div>
        <?php endif; ?>
    </a>
    <a class="kpi" href="<?= url('reports/index.php?type=income') ?>">
        <div class="kpi-label"><span class="kpi-icon is-info"><i class="bi bi-wallet2"></i></span> Collected today</div>
        <div class="kpi-value"><?= money_kpi($income_today) ?></div>
        <div class="kpi-meta"><strong><?= money($income_month) ?></strong> so far this month</div>
    </a>
    <a class="kpi" href="<?= url('fees/index.php?status=due') ?>">
        <div class="kpi-label"><span class="kpi-icon is-danger"><i class="bi bi-hourglass-split"></i></span> Outstanding dues</div>
        <div class="kpi-value"><?= money_kpi($total_due) ?></div>
        <div class="kpi-meta"><?= $students_with_due ? '<strong>' . $students_with_due . '</strong> student' . ($students_with_due === 1 ? '' : 's') . ' with unpaid fees' : 'All fees are paid' ?></div>
    </a>
</section>

<!-- Analytics -->
<div class="row g-4 mb-4">
    <div class="col-xl-8">
        <div class="card h-100">
            <div class="chart-head">
                <div>
                    <h2 class="section-title">Fee collection</h2>
                    <p class="section-sub">Payments received over the last 6 months</p>
                </div>
                <div class="chart-figure"><?= money($income_6m) ?><small>Total, 6 months</small></div>
            </div>
            <div class="chart-box">
                <?php if ($income_6m > 0): ?>
                    <canvas id="incomeChart" aria-label="Monthly income bar chart" role="img"></canvas>
                <?php else: ?>
                    <div class="chart-empty"><?= empty_state('bar-chart', 'No payments yet', 'Monthly totals appear here once payments are recorded.', '', true) ?></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="card h-100">
            <div class="chart-head">
                <div>
                    <h2 class="section-title">Attendance</h2>
                    <p class="section-sub">Daily rate, last 7 days</p>
                </div>
                <div class="chart-figure"><?= $att_avg !== null ? $att_avg . '%' : '—' ?><small>Average on class days</small></div>
            </div>
            <div class="chart-box">
                <?php if ($att_days): ?>
                    <canvas id="attChart" aria-label="Attendance line chart" role="img"></canvas>
                <?php else: ?>
                    <div class="chart-empty"><?= empty_state('calendar2-x', 'No attendance this week', 'Teachers\' daily attendance will be charted here.', '', true) ?></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Smart feature: students needing attention -->
<?php $risk_list = risk_list(); require __DIR__ . '/risk_widget.php'; ?>

<!-- Activity -->
<div class="row g-4">
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header">
                <div><h2 class="section-title">Recent payments</h2><p class="section-sub">Latest fees received at the office</p></div>
                <a href="<?= url('fees/index.php') ?>" class="link-more">All fees <i class="bi bi-arrow-right"></i></a>
            </div>
            <?php if ($recent_payments): ?>
                <div class="list-rows">
                    <?php foreach ($recent_payments as $p): ?>
                        <a class="list-row" href="<?= url('fees/receipt.php?id=' . $p['id']) ?>">
                            <?= avatar($p['student']) ?>
                            <div class="list-row-main">
                                <div class="list-row-title"><?= e($p['student']) ?></div>
                                <div class="list-row-sub">
                                    <?= e($p['fee_type']) ?> fee<?= $p['fee_month'] ? ' · ' . date('M Y', strtotime($p['fee_month'] . '-01')) : '' ?>
                                    · <?= e($p['method']) ?>
                                </div>
                            </div>
                            <div class="list-row-end">
                                <div class="fw-semibold text-dark tabular"><?= money($p['amount_paid']) ?></div>
                                <div class="list-row-sub"><?= fmt_date($p['payment_date']) ?></div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <?= empty_state('receipt', 'No payments recorded', 'Payments you collect will show up here with their receipts.') ?>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header">
                <div><h2 class="section-title">Upcoming exams</h2><p class="section-sub">Scheduled across all batches</p></div>
                <a href="<?= url('exams/index.php') ?>" class="link-more">All exams <i class="bi bi-arrow-right"></i></a>
            </div>
            <?php if ($upcoming): ?>
                <div class="list-rows">
                    <?php foreach ($upcoming as $ex): $days_left = (int)round((strtotime($ex['exam_date']) - strtotime($today)) / 86400); ?>
                        <a class="list-row" href="<?= url('exams/marks.php?id=' . $ex['id']) ?>">
                            <div class="date-block"><span class="m"><?= date('M', strtotime($ex['exam_date'])) ?></span><span class="d"><?= date('j', strtotime($ex['exam_date'])) ?></span></div>
                            <div class="list-row-main">
                                <div class="list-row-title"><?= e($ex['exam_name']) ?></div>
                                <div class="list-row-sub"><?= e($ex['batch']) ?> · <?= e($ex['subject']) ?></div>
                            </div>
                            <span class="badge <?= $days_left <= 3 ? 'text-bg-warning' : 'text-bg-secondary' ?>">
                                <?= $days_left === 0 ? 'Today' : ($days_left === 1 ? 'Tomorrow' : 'In ' . $days_left . ' days') ?>
                            </span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <?= empty_state('journal-check', 'No upcoming exams', 'Exams created by teachers will be listed here.') ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
$month_labels = array_map(fn($m) => date('M', strtotime($m . '-01')), array_keys($months));
$day_labels   = array_map(fn($d) => date('D', strtotime($d)), array_keys($days));
$extra_js = '<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
esChartDefaults();
const taka = v => "৳" + Number(v).toLocaleString("en-IN");
const incomeEl = document.getElementById("incomeChart");
if (incomeEl) new Chart(incomeEl, {
    type: "bar",
    data: { labels: ' . json_encode($month_labels) . ',
            datasets: [{ data: ' . json_encode(array_values($months)) . ',
                         backgroundColor: ctx => ctx.dataIndex === ' . (count($months) - 1) . ' ? "#4338ca" : "#c7c9f7",
                         hoverBackgroundColor: "#3730a3", borderRadius: 6, maxBarThickness: 44 }] },
    options: {
        scales: {
            x: { grid: { display: false }, border: { display: false } },
            y: { beginAtZero: true, border: { display: false }, ticks: { callback: taka, maxTicksLimit: 5 } }
        },
        plugins: { tooltip: { callbacks: { label: c => taka(c.parsed.y) + " collected" } } }
    }
});
const attEl = document.getElementById("attChart");
if (attEl) new Chart(attEl, {
    type: "line",
    data: { labels: ' . json_encode($day_labels) . ',
            datasets: [{ data: ' . json_encode(array_values($days)) . ',
                         borderColor: "#15803d", backgroundColor: "rgba(21,128,61,.08)", fill: true,
                         spanGaps: true, tension: .35, borderWidth: 2,
                         pointRadius: 3.5, pointBackgroundColor: "#fff", pointBorderWidth: 2 }] },
    options: {
        scales: {
            x: { grid: { display: false }, border: { display: false } },
            y: { min: 0, max: 100, border: { display: false }, ticks: { stepSize: 25, callback: v => v + "%" } }
        },
        plugins: { tooltip: { callbacks: { label: c => c.parsed.y + "% present" } } }
    }
});
</script>';
