<?php
/**
 * Layout for logged-out pages (login, forgot / reset password).
 * Left: brand panel (hidden on small screens). Right: the form.
 * Set $page_title before including; close with auth_footer.php.
 */
$page_title = $page_title ?? 'Sign in';
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
</head>
<body>
<?php show_flash(); ?>
<div class="auth">
    <aside class="auth-aside">
        <div class="auth-brand">
            <span class="es-brand-mark"><i class="bi bi-mortarboard-fill"></i></span>
            <?= e(APP_NAME) ?>
        </div>

        <div class="auth-pitch">
            
            <h1>Run your whole institute from one place.</h1>
            <p class="lead-text">Attendance, fees, exams and notices for admins, teachers, students and parents, all in one system built for coaching centers.</p>
            <ul class="auth-features">
                <li><i class="bi bi-calendar2-check"></i>
                    <div><strong>Attendance in a few taps</strong><span>Mark a whole batch, and percentages update on their own.</span></div></li>
                <li><i class="bi bi-receipt"></i>
                    <div><strong>Fees, dues and receipts</strong><span>Record payments and print a receipt the same minute.</span></div></li>
                <li><i class="bi bi-award"></i>
                    <div><strong>Results with automatic GPA</strong><span>Enter marks once; grades reach students and parents.</span></div></li>
            </ul>
        </div>

        <div class="auth-foot">&copy; <?= date('Y') ?> <?= e(INSTITUTE_NAME) ?> · Powered by <?= e(APP_NAME) ?></div>
    </aside>

    <main class="auth-main">
        <div class="auth-main-top">
            <div class="auth-mobile-brand">
                <span class="es-brand-mark"><i class="bi bi-mortarboard-fill"></i></span>
                <?= e(APP_NAME) ?>
            </div>
        </div>
        <div class="auth-form-wrap">
