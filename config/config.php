<?php
/**
 * EduSphere - main configuration
 * Change these values to match your XAMPP / hosting setup.
 */

// ---- Database ----
define('DB_HOST', 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'edusphere');   // PHPUnit sets DB_NAME=edusphere_test (see phpunit.xml)
define('DB_USER', 'root');
define('DB_PASS', '');          // XAMPP default root password is empty

// ---- Application ----
define('APP_NAME', 'EduSphere');
define('INSTITUTE_NAME', 'EduSphere');
define('INSTITUTE_ADDRESS', 'Dhaka');

// URL path of the project folder inside htdocs (no trailing slash).
// If you put the project at htdocs/edusphere, keep '/edusphere'.
define('BASE_URL', '/edusphere');

// Absolute folder of the project on disk
define('BASE_PATH', dirname(__DIR__));
define('UPLOAD_PATH', BASE_PATH . '/uploads');

// Security settings
define('MAX_LOGIN_ATTEMPTS', 5);     // FR-AUTH-5
define('LOCKOUT_MINUTES', 15);
define('MAX_UPLOAD_MB', 5);

// Version 1 has no email service, so the password-reset link is shown
// on screen. Set to false once email notifications are added (Phase 2).
define('SHOW_RESET_LINK', true);

date_default_timezone_set('Asia/Dhaka');
