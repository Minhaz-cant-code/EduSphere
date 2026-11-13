<?php
/**
 * Role-based dashboard (FR-DSH-1..3)
 * Each role gets its own dashboard file from includes/dashboards/.
 */
require_once __DIR__ . '/includes/functions.php';
require_login();

define('IN_APP', true);
$page_title = 'Dashboard';
require __DIR__ . '/includes/header.php';

require __DIR__ . '/includes/dashboards/' . role() . '.php';

require __DIR__ . '/includes/footer.php';
