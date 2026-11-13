<?php
/**
 * At-Risk Students (SRS 3.13, FR-RISK-3 and FR-RISK-4)
 * Admin sees every batch, a teacher sees only their own batches.
 */
require_once __DIR__ . '/../includes/functions.php';
require_role('admin', 'teacher');

// Which batches can this user see?
$batches   = manageable_batches();
$batch_ids = role() === 'admin' ? null : array_column($batches, 'id');

// Filters from the URL
$batch_id = get_int('batch_id');
$level    = $_GET['level'] ?? 'attention';   // attention = Medium + High

$students = risk_list($batch_ids);

// Count each level (only students with enough data)
$count = ['High' => 0, 'Medium' => 0, 'Low' => 0];
foreach ($students as $s) {
    if ($s['risk']['enough']) $count[$s['risk']['level']]++;
}

// Apply the filters
$rows = [];
foreach ($students as $s) {
    if ($batch_id && (int)$s['batch_id'] !== $batch_id) continue;
    if (!$s['risk']['enough'] && $level !== 'all') continue;   // new students: only in "All students"
    if ($level === 'attention' && $s['risk']['level'] === 'Low') continue;
    if (in_array($level, ['High', 'Medium', 'Low'], true) && $s['risk']['level'] !== $level) continue;
    $rows[] = $s;
}

$page_title = 'At-Risk Students';
require __DIR__ . '/../includes/header.php';
?>
<?php page_head('At-Risk Students', 'Students who may be falling behind, based on attendance, marks and fees. Scores update automatically.'); ?>

<section class="kpi-strip cols-3" aria-label="Risk summary">
    <a class="kpi" href="?level=High">
        <div class="kpi-label"><span class="kpi-icon is-danger"><i class="bi bi-exclamation-octagon"></i></span> High risk</div>
        <div class="kpi-value"><?= $count['High'] ?></div>
        <div class="kpi-meta">score 60–100 · act now</div>
    </a>
    <a class="kpi" href="?level=Medium">
        <div class="kpi-label"><span class="kpi-icon is-warning"><i class="bi bi-exclamation-triangle"></i></span> Medium risk</div>
        <div class="kpi-value"><?= $count['Medium'] ?></div>
        <div class="kpi-meta">score 30–59 · watch closely</div>
    </a>
    <a class="kpi" href="?level=Low">
        <div class="kpi-label"><span class="kpi-icon is-success"><i class="bi bi-check-circle"></i></span> Low risk</div>
        <div class="kpi-value"><?= $count['Low'] ?></div>
        <div class="kpi-meta">score 0–29 · no action</div>
    </a>
</section>

<form class="filter-bar filter-form">
    <div class="grow">
        <label class="form-label" for="batch_id">Batch</label>
        <select name="batch_id" id="batch_id" class="form-select">
            <option value="">All my batches</option>
            <?php foreach ($batches as $b): ?>
                <option value="<?= $b['id'] ?>" <?= $batch_id == $b['id'] ? 'selected' : '' ?>><?= e($b['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="fixed">
        <label class="form-label" for="level">Show</label>
        <select name="level" id="level" class="form-select">
            <option value="attention" <?= $level === 'attention' ? 'selected' : '' ?>>Needs attention (Medium + High)</option>
            <option value="High" <?= $level === 'High' ? 'selected' : '' ?>>High only</option>
            <option value="Medium" <?= $level === 'Medium' ? 'selected' : '' ?>>Medium only</option>
            <option value="Low" <?= $level === 'Low' ? 'selected' : '' ?>>Low only</option>
            <option value="all" <?= $level === 'all' ? 'selected' : '' ?>>All students</option>
        </select>
    </div>
    <button class="btn btn-light"><i class="bi bi-funnel"></i> Apply</button>
</form>

<div class="card mb-4">
    <div class="table-responsive">
        <table class="table table-hover table-stack">
            <thead><tr><th>Student</th><th>Batch</th><th style="min-width:150px">Risk score</th><th>Level</th><th>Reasons</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $s): $r = $s['risk']; ?>
                <tr>
                    <td>
                        <div class="person"><?= avatar($s['name'], $s['photo']) ?>
                            <div class="min-w-0">
                                <?php if (role() === 'admin'): ?>
                                    <a class="person-name" href="<?= url('admin/student_profile.php?id=' . $s['id']) ?>"><?= e($s['name']) ?></a>
                                <?php else: ?>
                                    <span class="person-name"><?= e($s['name']) ?></span>
                                <?php endif; ?>
                                <div class="person-sub">Roll <?= e($s['roll_no']) ?></div>
                            </div>
                        </div>
                    </td>
                    <td data-label="Batch"><?= e($s['batch'] ?? '—') ?></td>
                    <?php if (!$r['enough']): ?>
                        <td data-label="Risk score"><span class="text-muted">—</span></td>
                        <td data-label="Level"><span class="badge dot text-bg-secondary">Not enough data</span></td>
                        <td data-label="Reasons" class="small text-muted">Fewer than 5 classes and no results yet</td>
                    <?php else: ?>
                    <td data-label="Risk score">
                        <div class="d-flex align-items-center gap-2">
                            <div class="meter flex-grow-1 <?= $r['level'] === 'High' ? 'is-danger' : ($r['level'] === 'Medium' ? 'is-warning' : 'is-success') ?>" style="max-width:100px">
                                <span style="width:<?= $r['score'] ?>%"></span>
                            </div>
                            <strong class="tabular text-dark"><?= $r['score'] ?></strong>
                        </div>
                    </td>
                    <td data-label="Level"><?= risk_badge($r['level']) ?></td>
                    <td data-label="Reasons" class="small">
                        <?= $r['reasons'] ? e(implode(' · ', $r['reasons'])) : '<span class="text-muted">No warning signs</span>' ?>
                    </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?>
                <?= empty_row(5, 'emoji-smile', 'No students in this group', 'Nobody matches this filter right now. Try "All students".') ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- How the score works (SRS 3.13), shown so staff can trust the result -->
<div class="card">
    <div class="card-header"><div><h2 class="section-title">How the risk score works</h2><p class="section-sub">Simple rules, 100 points in total</p></div></div>
    <div class="card-body">
        <div class="row g-4 small">
            <div class="col-md-4">
                <div class="fw-semibold text-dark mb-1">Attendance (max 40)</div>
                Below 60% → 30 · 60–74% → 20 · 75–84% → 10<br>3 absences in a row → +10
            </div>
            <div class="col-md-4">
                <div class="fw-semibold text-dark mb-1">Marks (max 40)</div>
                Average below 40% → 30 · 40–49% → 20 · 50–59% → 10<br>Latest result 15+ points lower → +10
            </div>
            <div class="col-md-4">
                <div class="fw-semibold text-dark mb-1">Fees (max 20)</div>
                Overdue more than 30 days → 20<br>Overdue 1–30 days → 10
            </div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
