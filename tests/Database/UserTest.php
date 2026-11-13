<?php

namespace Tests\Database;

use PHPUnit\Framework\Attributes\DataProvider;

/** Accounts: create_user(), user_exists() and the demo logins (FR-AUTH-1) */
class UserTest extends DatabaseTestCase
{
    public function testCreateUserStoresAHashedPassword(): void
    {
        $id = create_user('Test Student', 'teststudent', 'test@edusphere.test', 'secret123', 'student');

        $row = $this->db->query("SELECT * FROM users WHERE id = $id")->fetch();

        $this->assertGreaterThan(0, $id);
        $this->assertSame('teststudent', $row['username']);
        $this->assertSame('student', $row['role']);
        $this->assertSame('active', $row['status']);
        $this->assertNotSame('secret123', $row['password']);          // never stored as plain text
        $this->assertTrue(password_verify('secret123', $row['password']));
    }

    public function testUsernameMustBeUnique(): void
    {
        $this->expectException(\PDOException::class);
        create_user('Another Admin', 'admin', 'other@edusphere.test', 'x', 'admin');
    }

    public function testUserExistsFindsTakenUsername(): void
    {
        $this->assertTrue(user_exists('admin', 'free@edusphere.test'));
    }

    public function testUserExistsFindsTakenEmail(): void
    {
        $this->assertTrue(user_exists('freename', 'admin@edusphere.test'));
    }

    public function testUserExistsIgnoresTheUserBeingEdited(): void
    {
        // When the admin edits user 1, its own username/email are not a "duplicate"
        $this->assertFalse(user_exists('admin', 'admin@edusphere.test', 1));
    }

    public function testUserExistsIsFalseForNewDetails(): void
    {
        $this->assertFalse(user_exists('brandnew', 'brandnew@edusphere.test'));
    }

    public static function demoLogins(): array
    {
        return [
            'admin'   => ['admin', 'admin123'],
            'teacher' => ['teacher1', 'teacher123'],
            'student' => ['student1', 'student123'],
            'parent'  => ['parent1', 'parent123'],
        ];
    }

    /** The passwords in README.md must really match the hashes in edusphere.sql */
    #[DataProvider('demoLogins')]
    public function testDemoLoginPasswordsWork(string $username, string $password): void
    {
        $st = $this->db->prepare('SELECT password FROM users WHERE username = ?');
        $st->execute([$username]);

        $this->assertTrue(password_verify($password, $st->fetchColumn()));
    }

    public function testLoginQueryFindsUserByEmailToo(): void
    {
        // Same query login.php uses: username OR email
        $st = $this->db->prepare('SELECT username FROM users WHERE username = ? OR email = ? LIMIT 1');
        $st->execute(['tanvir@edusphere.test', 'tanvir@edusphere.test']);

        $this->assertSame('student1', $st->fetchColumn());
    }
}
