<?php
/**
 * Create / edit exam (FR-EXM-1)
 */
require_once __DIR__ . '/../includes/functions.php';
require_role('admin', 'teacher');

$subjects = manageable_subjects();
$id = get_int('id');
$ex = ['subject_id' => '', 'exam_name' => '', 'exam_type' => 'Quiz', 'exam_date' => today(), 'total_marks' => 100];

if ($id) {
    $st = $pdo->prepare('SELECT * FROM exams WHERE id = ?');
    $st->execute([$id]);
    $ex = $st->fetch();
    if (!$ex || !can_manage_subject((int)$ex['subject_id'])) {
        flash('danger', 'Exam not found.');
        redirect('exams/index.php');
    }
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    foreach (['subject_id', 'exam_name', 'exam_type', 'exam_date', 'total_marks'] as $f) $ex[$f] = post($f);

    if (!can_manage_subject((int)$ex['subject_id'])) $errors[] = 'Select one of your subjects.';
    if ($ex['exam_name'] === '') $errors[] = 'Exam name is required.';
    if (!in_array($ex['exam_type'], ['Quiz', 'Midterm', 'Final', 'Assignment'], true)) $errors[] = 'Invalid exam type.';
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $ex['exam_date'])) $errors[] = 'Exam date is required.';
    if (!ctype_digit((string)$ex['total_marks']) || $ex['total_marks'] < 1 || $ex['total_marks'] > 1000) $errors[] = 'Total marks must be between 1 and 1000.';

    if (!$errors && $id) {
        // Cannot lower total marks below an already entered mark
        $st = $pdo->prepare('SELECT COALESCE(MAX(marks),0) FROM results WHERE exam_id = ?');
        $st->execute([$id]);
        if ($st->fetchColumn() > $ex['total_marks']) $errors[] = 'Some students already have more marks than this total.';
    }

    if (!$errors) {
        $data = [(int)$ex['subject_id'], $ex['exam_name'], $ex['exam_type'], $ex['exam_date'], (int)$ex['total_marks']];
        if ($id) {
            $data[] = $id;
            $pdo->prepare('UPDATE exams SET subject_id=?, exam_name=?, exam_type=?, exam_date=?, total_marks=? WHERE id=?')->execute($data);
            // Re-grade existing results in case total marks changed
            $st = $pdo->prepare('SELECT id, marks FROM results WHERE exam_id = ?');
            $st->execute([$id]);
            $up = $pdo->prepare('UPDATE results SET grade = ?, gpa = ? WHERE id = ?');
            foreach ($st->fetchAll() as $r) {
                [$g, $gpa] = calc_grade((float)$r['marks'], (float)$ex['total_marks']);
                $up->execute([$g, $gpa, $r['id']]);
            }
            flash('success', 'Exam updated.');
            redirect('exams/index.php');
        } else {
            $data[] = user_id();
            $pdo->prepare('INSERT INTO exams (subject_id, exam_name, exam_type, exam_date, total_marks, created_by) VALUES (?,?,?,?,?,?)')->execute($data);
            flash('success', 'Exam created. You can enter marks now.');
            redirect('exams/marks.php?id=' . $pdo->lastInsertId());
        }
    }
}

$page_title = $id ? 'Edit exam' : 'Create exam';
require __DIR__ . '/../includes/header.php';
?>
<?php page_head($page_title, $id ? 'Changing the total marks re-grades existing results automatically.' : 'After saving you will go straight to marks entry.', '', ['All exams', 'exams/index.php']); ?>
<?php form_errors($errors); ?>
<form method="post" class="card form-narrow needs-validation" novalidate>
    <?= csrf_field() ?>
    <div class="form-section">
        <div class="row g-3">
            <div class="col-12"><label class="form-label" for="subject_id">Batch &amp; subject<span class="req">*</span></label>
                <select name="subject_id" id="subject_id" class="form-select" required>
                    <option value="">Select</option>
                    <?php foreach ($subjects as $s): ?>
                        <option value="<?= $s['id'] ?>" <?= $ex['subject_id'] == $s['id'] ? 'selected' : '' ?>><?= e($s['batch_name']) ?> — <?= e($s['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="invalid-feedback">Choose a subject.</div></div>
            <div class="col-md-8"><label class="form-label" for="exam_name">Exam name<span class="req">*</span></label>
                <input name="exam_name" id="exam_name" class="form-control" value="<?= e($ex['exam_name']) ?>" placeholder="e.g. Physics Chapter 4 Test" required>
                <div class="invalid-feedback">Enter a name.</div></div>
            <div class="col-md-4"><label class="form-label" for="exam_type">Type</label>
                <select name="exam_type" id="exam_type" class="form-select">
                    <?php foreach (['Quiz', 'Midterm', 'Final', 'Assignment'] as $t): ?>
                        <option <?= $ex['exam_type'] === $t ? 'selected' : '' ?>><?= $t ?></option>
                    <?php endforeach; ?>
                </select></div>
            <div class="col-md-6"><label class="form-label" for="exam_date">Exam date<span class="req">*</span></label>
                <input type="date" name="exam_date" id="exam_date" class="form-control" value="<?= e($ex['exam_date']) ?>" required></div>
            <div class="col-md-6"><label class="form-label" for="total_marks">Total marks<span class="req">*</span></label>
                <input type="number" min="1" max="1000" name="total_marks" id="total_marks" class="form-control" value="<?= e($ex['total_marks']) ?>" required>
                <div class="invalid-feedback">Between 1 and 1000.</div></div>
        </div>
    </div>
    <div class="form-actions">
        <button class="btn btn-brand"><i class="bi bi-check-lg"></i> <?= $id ? 'Save changes' : 'Create & enter marks' ?></button>
        <a href="<?= url('exams/index.php') ?>" class="btn btn-light">Cancel</a>
    </div>
</form>
<?php require __DIR__ . '/../includes/footer.php'; ?>
