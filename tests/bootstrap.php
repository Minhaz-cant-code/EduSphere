<?php
/**
 * PHPUnit bootstrap - runs ONCE before any test.
 *
 * 1. Loads Composer's autoloader (PHPUnit + our Tests\ classes).
 * 2. Re-creates a fresh "edusphere_test" database from database/edusphere.sql,
 *    so every test run starts with the same demo data.
 * 3. Loads includes/functions.php (the code we are testing).
 */

// PHPUnit loads this file from inside a method, so make $pdo (created in db.php) a real global
// - the functions in functions.php use "global $pdo".
global $pdo;

// With Composer (vendor\) use its autoloader; with phpunit.phar load our base class directly
if (is_file(__DIR__ . '/../vendor/autoload.php')) {
    require __DIR__ . '/../vendor/autoload.php';
} else {
    require_once __DIR__ . '/Database/DatabaseTestCase.php';
}
require_once __DIR__ . '/../config/config.php';

// Safety check: never drop the real database
if (!str_ends_with(DB_NAME, '_test')) {
    fwrite(STDERR, "Refusing to run: DB_NAME is '" . DB_NAME . "'. Run tests with phpunit.xml (it sets DB_NAME=edusphere_test).\n");
    exit(1);
}

try {
    $server = new PDO('mysql:host=' . DB_HOST . ';charset=utf8mb4', DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
} catch (PDOException $e) {
    fwrite(STDERR, "\nCould not connect to MySQL. Start MySQL in the XAMPP Control Panel and try again.\n"
        . $e->getMessage() . "\n");
    exit(1);
}

// Build the test database from the same schema + demo data the app uses
$sql = file_get_contents(__DIR__ . '/../database/edusphere.sql');
$sql = preg_replace('/^\s*--.*$/m', '', $sql);                                  // drop comment lines
$sql = preg_replace('/^\s*(DROP DATABASE|CREATE DATABASE|USE)\b[^;]*;/mi', '', $sql); // don't touch "edusphere"

$server->exec('DROP DATABASE IF EXISTS `' . DB_NAME . '`');
$server->exec('CREATE DATABASE `' . DB_NAME . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$server->exec('USE `' . DB_NAME . '`');

foreach (preg_split('/;\s*$/m', $sql) as $statement) {
    if (trim($statement) !== '') {
        $server->exec($statement);
    }
}
$server = null;

// Now load the application code. db.php connects to edusphere_test because of DB_NAME.
require_once __DIR__ . '/../includes/functions.php';
