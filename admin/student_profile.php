<?php
/**
 * Student profile: attendance, fees and results summary (FR-STU-6)
 */
require_once __DIR__ . '/../includes/functions.php';
require_role('admin');

$id = get_int('id');
$st = $pdo->prepare(
    'SELECT s.*, u.name, u.email, u.username, u.status, b.name AS batch_name
       FROM students s JOIN users u ON u.id = s.user_id
       LEFT JOIN batches b ON b.id = s.batch_id WHERE s.id = ?'
);
$st->execute([$id]);
$s = $st->fetch();
if (!$s) {
    flash('danger', 'Student not found.');
    redirect('admin/students.php');
}

$att  = attendance_summary($id);
$fee  = fee_summary($id);
$fees = student_fees($id);

$st = $pdo->prepare(
    'SELECT r.*, e.exam_name, e.exam_type, e.exam_date, e.total_marks, sub.name AS subject
       FROM results r JOIN exams e ON e.id = r.exam_id JOIN subjects sub ON sub.id = e.subject_id
      WHERE r.student_id = ? ORDER BY e.exam_date DESC'
);
$st->execute([$id]);
$results = $st->fetchAll();
$avg_gpa = $results ? array_sum(array_column($results, 'gpa')) / count($results) : 0;

$st = $pdo->prepare('SELECT p.*, u.name, u.email FROM parents p JOIN users u ON u.id = p.user_id WHERE p.student_id = ?');
$st->execute([$id]);
$parents = $st->fetchAll();

$page_title = $s['name'];
require __DIR__ . '/../includes/header.php';
$att_cls = $att['percent'] >= 75 ? 'is-success' : ($att['percent'] >= 50 ? 'is-warning' : 'is-danger');
?>
<a class="page-back no-print" href="<?= url('admin/students.php') ?>"><i class="bi bi-arrow-left"></i> All students</a>
<div class="card mb-4">
    <div class="card-body d-flex flex-wrap align-items-center gap-4">
        <?= avatar($s['name'], $s['photo'], 'lg') ?>
        <div class="flex-grow-1 min-w-0">
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <h1 class="page-title"><?= e($s['name']) ?></h1>
                <?= status_badge($s['status']) ?>
            </div>
            <div class="profile-strip mt-2">
                <div class="facts">
                    <span><i class="bi bi-collection"></i> <?= e($s['batch_name'] ?? 'No batch') ?></span>
                    <span><i class="bi bi-hash"></i> Roll <?= e($s['roll_no']) ?></span>
                    <span><i class="bi bi-person-vcard"></i> ID <?= $s['id'] ?></span>
                    <?php if ($s['phone']): ?><span><i class="bi bi-telephone"></i> <?= e($s['phone']) ?></span><?php endif; ?>
                </div>
            </div>
        </div>
        <div class="page-actions no-print">
            <button onclick="window.print()" class="btn btn-light btn-icon" title="Print" aria-label="Print"><i class="bi bi-printer"></i></button>
            <a href="<?= url('fees/index.php?student_id=' . $id) ?>" class="btn btn-light"><i class="bi bi-wallet2"></i> Fees</a>
            <a href="<?= url('admin/student_form.php?id=' . $id) ?>" class="btn btn-brand"><i class="bi bi-pencil"></i> Edit profile</a>
        </div>
    </div>
</div>

<section class="kpi-strip cols-3" aria-label="Summary">
    <div class="kpi">
        <div class="kpi-label"><span class="kpi-icon is-success"><i class="bi bi-calendar2-check"></i></span> Attendance</div>
        <div class="kpi-value"><?= $att['total'] ? $att['percent'] . '<span class="unit">%</span>' : '—' ?></div>
        <?php if ($att['total']): ?><div class="meter <?= $att_cls ?>"><span style="width:<?= min(100, $att['percent']) ?>%"></span></div><?php endif; ?>
        <div class="kpi-meta">Present <strong><?= $att['present'] ?></strong> · Late <strong><?= $att['late'] ?></strong> · Absent <strong><?= $att['absent'] ?></strong></div>
    </div>
    <div class="kpi">
        <div class="kpi-label"><span class="kpi-icon <?= $fee['due'] > 0 ? 'is-danger' : 'is-success' ?>"><i class="bi bi-wallet2"></i></span> Fee due</div>
        <div class="kpi-value"><?= money_kpi($fee['due']) ?></div>
        <div class="kpi-meta">Paid <strong><?= money($fee['paid']) ?></strong> of <?= money($fee['total']) ?></div>
    </div>
    <div class="kpi">
        <div class="kpi-label"><span class="kpi-icon is-primary"><i class="bi bi-award"></i></span> Average GPA</div>
        <div class="kpi-value"><?= $results ? number_format($avg_gpa, 2) : '—' ?></div>
        <div class="kpi-meta">From <strong><?= count($results) ?></strong> exam<?= count($results) === 1 ? '' : 's' ?></div>
    </div>
</section>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header"><h2 class="section-title">Details</h2></div>
            <div class="card-body pt-2 pb-2">
                <dl class="detail-list">
                    <div><dt>Username</dt><dd><?= e($s['username']) ?></dd></div>
                    <div><dt>Email</dt><dd><?= e($s['email']) ?></dd></div>
                    <div><dt>Date of birth</dt><dd><?= fmt_date($s['dob']) ?></dd></div>
                    <div><dt>Gender</dt><dd><?= e($s['gender']) ?: '—' ?></dd></div>
                    <div><dt>Guardian</dt><dd><?= e($s['guardian_name']) ?: '—' ?></dd></div>
                    <div><dt>Address</dt><dd><?= e($s['address']) ?: '—' ?></dd></div>
                    <div><dt>Admitted</dt><dd><?= fmt_date($s['admission_date']) ?></dd></div>
                </dl>
            </div>
            <div class="card-footer">
                <div class="small fw-semibold text-dark mb-2">Parent accounts</div>
                <?php foreach ($parents as $p): ?>
                    <div class="d-flex align-items-center gap-2 mb-2"><?= avatar($p['name'], null, 'sm') ?>
                        <div class="small"><span class="text-dark fw-medium"><?= e($p['name']) ?></span> <span class="text-muted">· <?= e($p['relation']) ?></span></div></div>
                <?php endforeach; ?>
                <?php if (!$parents): ?>
                    <a class="small" href="<?= url('admin/parent_form.php?student_id=' . $id) ?>"><i class="bi bi-plus"></i> Add a parent login</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card mb-4">
            <div class="card-header">
                <h2 class="section-title">Fees</h2>
                <a class="link-more no-print" href="<?= url('fees/add.php?student_id=' . $id) ?>"><i class="bi bi-plus"></i> Add fee</a>
            </div>
            <div class="table-responsive">
                <table class="table table-stack">
                    <thead><tr><th>Fee</th><th class="num">Amount</th><th class="num">Paid</th><th class="num">Due</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php foreach ($fees as $f): ?>
                        <tr>
                            <td><div class="cell-main"><?= e($f['fee_type']) ?></div><div class="cell-sub"><?= e($f['fee_month'] ? date('F Y', strtotime($f['fee_month'] . '-01')) : $f['description']) ?></div></td>
                            <td class="num" data-label="Amount"><?= money($f['amount']) ?></td>
                            <td class="num" data-label="Paid"><?= money($f['paid']) ?></td>
                            <td class="num fw-semibold <?= $f['due'] > 0 ? 'text-danger' : '' ?>" data-label="Due"><?= money($f['due']) ?></td>
                            <td data-label="Status"><?= status_badge(fee_status($f['amount'], $f['paid'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$fees): ?><?= empty_row(5, 'wallet2', 'No fees recorded', 'Fees charged to this student will appear here.') ?><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h2 class="section-title">Results</h2></div>
            <div class="table-responsive">
                <table class="table table-stack">
                    <thead><tr><th>Exam</th><th>Date</th><th class="num">Marks</th><th>Grade</th></tr></thead>
                    <tbody>
                    <?php foreach ($results as $r): ?>
                        <tr>
                            <td><div class="cell-main"><?= e($r['exam_name']) ?></div><div class="cell-sub"><?= e($r['subject']) ?> · <?= e($r['exam_type']) ?></div></td>
                            <td data-label="Date"><?= fmt_date($r['exam_date']) ?></td>
                            <td class="num" data-label="Marks"><?= (float)$r['marks'] ?> <span class="text-muted">/ <?= (int)$r['total_marks'] ?></span></td>
                            <td data-label="Grade"><span class="badge <?= $r['grade'] === 'F' ? 'text-bg-danger' : 'text-bg-success' ?>"><?= e($r['grade']) ?> · <?= number_format($r['gpa'], 2) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$results): ?><?= empty_row(4, 'award', 'No results yet', 'Marks entered by teachers will appear here.') ?><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
