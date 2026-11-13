<?php
/**
 * Forgot password (FR-AUTH-3)
 * Creates a one-time reset token valid for 30 minutes.
 * Version 1 has no email service, so the link is displayed on screen
 * (controlled by SHOW_RESET_LINK in config). The admin can also reset
 * any password from the user's edit page.
 */
require_once __DIR__ . '/includes/functions.php';

$reset_link = '';
$sent = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $login = post('login');
    $st = $pdo->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND status = 'active'");
    $st->execute([$login, $login]);
    $uid = $st->fetchColumn();

    if ($uid) {
        $token = bin2hex(random_bytes(32));
        // store only a hash of the token, like a password
        $pdo->prepare('UPDATE users SET reset_token = ?, reset_expires = ? WHERE id = ?')
            ->execute([hash('sha256', $token), date('Y-m-d H:i:s', time() + 1800), $uid]);
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $reset_link = $scheme . '://' . $_SERVER['HTTP_HOST'] . url('reset_password.php?token=' . $token);
    }
    $sent = true;
}

$page_title = 'Forgot password';
require __DIR__ . '/includes/auth_header.php';
?>
<?php if ($sent): ?>
    <div class="empty-state-icon mx-0 mb-4" style="background:var(--es-success-soft);color:var(--es-success);border-color:var(--es-success-line)"><i class="bi bi-envelope-check"></i></div>
    <h2>Check your reset link</h2>
    <p class="auth-sub">If an active account matches what you entered, a password reset link has been created. It is valid for 30 minutes.</p>
    <?php if ($reset_link && SHOW_RESET_LINK): ?>
        <div class="alert alert-warning small">
            <i class="bi bi-cone-striped"></i>
            <div><strong>Demo mode:</strong> Version 1 has no email service, so the link is shown here.<br>
                <a href="<?= e($reset_link) ?>" class="text-break">Open the reset link</a></div>
        </div>
    <?php endif; ?>
<?php else: ?>
    <h2>Forgot your password?</h2>
    <p class="auth-sub">Enter your username or email and we'll create a link to set a new password.</p>
    <form method="post" class="needs-validation" novalidate>
        <?= csrf_field() ?>
        <div class="auth-field">
            <label class="form-label" for="login">Username or email</label>
            <div class="input-icon">
                <i class="bi bi-person"></i>
                <input type="text" id="login" name="login" class="form-control" placeholder="Username or email" required autofocus>
            </div>
            <div class="invalid-feedback">Enter your username or email.</div>
        </div>
        <button class="btn btn-brand btn-lg mt-4">Create reset link</button>
    </form>
<?php endif; ?>
<div class="auth-help">
    <i class="bi bi-arrow-left"></i>
    <span>Remembered it? <a href="<?= url('login.php') ?>">Back to sign in</a>. You can also ask the office to reset your password.</span>
</div>
<?php require __DIR__ . '/includes/auth_footer.php'; ?>
