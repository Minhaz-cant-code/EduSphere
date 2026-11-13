<?php
/**
 * Page header + application shell (sidebar, top bar).
 * Set $page_title before including this file.
 * Pages that are opened while logged out (e.g. reset password) use auth_header.php instead.
 */
$page_title = $page_title ?? APP_NAME;
$current    = $_SERVER['PHP_SELF'] ?? '';

// Navigation per role, grouped: group label => [ [label, path, icon], ... ]
$menus = [
    'admin' => [
        'Overview'   => [['Dashboard', 'dashboard.php', 'grid-1x2']],
        'People'     => [['Students', 'admin/students.php', 'people'],
                         ['Teachers', 'admin/teachers.php', 'person-badge'],
                         ['Parents', 'admin/parents.php', 'person-hearts']],
        'Academics'  => [['Batches', 'admin/batches.php', 'collection'],
                         ['Subjects', 'admin/subjects.php', 'book'],
                         ['Attendance', 'attendance/report.php', 'calendar2-check'],
                         ['Exams & Results', 'exams/index.php', 'journal-check'],
                         ['Assignments', 'assignments/index.php', 'file-earmark-text'],
                         ['At-Risk Students', 'admin/at_risk.php', 'exclamation-triangle']],
        'Finance'    => [['Fees & Payments', 'fees/index.php', 'wallet2'],
                         ['Reports', 'reports/index.php', 'bar-chart-line']],
        'Communicate' => [['Notice Board', 'notices/index.php', 'megaphone']],
    ],
    'teacher' => [
        'Overview'   => [['Dashboard', 'dashboard.php', 'grid-1x2']],
        'Classroom'  => [['Mark Attendance', 'attendance/mark.php', 'check2-square'],
                         ['Attendance Report', 'attendance/report.php', 'calendar2-check'],
                         ['Exams & Marks', 'exams/index.php', 'journal-check'],
                         ['Assignments', 'assignments/index.php', 'file-earmark-text'],
                         ['At-Risk Students', 'admin/at_risk.php', 'exclamation-triangle']],
        'Communicate' => [['Notice Board', 'notices/index.php', 'megaphone']],
    ],
    'student' => [
        'Overview'   => [['Dashboard', 'dashboard.php', 'grid-1x2']],
        'My Studies' => [['Attendance', 'attendance/my.php', 'calendar2-check'],
                         ['Results', 'exams/my.php', 'award'],
                         ['Assignments', 'assignments/index.php', 'file-earmark-text'],
                         ['Fees', 'fees/my.php', 'wallet2']],
        'Communicate' => [['Notice Board', 'notices/index.php', 'megaphone']],
    ],
    'parent' => [
        'Overview'   => [['Dashboard', 'dashboard.php', 'grid-1x2']],
        'My Child'   => [['Attendance', 'attendance/my.php', 'calendar2-check'],
                         ['Results', 'exams/my.php', 'award'],
                         ['Fees', 'fees/my.php', 'wallet2']],
        'Communicate' => [['Notice Board', 'notices/index.php', 'megaphone']],
    ],
];
$menu = $menus[role()] ?? [];

// Which item is active? Exact page first, then same folder.
$active_path = null;
$active_group = '';
foreach ($menu as $group => $items) {
    foreach ($items as [$label, $path]) {
        if (str_ends_with($current, '/' . $path)) { $active_path = $path; $active_group = $group; }
    }
}
if (!$active_path) {
    foreach ($menu as $group => $items) {
        foreach ($items as [$label, $path]) {
            $folder = dirname($path);
            if ($folder !== '.' && str_contains($current, '/' . $folder . '/')) { $active_path = $path; $active_group = $group; break 2; }
        }
    }
}
$me = user();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($page_title) ?> · <?= e(APP_NAME) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap">
    <!-- Only the ৳ sign, which Inter does not include -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Noto+Sans+Bengali:wght@500;600;700&text=%E0%A7%B3&display=swap">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= asset('assets/css/style.css') ?>">
    <meta name="csrf-token" content="<?= csrf_token() ?>">
</head>
<body>
<script>try { if (localStorage.getItem('es-sidebar-collapsed') === '1') document.body.classList.add('sidebar-collapsed'); } catch (e) {}</script>
<?php show_flash(); ?>
<?php if (is_logged_in()): ?>
<div class="es-app">
    <!-- Sidebar: fixed column on desktop, slide-in drawer on mobile -->
    <aside class="es-sidebar offcanvas-lg offcanvas-start no-print" tabindex="-1" id="sidebar" aria-label="Main navigation">
        <div class="es-brand">
            <a class="d-flex align-items-center gap-2 text-reset text-decoration-none min-w-0" href="<?= url('dashboard.php') ?>">
                <span class="es-brand-mark"><i class="bi bi-mortarboard-fill"></i></span>
                <span class="es-brand-text">
                    <span class="es-brand-name d-block"><?= e(APP_NAME) ?></span>
                    <span class="es-brand-sub"><?= e(INSTITUTE_NAME) ?></span>
                </span>
            </a>
            <button type="button" class="btn-close ms-auto d-lg-none" data-bs-dismiss="offcanvas" data-bs-target="#sidebar" aria-label="Close menu"></button>
        </div>
        <div class="offcanvas-body">
            <nav class="es-nav">
                <?php foreach ($menu as $group => $items): ?>
                    <div class="es-nav-group">
                        <div class="es-nav-label"><?= e($group) ?></div>
                        <?php foreach ($items as [$label, $path, $icon]): $is = $path === $active_path; ?>
                            <a href="<?= url($path) ?>" class="<?= $is ? 'active' : '' ?>" <?= $is ? 'aria-current="page"' : '' ?>
                               title="<?= e($label) ?>">
                                <i class="bi bi-<?= $icon ?>"></i><span><?= e($label) ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            </nav>
            <div class="es-sidebar-foot dropup">
                <button class="es-user" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <?= avatar($me['name']) ?>
                    <span class="es-user-meta">
                        <span class="es-user-name d-block"><?= e($me['name']) ?></span>
                        <span class="es-user-role"><?= e(role_label(role())) ?></span>
                    </span>
                    <i class="bi bi-chevron-expand text-muted"></i>
                </button>
                <ul class="dropdown-menu">
                    <li><h6 class="dropdown-header">Signed in as <?= e(role_label(role())) ?></h6></li>
                    <li><a class="dropdown-item" href="<?= url('change_password.php') ?>"><i class="bi bi-key"></i> Change password</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" href="<?= url('logout.php') ?>"><i class="bi bi-box-arrow-right"></i> Log out</a></li>
                </ul>
            </div>
        </div>
    </aside>

    <div class="es-main">
        <header class="es-topbar no-print">
            <button class="es-icon-btn d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebar" aria-label="Open menu">
                <i class="bi bi-list"></i>
            </button>
            <button class="es-icon-btn d-none d-lg-inline-flex" type="button" data-sidebar-toggle aria-label="Collapse sidebar" title="Collapse sidebar">
                <i class="bi bi-layout-sidebar"></i>
            </button>
            <div class="es-topbar-title">
                <?php if ($active_group && $active_group !== 'Overview'): ?>
                    <span class="es-crumb d-none d-sm-inline"><?= e($active_group) ?><i class="bi bi-chevron-right"></i></span>
                <?php endif; ?>
                <?= e($page_title) ?>
            </div>
            <div class="ms-auto d-flex align-items-center gap-2">
                <span class="es-topbar-date d-none d-md-inline"><?= date('l, j F Y') ?></span>
                <span class="divider-v d-none d-md-block mx-1"></span>
                <a class="es-icon-btn" href="<?= url('notices/index.php') ?>" title="Notice board" aria-label="Notice board">
                    <i class="bi bi-bell"></i>
                </a>
                <div class="dropdown">
                    <button class="es-icon-btn p-0" type="button" data-bs-toggle="dropdown" aria-label="Account menu" aria-expanded="false">
                        <?= avatar($me['name'], null, 'sm') ?>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li class="px-3 py-2">
                            <div class="fw-semibold text-dark"><?= e($me['name']) ?></div>
                            <div class="small text-muted"><?= e(role_label(role())) ?></div>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="<?= url('change_password.php') ?>"><i class="bi bi-key"></i> Change password</a></li>
                        <li><a class="dropdown-item text-danger" href="<?= url('logout.php') ?>"><i class="bi bi-box-arrow-right"></i> Log out</a></li>
                    </ul>
                </div>
            </div>
        </header>
        <main class="es-content" id="main">
<?php else: ?>
<main class="container py-5" id="main">
<?php endif; ?>
