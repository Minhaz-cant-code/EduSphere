<?php
/** Set a new password using a reset token (FR-AUTH-3) */
require_once __DIR__ . '/includes/functions.php';

$token = $_GET['token'] ?? ($_POST['token'] ?? '');
$st = $pdo->prepare('SELECT id FROM users WHERE reset_token = ? AND reset_expires > ?');
$st->execute([hash('sha256', $token), now()]);
$uid = $token ? $st->fetchColumn() : false;

$error = '';
if ($uid && $_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $p1 = $_POST['password'] ?? '';
    $p2 = $_POST['confirm'] ?? '';
    if (strlen($p1) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($p1 !== $p2) {
        $error = 'Passwords do not match.';
    } else {
        $pdo->prepare('UPDATE users SET password = ?, reset_token = NULL, reset_expires = NULL,
                       failed_attempts = 0, locked_until = NULL WHERE id = ?')
            ->execute([password_hash($p1, PASSWORD_BCRYPT), $uid]);
        flash('success', 'Password changed. You can sign in now.');
        redirect('login.php');
    }
}

$page_title = 'Reset password';
require __DIR__ . '/includes/auth_header.php';
?>
<?php if (!$uid): ?>
    <div class="empty-state-icon mx-0 mb-4" style="background:var(--es-danger-soft);color:var(--es-danger);border-color:var(--es-danger-line)"><i class="bi bi-link-45deg"></i></div>
    <h2>This link has expired</h2>
    <p class="auth-sub">The reset link is invalid or older than 30 minutes. Request a new one to continue.</p>
    <a href="<?= url('forgot_password.php') ?>" class="btn btn-brand btn-lg">Request a new link</a>
<?php else: ?>
    <h2>Set a new password</h2>
    <p class="auth-sub">Choose a password with at least 6 characters.</p>
    <?php if ($error): ?><div class="alert alert-danger"><i class="bi bi-exclamation-circle"></i><div><?= e($error) ?></div></div><?php endif; ?>
    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="token" value="<?= e($token) ?>">
        <div class="auth-field">
            <label class="form-label" for="password">New password</label>
            <div class="input-icon">
                <i class="bi bi-lock"></i>
                <input type="password" id="password" name="password" class="form-control" minlength="6" autocomplete="new-password" required>
                <button type="button" class="input-action" data-toggle-password="password" aria-label="Show password"><i class="bi bi-eye"></i></button>
            </div>
        </div>
        <div class="auth-field">
            <label class="form-label" for="confirm">Confirm new password</label>
            <div class="input-icon">
                <i class="bi bi-lock"></i>
                <input type="password" id="confirm" name="confirm" class="form-control" minlength="6" autocomplete="new-password" required>
            </div>
        </div>
        <button class="btn btn-brand btn-lg mt-4">Save new password</button>
    </form>
<?php endif; ?>
<div class="auth-help"><i class="bi bi-arrow-left"></i><span><a href="<?= url('login.php') ?>">Back to sign in</a></span></div>
<?php require __DIR__ . '/includes/auth_footer.php'; ?>
