<?php
/**
 * Login (FR-AUTH-1, FR-AUTH-5)
 * Accepts username OR email + password. Locks the account for
 * LOCKOUT_MINUTES after MAX_LOGIN_ATTEMPTS wrong passwords.
 */
require_once __DIR__ . '/includes/functions.php';

if (is_logged_in()) {
    redirect('dashboard.php');
}

$error = '';
$login = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $login    = post('login');
    $password = $_POST['password'] ?? '';

    if ($login === '' || $password === '') {
        $error = 'Please enter your username/email and password.';
    } else {
        $st = $pdo->prepare('SELECT * FROM users WHERE username = ? OR email = ? LIMIT 1');
        $st->execute([$login, $login]);
        $u = $st->fetch();

        if ($u && $u['locked_until'] && strtotime($u['locked_until']) > time()) {
            $mins = ceil((strtotime($u['locked_until']) - time()) / 60);
            $error = "Too many failed attempts. Account locked, try again in $mins minute(s).";
        } elseif ($u && password_verify($password, $u['password'])) {
            if ($u['status'] !== 'active') {
                $error = 'Your account is inactive. Please contact the institute office.';
            } else {
                // Success: reset counters and start a fresh session
                $pdo->prepare('UPDATE users SET failed_attempts = 0, locked_until = NULL WHERE id = ?')
                    ->execute([$u['id']]);
                session_regenerate_id(true);
                $_SESSION['user'] = [
                    'id'   => (int)$u['id'],
                    'name' => $u['name'],
                    'role' => $u['role'],
                ];
                flash('success', 'Welcome back, ' . $u['name'] . '!');
                redirect('dashboard.php');
            }
        } else {
            if ($u) {
                $attempts = $u['failed_attempts'] + 1;
                $locked   = null;
                if ($attempts >= MAX_LOGIN_ATTEMPTS) {
                    $locked   = date('Y-m-d H:i:s', time() + LOCKOUT_MINUTES * 60);
                    $attempts = 0;
                }
                $pdo->prepare('UPDATE users SET failed_attempts = ?, locked_until = ? WHERE id = ?')
                    ->execute([$attempts, $locked, $u['id']]);
            }
            // Same message whether the user exists or not
            $error = 'Invalid username/email or password.';
        }
    }
}

$page_title = 'Sign in';
require __DIR__ . '/includes/auth_header.php';
?>
<h2>Welcome back</h2>
<p class="auth-sub">Sign in to your <?= e(APP_NAME) ?> account to continue.</p>

<?php if ($error): ?>
    <div class="alert alert-danger" role="alert"><i class="bi bi-exclamation-circle"></i><div><?= e($error) ?></div></div>
<?php endif; ?>

<form method="post" class="needs-validation" novalidate>
    <?= csrf_field() ?>
    <div class="auth-field">
        <label class="form-label" for="login">Username or email</label>
        <div class="input-icon">
            <i class="bi bi-person"></i>
            <input type="text" id="login" name="login" class="form-control" value="<?= e($login) ?>"
                   placeholder="Enter your username or email" autocomplete="username" required autofocus>
        </div>
        <div class="invalid-feedback">Enter your username or email.</div>
    </div>
    <div class="auth-field">
        <div class="auth-label-row">
            <label class="form-label" for="password">Password</label>
            <a href="<?= url('forgot_password.php') ?>">Forgot password?</a>
        </div>
        <div class="input-icon">
            <i class="bi bi-lock"></i>
            <input type="password" id="password" name="password" class="form-control" placeholder="Enter your password"
                   autocomplete="current-password" required>
            <button type="button" class="input-action" data-toggle-password="password" aria-label="Show password"><i class="bi bi-eye"></i></button>
        </div>
    </div>
    <button class="btn btn-brand btn-lg mt-4">Sign in <i class="bi bi-arrow-right"></i></button>
</form>

<div class="auth-help">
    <i class="bi bi-info-circle"></i>
    <span>Students, parents and teachers sign in with the account given by the institute office.</span>
</div>
<?php require __DIR__ . '/includes/auth_footer.php'; ?>
