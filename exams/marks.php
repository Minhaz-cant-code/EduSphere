<?php
/**
 * Enter marks for an exam (FR-EXM-2) + automatic grade/GPA (FR-EXM-3)
 * Use case: Create Exam & Enter Marks <<include>> Calculate Result & GPA
 */
require_once __DIR__ . '/../includes/functions.php';
require_role('admin', 'teacher');

$id = get_int('id', (int)post('id'));
$st = $pdo->prepare('SELECT e.*, s.name AS subject, s.batch_id, b.name AS batch FROM exams e JOIN subjects s ON s.id = e.subject_id JOIN batches b ON b.id = s.batch_id WHERE e.id = ?');
$st->execute([$id]);
$exam = $st->fetch();
if (!$exam || !can_manage_subject((int)$exam['subject_id'])) {
    flash('danger', 'Exam not found.');
    redirect('exams/index.php');
}
$total = (float)$exam['total_marks'];

$st = $pdo->prepare(
    "SELECT s.id, s.roll_no, u.name, r.marks, r.grade, r.gpa
       FROM students s JOIN users u ON u.id = s.user_id
       LEFT JOIN results r ON r.student_id = s.id AND r.exam_id = ?
      WHERE s.batch_id = ? AND u.status = 'active'
      ORDER BY s.roll_no"
);
$st->execute([$id, $exam['batch_id']]);
$students = $st->fetchAll();

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $marks = $_POST['marks'] ?? [];
    $valid_ids = array_column($students, 'id');

    // Validate everything first
    foreach ($marks as $sid => $m) {
        $m = trim((string)$m);
        if ($m === '') continue;
        if (!is_numeric($m) || $m < 0 || $m > $total) {
            $errors[] = "Marks must be between 0 and $total (student ID $sid).";
        }
    }

    if (!$errors) {
        $find = $pdo->prepare('SELECT id FROM results WHERE exam_id = ? AND student_id = ?');
        $ins  = $pdo->prepare('INSERT INTO results (exam_id, student_id, marks, grade, gpa) VALUES (?,?,?,?,?)');
        $upd  = $pdo->prepare('UPDATE results SET marks = ?, grade = ?, gpa = ? WHERE id = ?');
        $del  = $pdo->prepare('DELETE FROM results WHERE exam_id = ? AND student_id = ?');

        $pdo->beginTransaction();
        $saved = 0;
        foreach ($marks as $sid => $m) {
            $sid = (int)$sid;
            if (!in_array($sid, $valid_ids)) continue;
            $m = trim((string)$m);
            if ($m === '') {
                $del->execute([$id, $sid]);   // empty box = not entered / absent
                continue;
            }
            [$grade, $gpa] = calc_grade((float)$m, $total);
            $find->execute([$id, $sid]);
            if ($rid = $find->fetchColumn()) {
                $upd->execute([$m, $grade, $gpa, $rid]);
            } else {
                $ins->execute([$id, $sid, $m, $grade, $gpa]);
            }
            $saved++;
        }
        if (isset($_POST['publish'])) {
            $pdo->prepare('UPDATE exams SET is_published = 1 WHERE id = ?')->execute([$id]);
        }
        $pdo->commit();
        flash('success', "Marks saved for $saved student(s). Grades and GPA were calculated automatically."
            . (isset($_POST['publish']) ? ' Result published.' : ''));
        redirect('exams/marks.php?id=' . $id);
    }
}

$page_title = 'Enter marks';
require __DIR__ . '/../includes/header.php';
$entered = count(array_filter($students, fn($s) => $s['marks'] !== null));
?>
<?php page_head($exam['exam_name'],
    e($exam['batch']) . ' · ' . e($exam['subject']) . ' · ' . fmt_date($exam['exam_date']) . ' · out of <strong class="text-dark">' . (int)$total . '</strong> marks &nbsp;'
    . ($exam['is_published'] ? '<span class="badge dot text-bg-success">Published</span>' : '<span class="badge dot text-bg-secondary">Draft, students can\'t see it yet</span>'),
    '<a href="' . url('exams/result_sheet.php?id=' . $id) . '" class="btn btn-light"><i class="bi bi-list-ol"></i> Result sheet</a>',
    ['All exams', 'exams/index.php']); ?>
<?php form_errors($errors); ?>

<div class="row g-4">
    <div class="col-xl-8">
        <form method="post" class="card">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= $id ?>">
            <div class="table-toolbar">
                <span class="table-count"><strong id="enteredCount"><?= $entered ?></strong> of <?= count($students) ?> marks entered</span>
                <span class="small text-muted">Leave a box empty if the student was absent.</span>
            </div>
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead><tr><th style="width:80px">Roll</th><th>Student</th><th>Marks</th><th>Grade</th><th class="num">GPA</th></tr></thead>
                    <tbody>
                    <?php foreach ($students as $s): ?>
                        <tr>
                            <td class="tabular text-muted"><?= e($s['roll_no']) ?></td>
                            <td><div class="person"><?= avatar($s['name'], null, 'sm') ?><span class="person-name"><?= e($s['name']) ?></span></div></td>
                            <td><input type="number" step="0.01" min="0" max="<?= $total ?>" name="marks[<?= $s['id'] ?>]" aria-label="Marks for <?= e($s['name']) ?>"
                                       class="form-control form-control-sm mark-input" value="<?= $s['marks'] !== null ? (float)$s['marks'] : '' ?>" placeholder="—"></td>
                            <td class="grade-cell"><?= $s['grade'] ? '<span class="badge ' . ($s['grade'] === 'F' ? 'text-bg-danger' : 'text-bg-success') . '">' . e($s['grade']) . '</span>' : '<span class="text-muted">—</span>' ?></td>
                            <td class="gpa-cell num"><?= $s['gpa'] !== null ? number_format($s['gpa'], 2) : '<span class="text-muted">—</span>' ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$students): ?><?= empty_row(5, 'people', 'No active students in this batch') ?><?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($students): ?>
            <div class="form-actions sticky-actions">
                <button class="btn btn-brand"><i class="bi bi-check-lg"></i> Save marks</button>
                <?php if (!$exam['is_published']): ?>
                    <button name="publish" value="1" class="btn btn-success"><i class="bi bi-send"></i> Save &amp; publish result</button>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </form>
    </div>
    <div class="col-xl-4">
        <div class="card">
            <div class="card-header"><div><h2 class="section-title">Grading scale</h2><p class="section-sub">Based on percentage of total marks</p></div></div>
            <div class="card-body">
                <div class="grade-scale">
                    <?php foreach ([['A+', '80–100', '5.00'], ['A', '70–79', '4.00'], ['A-', '60–69', '3.50'], ['B', '50–59', '3.00'], ['C', '40–49', '2.00'], ['D', '33–39', '1.00'], ['F', '0–32', '0.00']] as [$g, $r, $p]): ?>
                        <div><strong><?= $g ?></strong> <span class="text-muted">· <?= $p ?></span><div class="text-muted"><?= $r ?>%</div></div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php
// Live grade preview in the browser (the server recalculates on save)
$extra_js = '<script>
const TOTAL = ' . json_encode($total) . ';
function grade(m) {
    const p = m / TOTAL * 100;
    if (p >= 80) return ["A+", 5]; if (p >= 70) return ["A", 4]; if (p >= 60) return ["A-", 3.5];
    if (p >= 50) return ["B", 3]; if (p >= 40) return ["C", 2]; if (p >= 33) return ["D", 1]; return ["F", 0];
}
const inputs = document.querySelectorAll(".mark-input");
inputs.forEach(inp => inp.addEventListener("input", () => {
    const tr = inp.closest("tr");
    const g = tr.querySelector(".grade-cell"), gp = tr.querySelector(".gpa-cell");
    const v = parseFloat(inp.value);
    inp.classList.toggle("is-invalid", inp.value !== "" && (isNaN(v) || v < 0 || v > TOTAL));
    if (inp.value === "" || isNaN(v)) { g.innerHTML = "<span class=\"text-muted\">—</span>"; gp.innerHTML = "<span class=\"text-muted\">—</span>"; }
    else {
        const [letter, gpa] = grade(v);
        g.innerHTML = "<span class=\"badge " + (letter === "F" ? "text-bg-danger" : "text-bg-success") + "\">" + letter + "</span>";
        gp.textContent = gpa.toFixed(2);
    }
    document.getElementById("enteredCount").textContent = [...inputs].filter(i => i.value !== "").length;
}));
</script>';
require __DIR__ . '/../includes/footer.php';
