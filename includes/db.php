<?php
/**
 * Database connection (PDO) - shared by every page.
 */
require_once __DIR__ . '/../config/config.php';

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    
    error_log($e->getMessage());
    die('<h3 style="font-family:sans-serif;color:#b00">Could not connect to the database. '
        . 'Please check config/config.php and make sure MySQL is running.</h3>');
}
