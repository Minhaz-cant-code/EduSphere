<?php
/**
 * Add / edit student (FR-STU-1, FR-STU-2, FR-STU-5, FR-BAT-2)
 * Creates the login account (users) and the profile (students) together.
 */
require_once __DIR__ . '/../includes/functions.php';
require_role('admin');

$id = get_int('id');
$student = [
    'name' => '', 'email' => '', 'username' => '', 'batch_id' => '', 'roll_no' => '',
    'dob' => '', 'gender' => '', 'phone' => '', 'address' => '', 'guardian_name' => '',
    'admission_date' => today(), 'photo' => null, 'user_id' => 0,
];

if ($id) {
    $st = $pdo->prepare('SELECT s.*, u.name, u.email, u.username FROM students s JOIN users u ON u.id = s.user_id WHERE s.id = ?');
    $st->execute([$id]);
    $student = $st->fetch();
    if (!$student) {
        flash('danger', 'Student not found.');
        redirect('admin/students.php');
    }
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $fields = ['name', 'email', 'username', 'batch_id', 'roll_no', 'dob', 'gender', 'phone', 'address', 'guardian_name', 'admission_date'];
    foreach ($fields as $f) {
        $student[$f] = post($f);
    }
    $password = $_POST['password'] ?? '';

    // ---- Server-side validation ----
    if ($student['name'] === '')     $errors[] = 'Name is required.';
    if (!filter_var($student['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid email is required.';
    if (!preg_match('/^[A-Za-z0-9_.]{3,50}$/', $student['username'])) $errors[] = 'Username must be 3-50 letters, numbers, dot or underscore.';
    if ($student['roll_no'] === '')  $errors[] = 'Roll number is required.';
    if (!$student['batch_id'])       $errors[] = 'Please select a batch.';
    if (!$id && strlen($password) < 6) $errors[] = 'Password must be at least 6 characters.';
    if ($id && $password !== '' && strlen($password) < 6) $errors[] = 'New password must be at least 6 characters.';
    if ($student['gender'] !== '' && !in_array($student['gender'], ['Male', 'Female', 'Other'], true)) $errors[] = 'Invalid gender.';
    if (user_exists($student['username'], $student['email'], (int)$student['user_id'])) {
        $errors[] = 'This username or email is already used by another account.';
    }

    $photo = null;
    if (!$errors) {
        try {
            $photo = save_upload('photo', 'photos', ['jpg', 'jpeg', 'png', 'webp']);
        } catch (RuntimeException $ex) {
            $errors[] = $ex->getMessage();
        }
    }

    if (!$errors) {
        $nullable = fn($v) => $v === '' ? null : $v;
        $pdo->beginTransaction();
        try {
            if ($id) {
                $pdo->prepare('UPDATE users SET name = ?, email = ?, username = ? WHERE id = ?')
                    ->execute([$student['name'], $student['email'], $student['username'], $student['user_id']]);
                if ($password !== '') {
                    $pdo->prepare('UPDATE users SET password = ?, failed_attempts = 0, locked_until = NULL WHERE id = ?')
                        ->execute([password_hash($password, PASSWORD_BCRYPT), $student['user_id']]);
                }
                $user_id = $student['user_id'];
            } else {
                $user_id = create_user($student['name'], $student['username'], $student['email'], $password, 'student');
            }

            $data = [
                (int)$student['batch_id'], $student['roll_no'], $nullable($student['dob']), $nullable($student['gender']),
                $student['phone'], $student['address'], $student['guardian_name'], $nullable($student['admission_date']),
            ];

            if ($id) {
                $sql = 'UPDATE students SET batch_id=?, roll_no=?, dob=?, gender=?, phone=?, address=?, guardian_name=?, admission_date=?';
                if ($photo) {
                    $sql .= ', photo=?';
                    $data[] = $photo;
                }
                $sql .= ' WHERE id=?';
                $data[] = $id;
                $pdo->prepare($sql)->execute($data);
                if ($photo) delete_upload($student['photo']);
            } else {
                $data[] = $photo;
                array_unshift($data, $user_id);
                $pdo->prepare('INSERT INTO students (user_id, batch_id, roll_no, dob, gender, phone, address, guardian_name, admission_date, photo)
                               VALUES (?,?,?,?,?,?,?,?,?,?)')->execute($data);
                $id = (int)$pdo->lastInsertId();
            }
            $pdo->commit();
            flash('success', 'Student saved successfully.');
            redirect('admin/student_profile.php?id=' . $id);
        } catch (PDOException $ex) {
            $pdo->rollBack();
            delete_upload($photo);
            error_log($ex->getMessage());
            $errors[] = 'Could not save the student. Please check the data and try again.';
        }
    }
}

$batches = $pdo->query('SELECT id, name FROM batches ORDER BY name')->fetchAll();

$page_title = $id ? 'Edit student' : 'Add student';
require __DIR__ . '/../includes/header.php';
?>
<?php page_head($page_title, $id ? 'Update the profile, batch or login of ' . e($student['name']) . '.' : 'Create the student profile and their login account in one step.', '',
    $id ? ['Back to profile', 'admin/student_profile.php?id=' . $id] : ['All students', 'admin/students.php']); ?>

<?php form_errors($errors); ?>

<form method="post" enctype="multipart/form-data" class="needs-validation" novalidate>
    <?= csrf_field() ?>
    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card">
                <div class="form-section">
                    <div class="form-section-head"><h2>Personal details</h2><p>Basic information about the student.</p></div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="name">Full name<span class="req">*</span></label>
                            <input id="name" name="name" class="form-control" value="<?= e($student['name']) ?>" required>
                            <div class="invalid-feedback">Enter the student's name.</div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="dob">Date of birth</label>
                            <input type="date" id="dob" name="dob" class="form-control" value="<?= e($student['dob']) ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="gender">Gender</label>
                            <select id="gender" name="gender" class="form-select">
                                <option value="">Select</option>
                                <?php foreach (['Male', 'Female', 'Other'] as $g): ?>
                                    <option <?= $student['gender'] === $g ? 'selected' : '' ?>><?= $g ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="guardian_name">Guardian name</label>
                            <input id="guardian_name" name="guardian_name" class="form-control" value="<?= e($student['guardian_name']) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="phone">Phone</label>
                            <input id="phone" name="phone" class="form-control" value="<?= e($student['phone']) ?>" pattern="[0-9+\- ]{6,20}" placeholder="01XXXXXXXXX">
                            <div class="invalid-feedback">Use digits only (6–20).</div>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="address">Address</label>
                            <input id="address" name="address" class="form-control" value="<?= e($student['address']) ?>">
                        </div>
                    </div>
                </div>
                <div class="form-section">
                    <div class="form-section-head"><h2>Enrolment</h2><p>The batch decides which subjects, exams and assignments the student sees.</p></div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="batch_id">Batch<span class="req">*</span></label>
                            <select id="batch_id" name="batch_id" class="form-select" required>
                                <option value="">Select batch</option>
                                <?php foreach ($batches as $b): ?>
                                    <option value="<?= $b['id'] ?>" <?= $student['batch_id'] == $b['id'] ? 'selected' : '' ?>><?= e($b['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">Choose a batch.</div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="roll_no">Roll no<span class="req">*</span></label>
                            <input id="roll_no" name="roll_no" class="form-control" value="<?= e($student['roll_no']) ?>" required>
                            <div class="invalid-feedback">Required.</div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="admission_date">Admission date</label>
                            <input type="date" id="admission_date" name="admission_date" class="form-control" value="<?= e($student['admission_date']) ?>">
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card mb-4">
                <div class="form-section">
                    <div class="form-section-head"><h2>Login account</h2><p>The student signs in with these details.</p></div>
                    <div class="mb-3">
                        <label class="form-label" for="email">Email<span class="req">*</span></label>
                        <input type="email" id="email" name="email" class="form-control" value="<?= e($student['email']) ?>" required>
                        <div class="invalid-feedback">Enter a valid email.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="username">Username<span class="req">*</span></label>
                        <input id="username" name="username" class="form-control" value="<?= e($student['username']) ?>" required>
                        <span class="form-hint">3–50 letters, numbers, dot or underscore.</span>
                    </div>
                    <div>
                        <label class="form-label" for="password"><?= $id ? 'Reset password' : 'Password' ?><?= $id ? '' : '<span class="req">*</span>' ?></label>
                        <div class="input-icon">
                            <i class="bi bi-lock"></i>
                            <input type="password" id="password" name="password" class="form-control" autocomplete="new-password" <?= $id ? 'placeholder="Leave empty to keep current"' : 'required minlength="6"' ?>>
                            <button type="button" class="input-action" data-toggle-password="password" aria-label="Show password"><i class="bi bi-eye"></i></button>
                        </div>
                        <span class="form-hint">At least 6 characters.</span>
                    </div>
                </div>
            </div>
            <div class="card">
                <div class="form-section">
                    <div class="form-section-head"><h2>Photo</h2><p>Optional. JPG, PNG or WEBP up to <?= MAX_UPLOAD_MB ?> MB.</p></div>
                    <div class="photo-drop">
                        <?= avatar($student['name'] ?: 'New Student', $student['photo'], 'lg') ?>
                        <input type="file" name="photo" class="form-control" accept="image/*" aria-label="Photo">
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="d-flex gap-2 mt-4">
        <button class="btn btn-brand"><i class="bi bi-check-lg"></i> <?= $id ? 'Save changes' : 'Create student' ?></button>
        <a href="<?= url($id ? 'admin/student_profile.php?id=' . $id : 'admin/students.php') ?>" class="btn btn-light">Cancel</a>
    </div>
</form>
<?php require __DIR__ . '/../includes/footer.php'; ?>
