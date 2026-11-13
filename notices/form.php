<?php
/**
 * Post / edit a notice (FR-NOT-1) - Admin & Teacher
 */
require_once __DIR__ . '/../includes/functions.php';
require_role('admin', 'teacher');

$id = get_int('id');
$n = ['title' => '', 'body' => '', 'category' => 'General', 'target_role' => 'all', 'expires_on' => ''];
if ($id) {
    $st = $pdo->prepare('SELECT * FROM notices WHERE id = ?');
    $st->execute([$id]);
    $n = $st->fetch();
    if (!$n || (role() === 'teacher' && (int)$n['posted_by'] !== user_id())) {
        flash('danger', 'Notice not found.');
        redirect('notices/index.php');
    }
}

$categories = ['General', 'Holiday', 'Exam', 'Routine', 'Event', 'Emergency'];
$targets = ['all' => 'Everyone', 'student' => 'Students', 'parent' => 'Parents', 'teacher' => 'Teachers'];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    foreach (['title', 'body', 'category', 'target_role', 'expires_on'] as $f) $n[$f] = post($f);

    if ($n['title'] === '') $errors[] = 'Title is required.';
    if ($n['body'] === '') $errors[] = 'Notice text is required.';
    if (!in_array($n['category'], $categories, true)) $n['category'] = 'General';
    if (!isset($targets[$n['target_role']])) $n['target_role'] = 'all';
    if ($n['expires_on'] !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $n['expires_on'])) $errors[] = 'Invalid expiry date.';

    if (!$errors) {
        $exp = $n['expires_on'] ?: null;
        if ($id) {
            $pdo->prepare('UPDATE notices SET title = ?, body = ?, category = ?, target_role = ?, expires_on = ? WHERE id = ?')
                ->execute([$n['title'], $n['body'], $n['category'], $n['target_role'], $exp, $id]);
            flash('success', 'Notice updated.');
        } else {
            $pdo->prepare('INSERT INTO notices (title, body, category, target_role, posted_by, created_at, expires_on) VALUES (?,?,?,?,?,?,?)')
                ->execute([$n['title'], $n['body'], $n['category'], $n['target_role'], user_id(), now(), $exp]);
            flash('success', 'Notice published.');
        }
        redirect('notices/index.php');
    }
}

$page_title = $id ? 'Edit notice' : 'Post notice';
require __DIR__ . '/../includes/header.php';
?>
<?php page_head($page_title, 'Choose who should see it. Expired notices are hidden automatically.', '', ['Notice Board', 'notices/index.php']); ?>
<?php form_errors($errors); ?>
<form method="post" class="card form-narrow needs-validation" novalidate>
    <?= csrf_field() ?>
    <div class="form-section">
        <div class="row g-3">
            <div class="col-12"><label class="form-label" for="title">Title<span class="req">*</span></label>
                <input name="title" id="title" class="form-control" value="<?= e($n['title']) ?>" maxlength="150" required>
                <div class="invalid-feedback">Enter a title.</div></div>
            <div class="col-md-4"><label class="form-label" for="category">Category</label>
                <select name="category" id="category" class="form-select">
                    <?php foreach ($categories as $c): ?><option <?= $n['category'] === $c ? 'selected' : '' ?>><?= $c ?></option><?php endforeach; ?>
                </select></div>
            <div class="col-md-4"><label class="form-label" for="target_role">Show to</label>
                <select name="target_role" id="target_role" class="form-select">
                    <?php foreach ($targets as $k => $v): ?><option value="<?= $k ?>" <?= $n['target_role'] === $k ? 'selected' : '' ?>><?= $v ?></option><?php endforeach; ?>
                </select></div>
            <div class="col-md-4"><label class="form-label" for="expires_on">Hide after</label>
                <input type="date" name="expires_on" id="expires_on" class="form-control" value="<?= e($n['expires_on']) ?>">
                <span class="form-hint">Optional</span></div>
            <div class="col-12"><label class="form-label" for="body">Notice<span class="req">*</span></label>
                <textarea name="body" id="body" rows="7" class="form-control" required><?= e($n['body']) ?></textarea>
                <div class="invalid-feedback">Write the notice text.</div></div>
        </div>
    </div>
    <div class="form-actions">
        <button class="btn btn-brand"><i class="bi bi-megaphone"></i> <?= $id ? 'Save changes' : 'Publish notice' ?></button>
        <a href="<?= url('notices/index.php') ?>" class="btn btn-light">Cancel</a>
    </div>
</form>
<?php require __DIR__ . '/../includes/footer.php'; ?>
