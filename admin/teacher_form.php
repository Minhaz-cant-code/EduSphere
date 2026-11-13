<?php
/**
 * Add / edit teacher + allocate subjects (FR-TCH-1, FR-TCH-2, FR-TCH-3, FR-BAT-3)
 */
require_once __DIR__ . '/../includes/functions.php';
require_role('admin');

$id = get_int('id');
$t = ['name' => '', 'email' => '', 'username' => '', 'qualification' => '', 'phone' => '',
      'salary' => '', 'joining_date' => today(), 'user_id' => 0];
$allocated = [];

if ($id) {
    $st = $pdo->prepare('SELECT t.*, u.name, u.email, u.username FROM teachers t JOIN users u ON u.id = t.user_id WHERE t.id = ?');
    $st->execute([$id]);
    $t = $st->fetch();
    if (!$t) {
        flash('danger', 'Teacher not found.');
        redirect('admin/teachers.php');
    }
    $st = $pdo->prepare('SELECT subject_id FROM teacher_subjects WHERE teacher_id = ?');
    $st->execute([$id]);
    $allocated = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    foreach (['name', 'email', 'username', 'qualification', 'phone', 'salary', 'joining_date'] as $f) {
        $t[$f] = post($f);
    }
    $password  = $_POST['password'] ?? '';
    $allocated = array_map('intval', $_POST['subjects'] ?? []);

    if ($t['name'] === '') $errors[] = 'Name is required.';
    if (!filter_var($t['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid email is required.';
    if (!preg_match('/^[A-Za-z0-9_.]{3,50}$/', $t['username'])) $errors[] = 'Username must be 3-50 letters, numbers, dot or underscore.';
    if ($t['salary'] !== '' && (!is_numeric($t['salary']) || $t['salary'] < 0)) $errors[] = 'Salary must be a positive number.';
    if (!$id && strlen($password) < 6) $errors[] = 'Password must be at least 6 characters.';
    if ($id && $password !== '' && strlen($password) < 6) $errors[] = 'New password must be at least 6 characters.';
    if (user_exists($t['username'], $t['email'], (int)$t['user_id'])) $errors[] = 'This username or email is already used.';

    if (!$errors) {
        $pdo->beginTransaction();
        try {
            $salary = $t['salary'] === '' ? 0 : (float)$t['salary'];
            $join   = $t['joining_date'] === '' ? null : $t['joining_date'];
            if ($id) {
                $pdo->prepare('UPDATE users SET name = ?, email = ?, username = ? WHERE id = ?')
                    ->execute([$t['name'], $t['email'], $t['username'], $t['user_id']]);
                if ($password !== '') {
                    $pdo->prepare('UPDATE users SET password = ?, failed_attempts = 0, locked_until = NULL WHERE id = ?')
                        ->execute([password_hash($password, PASSWORD_BCRYPT), $t['user_id']]);
                }
                $pdo->prepare('UPDATE teachers SET qualification = ?, phone = ?, salary = ?, joining_date = ? WHERE id = ?')
                    ->execute([$t['qualification'], $t['phone'], $salary, $join, $id]);
            } else {
                $uid = create_user($t['name'], $t['username'], $t['email'], $password, 'teacher');
                $pdo->prepare('INSERT INTO teachers (user_id, qualification, phone, salary, joining_date) VALUES (?,?,?,?,?)')
                    ->execute([$uid, $t['qualification'], $t['phone'], $salary, $join]);
                $id = (int)$pdo->lastInsertId();
            }

            // Re-save the subject allocation
            $pdo->prepare('DELETE FROM teacher_subjects WHERE teacher_id = ?')->execute([$id]);
            $ins = $pdo->prepare('INSERT INTO teacher_subjects (teacher_id, subject_id) VALUES (?, ?)');
            foreach (array_unique($allocated) as $sub) {
                $ins->execute([$id, $sub]);
            }
            $pdo->commit();
            flash('success', 'Teacher saved.');
            redirect('admin/teachers.php');
        } catch (PDOException $ex) {
            $pdo->rollBack();
            error_log($ex->getMessage());
            $errors[] = 'Could not save the teacher. Please try again.';
        }
    }
}

// Subjects grouped by batch for the allocation checkboxes
$subjects = $pdo->query(
    'SELECT s.id, s.name, b.name AS batch FROM subjects s JOIN batches b ON b.id = s.batch_id ORDER BY b.name, s.name'
)->fetchAll();
$grouped = [];
foreach ($subjects as $s) {
    $grouped[$s['batch']][] = $s;
}

$page_title = $id ? 'Edit teacher' : 'Add teacher';
require __DIR__ . '/../includes/header.php';
?>
<?php page_head($page_title, $id ? 'Update details, login and subject allocation.' : 'Create the teacher\'s profile, login and subject allocation.', '', ['All teachers', 'admin/teachers.php']); ?>
<?php form_errors($errors); ?>

<form method="post" class="needs-validation" novalidate>
    <?= csrf_field() ?>
    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card">
                <div class="form-section">
                    <div class="form-section-head"><h2>Teacher details</h2><p>Shown to admins only.</p></div>
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label" for="name">Full name<span class="req">*</span></label>
                            <input id="name" name="name" class="form-control" value="<?= e($t['name']) ?>" required>
                            <div class="invalid-feedback">Enter the teacher's name.</div></div>
                        <div class="col-md-6"><label class="form-label" for="qualification">Qualification</label>
                            <input id="qualification" name="qualification" class="form-control" value="<?= e($t['qualification']) ?>" placeholder="e.g. M.Sc in Physics"></div>
                        <div class="col-md-4"><label class="form-label" for="phone">Phone</label>
                            <input id="phone" name="phone" class="form-control" value="<?= e($t['phone']) ?>"></div>
                        <div class="col-md-4"><label class="form-label" for="salary">Monthly salary</label>
                            <div class="input-group"><span class="input-group-text">৳</span>
                                <input type="number" step="0.01" min="0" id="salary" name="salary" class="form-control" value="<?= e($t['salary']) ?>"></div></div>
                        <div class="col-md-4"><label class="form-label" for="joining_date">Joining date</label>
                            <input type="date" id="joining_date" name="joining_date" class="form-control" value="<?= e($t['joining_date']) ?>"></div>
                    </div>
                </div>
                <div class="form-section">
                    <div class="form-section-head"><h2>Login account</h2><p>The teacher signs in with these details.</p></div>
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label" for="email">Email<span class="req">*</span></label>
                            <input type="email" id="email" name="email" class="form-control" value="<?= e($t['email']) ?>" required>
                            <div class="invalid-feedback">Enter a valid email.</div></div>
                        <div class="col-md-6"><label class="form-label" for="username">Username<span class="req">*</span></label>
                            <input id="username" name="username" class="form-control" value="<?= e($t['username']) ?>" required></div>
                        <div class="col-md-6"><label class="form-label" for="password"><?= $id ? 'Reset password' : 'Password' ?><?= $id ? '' : '<span class="req">*</span>' ?></label>
                            <div class="input-icon"><i class="bi bi-lock"></i>
                                <input type="password" id="password" name="password" class="form-control" autocomplete="new-password" <?= $id ? 'placeholder="Leave empty to keep current"' : 'required minlength="6"' ?>>
                                <button type="button" class="input-action" data-toggle-password="password" aria-label="Show password"><i class="bi bi-eye"></i></button></div>
                            <span class="form-hint">At least 6 characters.</span></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card">
                <div class="form-section">
                    <div class="form-section-head"><h2>Subjects taught</h2><p>The teacher can mark attendance, create exams and upload assignments for these.</p></div>
                    <?php foreach ($grouped as $batch => $subs): ?>
                        <div class="small fw-semibold text-muted text-uppercase mt-3 mb-2" style="letter-spacing:.04em;font-size:11px"><?= e($batch) ?></div>
                        <div class="check-grid">
                            <?php foreach ($subs as $s): ?>
                                <label class="check-tile" for="sub<?= $s['id'] ?>">
                                    <input class="form-check-input" type="checkbox" name="subjects[]" value="<?= $s['id'] ?>" id="sub<?= $s['id'] ?>"
                                        <?= in_array((int)$s['id'], $allocated, true) ? 'checked' : '' ?>>
                                    <span><?= e($s['name']) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                    <?php if (!$grouped): ?>
                        <?= empty_state('book', 'No subjects yet', 'Create batches and subjects first, then allocate them here.', '<a class="btn btn-sm btn-light" href="' . url('admin/subjects.php') . '">Manage subjects</a>', true) ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <div class="d-flex gap-2 mt-4">
        <button class="btn btn-brand"><i class="bi bi-check-lg"></i> <?= $id ? 'Save changes' : 'Create teacher' ?></button>
        <a href="<?= url('admin/teachers.php') ?>" class="btn btn-light">Cancel</a>
    </div>
</form>
<?php require __DIR__ . '/../includes/footer.php'; ?>
