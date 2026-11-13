<?php
/**
 * Add / edit a parent account linked to one student
 */
require_once __DIR__ . '/../includes/functions.php';
require_role('admin');

$id = get_int('id');
$p = ['name' => '', 'email' => '', 'username' => '', 'student_id' => get_int('student_id'),
      'relation' => 'Father', 'phone' => '', 'status' => 'active', 'user_id' => 0];

if ($id) {
    $st = $pdo->prepare('SELECT p.*, u.name, u.email, u.username, u.status FROM parents p JOIN users u ON u.id = p.user_id WHERE p.id = ?');
    $st->execute([$id]);
    $p = $st->fetch();
    if (!$p) {
        flash('danger', 'Parent not found.');
        redirect('admin/parents.php');
    }
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    foreach (['name', 'email', 'username', 'student_id', 'relation', 'phone', 'status'] as $f) {
        $p[$f] = post($f);
    }
    $password = $_POST['password'] ?? '';

    if ($p['name'] === '') $errors[] = 'Name is required.';
    if (!filter_var($p['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid email is required.';
    if (!preg_match('/^[A-Za-z0-9_.]{3,50}$/', $p['username'])) $errors[] = 'Username must be 3-50 letters, numbers, dot or underscore.';
    if (!(int)$p['student_id']) $errors[] = 'Please select the child (student).';
    if (!in_array($p['status'], ['active', 'inactive'], true)) $p['status'] = 'active';
    if (!$id && strlen($password) < 6) $errors[] = 'Password must be at least 6 characters.';
    if ($id && $password !== '' && strlen($password) < 6) $errors[] = 'New password must be at least 6 characters.';
    if (user_exists($p['username'], $p['email'], (int)$p['user_id'])) $errors[] = 'This username or email is already used.';

    if (!$errors) {
        $pdo->beginTransaction();
        try {
            if ($id) {
                $pdo->prepare('UPDATE users SET name = ?, email = ?, username = ?, status = ? WHERE id = ?')
                    ->execute([$p['name'], $p['email'], $p['username'], $p['status'], $p['user_id']]);
                if ($password !== '') {
                    $pdo->prepare('UPDATE users SET password = ? WHERE id = ?')
                        ->execute([password_hash($password, PASSWORD_BCRYPT), $p['user_id']]);
                }
                $pdo->prepare('UPDATE parents SET student_id = ?, relation = ?, phone = ? WHERE id = ?')
                    ->execute([(int)$p['student_id'], $p['relation'], $p['phone'], $id]);
            } else {
                $uid = create_user($p['name'], $p['username'], $p['email'], $password, 'parent');
                $pdo->prepare('INSERT INTO parents (user_id, student_id, relation, phone) VALUES (?,?,?,?)')
                    ->execute([$uid, (int)$p['student_id'], $p['relation'], $p['phone']]);
            }
            $pdo->commit();
            flash('success', 'Parent account saved.');
            redirect('admin/parents.php');
        } catch (PDOException $ex) {
            $pdo->rollBack();
            error_log($ex->getMessage());
            $errors[] = 'Could not save. Please try again.';
        }
    }
}

$students = $pdo->query(
    'SELECT s.id, u.name, s.roll_no, b.name AS batch FROM students s JOIN users u ON u.id = s.user_id
       LEFT JOIN batches b ON b.id = s.batch_id ORDER BY u.name'
)->fetchAll();

$page_title = $id ? 'Edit parent' : 'Add parent';
require __DIR__ . '/../includes/header.php';
?>
<?php page_head($page_title, 'A parent account can only view the linked child\'s records.', '', ['All parents', 'admin/parents.php']); ?>
<?php form_errors($errors); ?>
<form method="post" class="card form-narrow needs-validation" novalidate>
    <?= csrf_field() ?>
    <div class="form-section">
        <div class="form-section-head"><h2>Parent &amp; child</h2></div>
        <div class="row g-3">
            <div class="col-md-6"><label class="form-label" for="name">Parent name<span class="req">*</span></label>
                <input id="name" name="name" class="form-control" value="<?= e($p['name']) ?>" required>
                <div class="invalid-feedback">Enter the parent's name.</div></div>
            <div class="col-md-6"><label class="form-label" for="phone">Phone</label>
                <input id="phone" name="phone" class="form-control" value="<?= e($p['phone']) ?>"></div>
            <div class="col-md-8"><label class="form-label" for="student_id">Child<span class="req">*</span></label>
                <select id="student_id" name="student_id" class="form-select" required>
                    <option value="">Select student</option>
                    <?php foreach ($students as $s): ?>
                        <option value="<?= $s['id'] ?>" <?= $p['student_id'] == $s['id'] ? 'selected' : '' ?>>
                            <?= e($s['name']) ?> (<?= e($s['roll_no']) ?>, <?= e($s['batch']) ?>)</option>
                    <?php endforeach; ?>
                </select>
                <div class="invalid-feedback">Choose the child.</div></div>
            <div class="col-md-4"><label class="form-label" for="relation">Relation</label>
                <select id="relation" name="relation" class="form-select">
                    <?php foreach (['Father', 'Mother', 'Guardian'] as $r): ?>
                        <option <?= $p['relation'] === $r ? 'selected' : '' ?>><?= $r ?></option>
                    <?php endforeach; ?>
                </select></div>
        </div>
    </div>
    <div class="form-section">
        <div class="form-section-head"><h2>Login account</h2></div>
        <div class="row g-3">
            <div class="col-md-6"><label class="form-label" for="email">Email<span class="req">*</span></label>
                <input type="email" id="email" name="email" class="form-control" value="<?= e($p['email']) ?>" required>
                <div class="invalid-feedback">Enter a valid email.</div></div>
            <div class="col-md-6"><label class="form-label" for="username">Username<span class="req">*</span></label>
                <input id="username" name="username" class="form-control" value="<?= e($p['username']) ?>" required></div>
            <div class="col-md-6"><label class="form-label" for="password"><?= $id ? 'Reset password' : 'Password' ?><?= $id ? '' : '<span class="req">*</span>' ?></label>
                <div class="input-icon"><i class="bi bi-lock"></i>
                    <input type="password" id="password" name="password" class="form-control" autocomplete="new-password" <?= $id ? 'placeholder="Leave empty to keep current"' : 'required minlength="6"' ?>>
                    <button type="button" class="input-action" data-toggle-password="password" aria-label="Show password"><i class="bi bi-eye"></i></button></div></div>
            <div class="col-md-6"><label class="form-label" for="status">Status</label>
                <select id="status" name="status" class="form-select">
                    <option value="active" <?= $p['status'] === 'active' ? 'selected' : '' ?>>Active, can sign in</option>
                    <option value="inactive" <?= $p['status'] === 'inactive' ? 'selected' : '' ?>>Inactive, sign-in blocked</option>
                </select></div>
        </div>
    </div>
    <div class="form-actions">
        <button class="btn btn-brand"><i class="bi bi-check-lg"></i> <?= $id ? 'Save changes' : 'Create parent account' ?></button>
        <a href="<?= url('admin/parents.php') ?>" class="btn btn-light">Cancel</a>
    </div>
</form>
<?php require __DIR__ . '/../includes/footer.php'; ?>
