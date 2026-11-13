<?php
/**
 * Common helper functions + session/role checks.
 * Every page starts with:  require_once '../includes/functions.php';
 */
require_once __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
    session_start();
}

/* ------------------------------------------------------------------
 *  Output & navigation helpers
 * ------------------------------------------------------------------ */

/** Escape output to prevent XSS (NFR-2) */
function e($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function url(string $path = ''): string
{
    return BASE_URL . '/' . ltrim($path, '/');
}

/** URL of a CSS/JS file with its last-modified time, so browsers always load the newest version */
function asset(string $path): string
{
    $file = BASE_PATH . '/' . ltrim($path, '/');
    return url($path) . (is_file($file) ? '?v=' . filemtime($file) : '');
}

function redirect(string $path): void
{
    header('Location: ' . url($path));
    exit;
}

/** One-time messages shown after a redirect */
function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'msg' => $message];
}

/**
 * Print one-time messages as toasts (top-right).
 * Success/info toasts close themselves; errors stay until dismissed (see app.js).
 */
function show_flash(): void
{
    if (empty($_SESSION['flash'])) return;
    $icons = ['success' => 'check-circle-fill', 'danger' => 'exclamation-octagon-fill',
              'warning' => 'exclamation-triangle-fill', 'info' => 'info-circle-fill'];
    echo '<div class="toast-stack" aria-live="polite">';
    foreach ($_SESSION['flash'] as $f) {
        $type = isset($icons[$f['type']]) ? $f['type'] : 'info';
        echo '<div class="es-toast is-' . $type . '" role="' . ($type === 'danger' ? 'alert' : 'status') . '" data-autohide="'
            . (in_array($type, ['success', 'info'], true) ? '1' : '0') . '">'
            . '<i class="bi bi-' . $icons[$type] . '"></i>'
            . '<div class="es-toast-msg">' . e($f['msg']) . '</div>'
            . '<button type="button" class="btn-close" aria-label="Close"></button></div>';
    }
    echo '</div>';
    unset($_SESSION['flash']);
}

/* ------------------------------------------------------------------
 *  UI building blocks (keep markup consistent across pages)
 * ------------------------------------------------------------------ */

/**
 * Page header: title, optional subtitle and right-aligned actions (raw HTML).
 * $back = [label, path] shows a small "back" link above the title.
 */
function page_head(string $title, string $sub = '', string $actions = '', ?array $back = null): void
{
    if ($back) {
        echo '<a class="page-back no-print" href="' . url($back[1]) . '"><i class="bi bi-arrow-left"></i> ' . e($back[0]) . '</a>';
    }
    echo '<div class="page-head"><div><h1>' . e($title) . '</h1>';
    if ($sub !== '') echo '<p class="page-sub">' . $sub . '</p>';
    echo '</div>';
    if ($actions !== '') echo '<div class="page-actions no-print">' . $actions . '</div>';
    echo '</div>';
}

/** Friendly empty state block (returns HTML) */
function empty_state(string $icon, string $title, string $text = '', string $action = '', bool $compact = false): string
{
    return '<div class="empty-state' . ($compact ? ' compact' : '') . '">'
        . '<div class="empty-state-icon"><i class="bi bi-' . e($icon) . '"></i></div>'
        . '<div class="empty-state-title">' . e($title) . '</div>'
        . ($text !== '' ? '<div class="empty-state-text">' . e($text) . '</div>' : '')
        . $action . '</div>';
}

/** Empty state inside a table body */
function empty_row(int $cols, string $icon, string $title, string $text = '', string $action = ''): string
{
    return '<tr><td colspan="' . $cols . '" class="empty-cell">' . empty_state($icon, $title, $text, $action, true) . '</td></tr>';
}

/** Initials from a name, e.g. "Tanvir Ahmed" -> "TA" */
function initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $out = '';
    foreach (array_slice($parts, 0, 2) as $p) {
        $out .= mb_strtoupper(mb_substr($p, 0, 1));
    }
    return $out ?: '?';
}

/** Avatar: photo if available, otherwise coloured initials */
function avatar(string $name, ?string $photo = null, string $size = ''): string
{
    $cls = 'avatar' . ($size ? ' avatar-' . $size : '');
    if ($photo) {
        return '<img src="' . url('uploads/' . $photo) . '" class="' . $cls . '" alt="">';
    }
    $tone = (abs(crc32($name)) % 5) + 1;
    return '<span class="' . $cls . ' tone-' . $tone . '" aria-hidden="true">' . e(initials($name)) . '</span>';
}

/** Validation error summary shown at the top of a form */
function form_errors(array $errors): void
{
    if (!$errors) return;
    echo '<div class="alert alert-danger" role="alert"><i class="bi bi-exclamation-circle"></i><div>'
        . '<strong>Please fix the following:</strong><ul class="mb-0 mt-1">';
    foreach (array_unique($errors) as $er) echo '<li>' . e($er) . '</li>';
    echo '</ul></div></div>';
}

/** Time-of-day greeting */
function greeting(): string
{
    $h = (int)date('G');
    if ($h < 12) return 'Good morning';
    if ($h < 17) return 'Good afternoon';
    return 'Good evening';
}

/** Short role label for the UI */
function role_label(string $role): string
{
    return ['admin' => 'Administrator', 'teacher' => 'Teacher', 'student' => 'Student', 'parent' => 'Parent'][$role] ?? ucfirst($role);
}

function money($amount): string
{
    return '৳' . "\u{00A0}" . number_format((float)$amount, 2);   // non-breaking space keeps ৳ with the number
}

/** Money for large KPI numbers: small ৳ unit, no decimals when the amount is whole */
function money_kpi($amount): string
{
    $a = (float)$amount;
    $num = number_format($a, fmod($a, 1.0) == 0.0 ? 0 : 2);
    return '<span class="unit">৳</span>' . $num;
}

function fmt_date($date): string
{
    return $date ? date('d M Y', strtotime($date)) : '-';
}

function today(): string
{
    return date('Y-m-d');
}

function now(): string
{
    return date('Y-m-d H:i:s');
}

/** Read a trimmed POST value */
function post(string $key, $default = '')
{
    return isset($_POST[$key]) ? trim((string)$_POST[$key]) : $default;
}

/** Read an integer GET value */
function get_int(string $key, int $default = 0): int
{
    return isset($_GET[$key]) ? (int)$_GET[$key] : $default;
}

/* ------------------------------------------------------------------
 *  CSRF protection for every form
 * ------------------------------------------------------------------ */

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . csrf_token() . '">';
}

function verify_csrf(): void
{
    $sent = $_POST['csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!hash_equals(csrf_token(), $sent)) {
        http_response_code(400);
        die('Invalid form token. Please go back, refresh the page and try again.');
    }
}

/* ------------------------------------------------------------------
 *  Authentication & Role-Based Access Control (FR-AUTH-4)
 * ------------------------------------------------------------------ */

function is_logged_in(): bool
{
    return !empty($_SESSION['user']);
}

function user(): array
{
    return $_SESSION['user'] ?? [];
}

function user_id(): int
{
    return (int)($_SESSION['user']['id'] ?? 0);
}

function role(): string
{
    return $_SESSION['user']['role'] ?? '';
}

function require_login(): void
{
    if (!is_logged_in()) {
        flash('warning', 'Please log in first.');
        redirect('login.php');
    }
}

/** Allow only the listed roles; checked on the server for every page */
function require_role(string ...$roles): void
{
    require_login();
    if (!in_array(role(), $roles, true)) {
        http_response_code(403);
        $page_title = 'Access denied';
        require __DIR__ . '/header.php';
        echo '<div class="card">' . empty_state('shield-lock', 'You don\'t have access to this page',
            'This page is not available for your role. If you think this is a mistake, contact the institute office.',
            '<a class="btn btn-brand" href="' . url('dashboard.php') . '">Back to dashboard</a>') . '</div>';
        require __DIR__ . '/footer.php';
        exit;
    }
}

/* ------------------------------------------------------------------
 *  Role-specific lookups
 * ------------------------------------------------------------------ */

/** teachers.id of the logged-in teacher */
function my_teacher_id(): int
{
    global $pdo;
    static $id = null;
    if ($id === null) {
        $st = $pdo->prepare('SELECT id FROM teachers WHERE user_id = ?');
        $st->execute([user_id()]);
        $id = (int)$st->fetchColumn();
    }
    return $id;
}

/**
 * students.id that the logged-in user may view:
 * a student sees himself, a parent sees the linked child.
 */
function my_student_id(): int
{
    global $pdo;
    static $id = null;
    if ($id === null) {
        if (role() === 'student') {
            $st = $pdo->prepare('SELECT id FROM students WHERE user_id = ?');
        } elseif (role() === 'parent') {
            $st = $pdo->prepare('SELECT student_id FROM parents WHERE user_id = ?');
        } else {
            return $id = 0;
        }
        $st->execute([user_id()]);
        $id = (int)$st->fetchColumn();
    }
    return $id;
}

/** Subjects (with batch name) the logged-in teacher teaches */
function teacher_subjects(): array
{
    global $pdo;
    $st = $pdo->prepare(
        'SELECT s.id, s.name, s.batch_id, b.name AS batch_name
           FROM teacher_subjects ts
           JOIN subjects s ON s.id = ts.subject_id
           JOIN batches  b ON b.id = s.batch_id
          WHERE ts.teacher_id = ?
          ORDER BY b.name, s.name'
    );
    $st->execute([my_teacher_id()]);
    return $st->fetchAll();
}

/** Subjects a teacher/admin may manage (admin = all subjects) */
function manageable_subjects(): array
{
    global $pdo;
    if (role() === 'admin') {
        return $pdo->query(
            'SELECT s.id, s.name, s.batch_id, b.name AS batch_name
               FROM subjects s JOIN batches b ON b.id = s.batch_id
              ORDER BY b.name, s.name'
        )->fetchAll();
    }
    return teacher_subjects();
}

/** Batches the current admin/teacher may work with */
function manageable_batches(): array
{
    global $pdo;
    if (role() === 'admin') {
        return $pdo->query('SELECT id, name FROM batches ORDER BY name')->fetchAll();
    }
    $st = $pdo->prepare(
        'SELECT DISTINCT b.id, b.name
           FROM teacher_subjects ts
           JOIN subjects s ON s.id = ts.subject_id
           JOIN batches  b ON b.id = s.batch_id
          WHERE ts.teacher_id = ?
          ORDER BY b.name'
    );
    $st->execute([my_teacher_id()]);
    return $st->fetchAll();
}

function can_manage_batch(int $batch_id): bool
{
    foreach (manageable_batches() as $b) {
        if ((int)$b['id'] === $batch_id) return true;
    }
    return false;
}

function can_manage_subject(int $subject_id): bool
{
    foreach (manageable_subjects() as $s) {
        if ((int)$s['id'] === $subject_id) return true;
    }
    return false;
}

/* ------------------------------------------------------------------
 *  Business rules
 * ------------------------------------------------------------------ */

/**
 * Grade & GPA from percentage (Bangladesh 5.00 scale) - FR-EXM-3
 */
function calc_grade(float $marks, float $total): array
{
    $p = $total > 0 ? ($marks / $total) * 100 : 0;
    if ($p >= 80) return ['A+', 5.00];
    if ($p >= 70) return ['A', 4.00];
    if ($p >= 60) return ['A-', 3.50];
    if ($p >= 50) return ['B', 3.00];
    if ($p >= 40) return ['C', 2.00];
    if ($p >= 33) return ['D', 1.00];
    return ['F', 0.00];
}

/** Attendance summary of one student: counts + percentage (FR-ATT-2) */
function attendance_summary(int $student_id, ?string $from = null, ?string $to = null): array
{
    global $pdo;
    $sql = "SELECT
                SUM(status = 'Present') AS present,
                SUM(status = 'Absent')  AS absent,
                SUM(status = 'Late')    AS late,
                COUNT(*)                AS total
            FROM attendance WHERE student_id = ?";
    $params = [$student_id];
    if ($from && $to) {
        $sql .= ' AND att_date BETWEEN ? AND ?';
        $params[] = $from;
        $params[] = $to;
    }
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $r = $st->fetch();
    $total = (int)$r['total'];
    // Late counts as attended
    $attended = (int)$r['present'] + (int)$r['late'];
    return [
        'present' => (int)$r['present'],
        'absent'  => (int)$r['absent'],
        'late'    => (int)$r['late'],
        'total'   => $total,
        'percent' => $total ? round($attended / $total * 100, 1) : 0,
    ];
}

/** Total fee, paid and due for one student (FR-FEE-2) */
function fee_summary(int $student_id): array
{
    global $pdo;
    $st = $pdo->prepare(
        'SELECT COALESCE(SUM(amount),0) FROM fees WHERE student_id = ?'
    );
    $st->execute([$student_id]);
    $total = (float)$st->fetchColumn();

    $st = $pdo->prepare(
        'SELECT COALESCE(SUM(p.amount_paid),0)
           FROM payments p JOIN fees f ON f.id = p.fee_id
          WHERE f.student_id = ?'
    );
    $st->execute([$student_id]);
    $paid = (float)$st->fetchColumn();

    return ['total' => $total, 'paid' => $paid, 'due' => $total - $paid];
}

/** Fees of a student with paid & due columns */
function student_fees(int $student_id): array
{
    global $pdo;
    $st = $pdo->prepare(
        'SELECT f.*, COALESCE(SUM(p.amount_paid),0) AS paid
           FROM fees f LEFT JOIN payments p ON p.fee_id = f.id
          WHERE f.student_id = ?
          GROUP BY f.id
          ORDER BY f.due_date DESC, f.id DESC'
    );
    $st->execute([$student_id]);
    $rows = $st->fetchAll();
    foreach ($rows as &$r) {
        $r['due'] = $r['amount'] - $r['paid'];
    }
    return $rows;
}

/** Badge colour for a status word */
function status_badge(string $status): string
{
    $map = [
        'Present' => 'success', 'Absent' => 'danger', 'Late' => 'warning',
        'Paid' => 'success', 'Partial' => 'warning', 'Unpaid' => 'danger',
        'active' => 'success', 'inactive' => 'secondary',
    ];
    $c = $map[$status] ?? 'secondary';
    return '<span class="badge dot text-bg-' . $c . '">' . e(ucfirst($status)) . '</span>';
}

function fee_status(float $amount, float $paid): string
{
    if ($paid <= 0) return 'Unpaid';
    if ($paid < $amount) return 'Partial';
    return 'Paid';
}

/**
 * Save an uploaded file safely.
 * Returns the stored relative path (e.g. "assignments/abc.pdf") or null.
 * Throws RuntimeException with a user-friendly message on invalid files.
 */
function save_upload(string $field, string $folder, array $allowed_ext): ?string
{
    if (empty($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    $f = $_FILES[$field];
    if ($f['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('File upload failed. Please try again.');
    }
    if ($f['size'] > MAX_UPLOAD_MB * 1024 * 1024) {
        throw new RuntimeException('File is too large. Maximum size is ' . MAX_UPLOAD_MB . ' MB.');
    }
    $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed_ext, true)) {
        throw new RuntimeException('Only these file types are allowed: ' . implode(', ', $allowed_ext));
    }
    $name = date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
    $dir = UPLOAD_PATH . '/' . $folder;
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    if (!move_uploaded_file($f['tmp_name'], $dir . '/' . $name)) {
        throw new RuntimeException('Could not save the uploaded file.');
    }
    return $folder . '/' . $name;
}

function delete_upload(?string $path): void
{
    if ($path && is_file(UPLOAD_PATH . '/' . $path)) {
        unlink(UPLOAD_PATH . '/' . $path);
    }
}

/** Create a login account; returns new users.id */
function create_user(string $name, string $username, string $email, string $password, string $role): int
{
    global $pdo;
    $st = $pdo->prepare(
        'INSERT INTO users (name, username, email, password, role) VALUES (?,?,?,?,?)'
    );
    $st->execute([$name, $username, $email, password_hash($password, PASSWORD_BCRYPT), $role]);
    return (int)$pdo->lastInsertId();
}

/** Check that a username/email is not used by another user */
function user_exists(string $username, string $email, int $except_id = 0): bool
{
    global $pdo;
    $st = $pdo->prepare('SELECT COUNT(*) FROM users WHERE (username = ? OR email = ?) AND id <> ?');
    $st->execute([$username, $email, $except_id]);
    return $st->fetchColumn() > 0;
}

/** Active notices visible to the current role */
function visible_notices(int $limit = 0): array
{
    global $pdo;
    $sql = "SELECT n.*, u.name AS author, u.role AS author_role
              FROM notices n LEFT JOIN users u ON u.id = n.posted_by
             WHERE (n.expires_on IS NULL OR n.expires_on >= ?)";
    $params = [today()];
    if (role() !== 'admin') {
        $sql .= " AND (n.target_role = 'all' OR n.target_role = ?)";
        $params[] = role();
    }
    $sql .= ' ORDER BY n.created_at DESC';
    if ($limit > 0) $sql .= ' LIMIT ' . (int)$limit;
    $st = $pdo->prepare($sql);
    $st->execute($params);
    return $st->fetchAll();
}

/* ------------------------------------------------------------------
 *  SMART FEATURE: At-Risk Student Early Warning (SRS 3.13)
 *  Gives a student a risk score from 0 to 100 using simple rules:
 *    Attendance (max 40) + Marks (max 40) + Fees (max 20)
 * ------------------------------------------------------------------ */

function risk_score(int $student_id): array
{
    global $pdo;
    $score   = 0;
    $reasons = [];

    // ---------- 1. Attendance in the last 30 days ----------
    $st = $pdo->prepare('SELECT status FROM attendance WHERE student_id = ? AND att_date >= ?');
    $st->execute([$student_id, date('Y-m-d', strtotime('-30 days'))]);
    $statuses = $st->fetchAll(PDO::FETCH_COLUMN);
    $classes  = count($statuses);

    if ($classes > 0) {
        $attended = count(array_keys($statuses, 'Present')) + count(array_keys($statuses, 'Late'));
        $percent  = round($attended / $classes * 100);

        if ($percent < 60)      { $score += 30; }
        elseif ($percent < 75)  { $score += 20; }
        elseif ($percent < 85)  { $score += 10; }
        if ($percent < 85) {
            $reasons[] = "Attendance $percent% in the last 30 days";
        }
    }

    // 3 absences in a row (the 3 most recent classes)
    $st = $pdo->prepare('SELECT status FROM attendance WHERE student_id = ? ORDER BY att_date DESC LIMIT 3');
    $st->execute([$student_id]);
    $last3 = $st->fetchAll(PDO::FETCH_COLUMN);
    if (count($last3) === 3 && count(array_keys($last3, 'Absent')) === 3) {
        $score += 10;
        $reasons[] = '3 absences in a row';
    }

    // ---------- 2. Marks in published results ----------
    $st = $pdo->prepare(
        'SELECT r.marks, e.total_marks
           FROM results r JOIN exams e ON e.id = r.exam_id
          WHERE r.student_id = ? AND e.is_published = 1
          ORDER BY e.exam_date, e.id'
    );
    $st->execute([$student_id]);
    $percents = [];
    foreach ($st->fetchAll() as $r) {
        $percents[] = $r['marks'] / $r['total_marks'] * 100;
    }

    if ($percents) {
        $average = round(array_sum($percents) / count($percents));

        if ($average < 40)      { $score += 30; }
        elseif ($average < 50)  { $score += 20; }
        elseif ($average < 60)  { $score += 10; }
        if ($average < 60) {
            $reasons[] = "Average marks $average%";
        }

        // Falling trend: latest result is 15+ points below the two before it
        $n = count($percents);
        if ($n >= 3) {
            $latest   = $percents[$n - 1];
            $previous = ($percents[$n - 2] + $percents[$n - 3]) / 2;
            if ($previous - $latest >= 15) {
                $score += 10;
                $reasons[] = 'Marks dropped from ' . round($previous) . '% to ' . round($latest) . '%';
            }
        }
    }

    // ---------- 3. Overdue fees ----------
    $most_days = 0;
    foreach (student_fees($student_id) as $f) {
        if ($f['due'] > 0 && $f['due_date'] && $f['due_date'] < today()) {
            $days = (int)((strtotime(today()) - strtotime($f['due_date'])) / 86400);
            $most_days = max($most_days, $days);
        }
    }
    if ($most_days > 30) {
        $score += 20;
        $reasons[] = "Fee overdue by $most_days days";
    } elseif ($most_days > 0) {
        $score += 10;
        $reasons[] = "Fee overdue by $most_days days";
    }

    // ---------- Final level ----------
    $score = min(100, $score);
    if ($score >= 60)     { $level = 'High'; }
    elseif ($score >= 30) { $level = 'Medium'; }
    else                  { $level = 'Low'; }

    // Not enough data to judge (fewer than 5 classes and no results)
    $enough = $classes >= 5 || count($percents) > 0;

    return ['score' => $score, 'level' => $level, 'reasons' => $reasons, 'enough' => $enough];
}

/**
 * All active students with their risk, highest score first.
 * $batch_ids = only these batches (for teachers); null = every batch (admin).
 */
function risk_list(?array $batch_ids = null): array
{
    global $pdo;
    $sql = "SELECT s.id, s.roll_no, s.photo, s.batch_id, u.name, b.name AS batch
              FROM students s
              JOIN users u ON u.id = s.user_id
              LEFT JOIN batches b ON b.id = s.batch_id
             WHERE u.status = 'active'";
    if ($batch_ids !== null) {
        if (!$batch_ids) return [];
        $sql .= ' AND s.batch_id IN (' . implode(',', array_map('intval', $batch_ids)) . ')';
    }
    $list = [];
    foreach ($pdo->query($sql)->fetchAll() as $s) {
        $s['risk'] = risk_score((int)$s['id']);
        $list[] = $s;
    }
    usort($list, fn($a, $b) => $b['risk']['score'] <=> $a['risk']['score']);
    return $list;
}

/** Coloured badge for a risk level */
function risk_badge(string $level): string
{
    $colors = ['High' => 'danger', 'Medium' => 'warning', 'Low' => 'success'];
    return '<span class="badge dot text-bg-' . ($colors[$level] ?? 'secondary') . '">' . e($level) . ' risk</span>';
}
