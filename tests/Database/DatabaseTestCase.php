<?php

namespace Tests\Database;

use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Base class for tests that use the database.
 *
 * Every test runs inside a transaction that is rolled back afterwards,
 * so anything a test inserts or changes disappears and the next test
 * sees the original demo data from database/edusphere.sql again.
 */
abstract class DatabaseTestCase extends TestCase
{
    protected PDO $db;

    protected function setUp(): void
    {
        global $pdo;
        $this->db = $pdo;
        $this->db->beginTransaction();
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        if ($this->db->inTransaction()) {
            $this->db->rollBack();
        }
        $_SESSION = [];
    }

    /** Pretend a user is logged in (same shape that login.php stores) */
    protected function loginAs(int $userId, string $role): void
    {
        $_SESSION['user'] = ['id' => $userId, 'name' => 'Test user', 'role' => $role];
    }
}
