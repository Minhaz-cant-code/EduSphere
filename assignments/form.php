<?php
/**
 * Upload / edit an assignment (FR-ASG-1) - Teacher only
 */
require_once __DIR__ . '/../includes/functions.php';
require_role('teacher');

$subjects = teacher_subjects();
$id = get_int('id');
$a = ['subject_id' => '', 'title' => '', 'description' => '', 'deadline' => date('Y-m-d\T23:59', strtotime('+7 days')), 'file_path' => null];

if ($id) {
    $st = $pdo->prepare('SELECT * FROM assignments WHERE id = ? AND teacher_id = ?');
    $st->execute([$id, my_teacher_id()]);
    $a = $st->fetch();
    if (!$a) {
        flash('danger', 'Assignment not found.');
        redirect('assignments/index.php');
    }
    $a['deadline'] = date('Y-m-d\TH:i', strtotime($a['deadline']));
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    foreach (['subject_id', 'title', 'description', 'deadline'] as $f) $a[$f] = post($f);

    if (!can_manage_subject((int)$a['subject_id'])) $errors[] = 'Select one of your subjects.';
    if ($a['title'] === '') $errors[] = 'Title is required.';
    $deadline = strtotime($a['deadline']);
    if (!$deadline) $errors[] = 'Deadline is required.';

    $file = null;
    if (!$errors) {
        try {
            $file = save_upload('file', 'assignments', ['pdf']);
        } catch (RuntimeException $ex) {
            $errors[] = $ex->getMessage();
        }
        if (!$id && !$file && $a['description'] === '') {
            $errors[] = 'Upload a PDF file or write the assignment instructions.';
        }
    }

    if (!$errors) {
        $dl = date('Y-m-d H:i:s', $deadline);
        if ($id) {
            $sql = 'UPDATE assignments SET subject_id = ?, title = ?, description = ?, deadline = ?';
            $params = [(int)$a['subject_id'], $a['title'], $a['description'], $dl];
            if ($file) {
                $sql .= ', file_path = ?';
                $params[] = $file;
                delete_upload($a['file_path']);
            }
            $params[] = $id;
            $pdo->prepare($sql . ' WHERE id = ?')->execute($params);
            flash('success', 'Assignment updated.');
        } else {
            $pdo->prepare('INSERT INTO assignments (subject_id, teacher_id, title, description, file_path, deadline, created_at) VALUES (?,?,?,?,?,?,?)')
                ->execute([(int)$a['subject_id'], my_teacher_id(), $a['title'], $a['description'], $file, $dl, now()]);
            flash('success', 'Assignment published to the batch.');
        }
        redirect('assignments/index.php');
    }
}

$page_title = $id ? 'Edit assignment' : 'Upload assignment';
require __DIR__ . '/../includes/header.php';
?>
<?php page_head($page_title, 'Students in the batch see it right away and can submit until the deadline.', '', ['All assignments', 'assignments/index.php']); ?>
<?php form_errors($errors); ?>
<?php if (!$subjects): ?>
    <div class="card"><?= empty_state('book', 'No subjects assigned to you', 'Ask the admin to allocate subjects before uploading assignments.') ?></div>
<?php else: ?>
<form method="post" enctype="multipart/form-data" class="card form-narrow needs-validation" novalidate>
    <?= csrf_field() ?>
    <div class="form-section">
        <div class="row g-3">
            <div class="col-md-7"><label class="form-label" for="subject_id">Batch &amp; subject<span class="req">*</span></label>
                <select name="subject_id" id="subject_id" class="form-select" required>
                    <option value="">Select</option>
                    <?php foreach ($subjects as $s): ?>
                        <option value="<?= $s['id'] ?>" <?= $a['subject_id'] == $s['id'] ? 'selected' : '' ?>><?= e($s['batch_name']) ?> — <?= e($s['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="invalid-feedback">Choose a subject.</div></div>
            <div class="col-md-5"><label class="form-label" for="deadline">Deadline<span class="req">*</span></label>
                <input type="datetime-local" name="deadline" id="deadline" class="form-control" value="<?= e($a['deadline']) ?>" required></div>
            <div class="col-12"><label class="form-label" for="title">Title<span class="req">*</span></label>
                <input name="title" id="title" class="form-control" value="<?= e($a['title']) ?>" placeholder="e.g. Newton's Laws problem set" required>
                <div class="invalid-feedback">Enter a title.</div></div>
            <div class="col-12"><label class="form-label" for="description">Instructions</label>
                <textarea name="description" id="description" rows="5" class="form-control" placeholder="What should students do? How should they submit?"><?= e($a['description']) ?></textarea></div>
            <div class="col-12"><label class="form-label" for="file">Assignment file (PDF)</label>
                <input type="file" name="file" id="file" class="form-control" accept="application/pdf">
                <span class="form-hint"><?= $a['file_path'] ? 'A file is already attached. Choose a new one only to replace it. ' : '' ?>PDF up to <?= MAX_UPLOAD_MB ?> MB. Upload a file or write instructions, or both.</span></div>
        </div>
    </div>
    <div class="form-actions">
        <button class="btn btn-brand"><i class="bi bi-upload"></i> <?= $id ? 'Save changes' : 'Publish assignment' ?></button>
        <a href="<?= url('assignments/index.php') ?>" class="btn btn-light">Cancel</a>
    </div>
</form>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
