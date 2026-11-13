<?php
/** Logged-in user changes own password */
require_once __DIR__ . '/includes/functions.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $st = $pdo->prepare('SELECT password FROM users WHERE id = ?');
    $st->execute([user_id()]);
    $hash = $st->fetchColumn();

    $new = $_POST['new'] ?? '';
    if (!password_verify($_POST['current'] ?? '', $hash)) {
        flash('danger', 'Current password is wrong.');
    } elseif (strlen($new) < 6) {
        flash('danger', 'New password must be at least 6 characters.');
    } elseif ($new !== ($_POST['confirm'] ?? '')) {
        flash('danger', 'New passwords do not match.');
    } else {
        $pdo->prepare('UPDATE users SET password = ? WHERE id = ?')
            ->execute([password_hash($new, PASSWORD_BCRYPT), user_id()]);
        flash('success', 'Password changed successfully.');
        redirect('dashboard.php');
    }
    redirect('change_password.php');
}

$page_title = 'Change password';
require __DIR__ . '/includes/header.php';
?>
<?php page_head('Change password', 'Use at least 6 characters. You stay signed in after changing it.'); ?>
<form method="post" class="card form-xnarrow needs-validation" novalidate>
    <?= csrf_field() ?>
    <div class="form-section">
        <div class="mb-3">
            <label class="form-label" for="current">Current password</label>
            <div class="input-icon"><i class="bi bi-lock"></i>
                <input type="password" name="current" id="current" class="form-control" autocomplete="current-password" required></div>
        </div>
        <div class="mb-3">
            <label class="form-label" for="new">New password</label>
            <div class="input-icon"><i class="bi bi-key"></i>
                <input type="password" name="new" id="new" class="form-control" minlength="6" autocomplete="new-password" required>
                <button type="button" class="input-action" data-toggle-password="new" aria-label="Show password"><i class="bi bi-eye"></i></button></div>
        </div>
        <div>
            <label class="form-label" for="confirm">Confirm new password</label>
            <div class="input-icon"><i class="bi bi-key"></i>
                <input type="password" name="confirm" id="confirm" class="form-control" minlength="6" autocomplete="new-password" required></div>
        </div>
    </div>
    <div class="form-actions">
        <button class="btn btn-brand"><i class="bi bi-check-lg"></i> Update password</button>
        <a href="<?= url('dashboard.php') ?>" class="btn btn-light">Cancel</a>
    </div>
</form>
<?php require __DIR__ . '/includes/footer.php'; ?>
