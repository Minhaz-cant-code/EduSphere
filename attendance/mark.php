<?php
/**
 * Mark attendance (FR-ATT-1) - Teacher/Admin
 * The list is saved with AJAX (attendance/save.php) without reloading the page.
 */
require_once __DIR__ . '/../includes/functions.php';
require_role('teacher', 'admin');

$batches  = manageable_batches();
$batch_id = get_int('batch_id', $batches ? (int)$batches[0]['id'] : 0);
$date     = $_GET['date'] ?? today();

// Validate inputs
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || $date > today()) {
    $date = today();
}
$students = [];
if ($batch_id && can_manage_batch($batch_id)) {
    $st = $pdo->prepare(
        "SELECT s.id, s.roll_no, u.name, a.status
           FROM students s
           JOIN users u ON u.id = s.user_id
           LEFT JOIN attendance a ON a.student_id = s.id AND a.att_date = ?
          WHERE s.batch_id = ? AND u.status = 'active'
          ORDER BY s.roll_no"
    );
    $st->execute([$date, $batch_id]);
    $students = $st->fetchAll();
} else {
    $batch_id = 0;
}
$already = $students && count(array_filter($students, fn($s) => $s['status'] !== null)) > 0;

$page_title = 'Mark attendance';
require __DIR__ . '/../includes/header.php';
?>
<?php page_head('Mark attendance', 'Everyone starts as Present. Tap Late or Absent for the exceptions, then save.',
    '<a href="' . url('attendance/report.php' . ($batch_id ? '?batch_id=' . $batch_id : '')) . '" class="btn btn-light"><i class="bi bi-calendar2-week"></i> Monthly report</a>'); ?>

<form class="filter-bar filter-form">
    <div class="grow">
        <label class="form-label" for="batch_id">Batch</label>
        <select name="batch_id" id="batch_id" class="form-select">
            <?php foreach ($batches as $b): ?>
                <option value="<?= $b['id'] ?>" <?= $batch_id == $b['id'] ? 'selected' : '' ?>><?= e($b['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="fixed">
        <label class="form-label" for="date">Date</label>
        <input type="date" name="date" id="date" class="form-control" value="<?= e($date) ?>" max="<?= today() ?>">
    </div>
    <button class="btn btn-light"><i class="bi bi-arrow-repeat"></i> Load class</button>
</form>

<?php if (!$batches): ?>
    <div class="card"><?= empty_state('collection', 'No batch assigned to you', 'Ask the admin to allocate subjects to you. Your batches will appear here.') ?></div>
<?php elseif (!$students): ?>
    <div class="card"><?= empty_state('people', 'No active students in this batch', 'Students added to this batch will appear here.') ?></div>
<?php else: ?>
    <?php if ($already): ?>
        <div class="alert alert-info"><i class="bi bi-info-circle"></i><div>Attendance for <strong><?= fmt_date($date) ?></strong> was already taken. Changes you save will update it.</div></div>
    <?php endif; ?>
    <div class="card">
        <div class="table-toolbar">
            <div class="att-summary" id="attSummary" aria-live="polite">
                <span><span class="cat-dot" style="background:#16803c"></span> Present <strong data-count="Present">0</strong></span>
                <span><span class="cat-dot" style="background:#d97706"></span> Late <strong data-count="Late">0</strong></span>
                <span><span class="cat-dot" style="background:#b91c1c"></span> Absent <strong data-count="Absent">0</strong></span>
            </div>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-sm btn-light" onclick="markAll('Present')"><i class="bi bi-check-all"></i> All present</button>
                <button type="button" class="btn btn-sm btn-light" onclick="markAll('Absent')">All absent</button>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table mb-0" id="attTable">
                <thead><tr><th style="width:90px">Roll</th><th>Student</th><th class="text-end">Status</th></tr></thead>
                <tbody>
                <?php foreach ($students as $s): $cur = $s['status'] ?? 'Present'; ?>
                    <tr data-student="<?= $s['id'] ?>">
                        <td class="tabular text-muted"><?= e($s['roll_no']) ?></td>
                        <td><div class="person"><?= avatar($s['name'], null, 'sm') ?><span class="person-name"><?= e($s['name']) ?></span></div></td>
                        <td class="text-end att-options">
                            <div class="btn-group btn-group-sm" role="group" aria-label="Attendance for <?= e($s['name']) ?>">
                                <?php foreach (['Present' => 'success', 'Late' => 'warning', 'Absent' => 'danger'] as $opt => $c): ?>
                                    <input type="radio" class="btn-check" name="st<?= $s['id'] ?>" id="st<?= $s['id'] . $opt ?>" value="<?= $opt ?>" <?= $cur === $opt ? 'checked' : '' ?>>
                                    <label class="btn btn-outline-<?= $c ?>" for="st<?= $s['id'] . $opt ?>"><?= $opt ?></label>
                                <?php endforeach; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="card-footer sticky-actions d-flex flex-wrap align-items-center gap-3">
            <button class="btn btn-brand" id="saveBtn" onclick="saveAttendance()"><i class="bi bi-check-lg"></i> Save attendance</button>
            <span id="saveMsg" class="small text-muted"><?= count($students) ?> students · <?= fmt_date($date) ?></span>
        </div>
    </div>
<?php endif; ?>

<?php
$extra_js = '<script>
function updateSummary() {
    const c = { Present: 0, Late: 0, Absent: 0 };
    document.querySelectorAll("#attTable input:checked").forEach(r => c[r.value]++);
    Object.keys(c).forEach(k => { const el = document.querySelector("[data-count=" + k + "]"); if (el) el.textContent = c[k]; });
}
function markAll(status) {
    document.querySelectorAll("#attTable input[value=" + status + "]").forEach(r => r.checked = true);
    updateSummary();
}
document.querySelectorAll("#attTable input").forEach(r => r.addEventListener("change", updateSummary));
updateSummary();

function saveAttendance() {
    const records = {};
    document.querySelectorAll("#attTable tbody tr").forEach(tr => {
        const checked = tr.querySelector("input:checked");
        if (checked) records[tr.dataset.student] = checked.value;
    });
    const btn = document.getElementById("saveBtn");
    const msg = document.getElementById("saveMsg");
    btn.disabled = true;
    msg.className = "small text-muted";
    msg.innerHTML = "<span class=\"spinner-border spinner-border-sm me-1\"></span> Saving…";

    fetch("save.php", {
        method: "POST",
        headers: { "Content-Type": "application/json",
                   "X-CSRF-Token": document.querySelector("meta[name=csrf-token]").content },
        body: JSON.stringify({ batch_id: ' . (int)$batch_id . ', date: ' . json_encode($date) . ', records: records })
    })
    .then(r => r.json())
    .then(data => {
        msg.className = "small fw-medium " + (data.ok ? "text-success" : "text-danger");
        msg.innerHTML = "<i class=\"bi bi-" + (data.ok ? "check-circle-fill" : "exclamation-circle-fill") + " me-1\"></i>";
        msg.append(data.message);
    })
    .catch(() => { msg.className = "small text-danger"; msg.textContent = "Network error, please try again."; })
    .finally(() => btn.disabled = false);
}
</script>';
require __DIR__ . '/../includes/footer.php';
