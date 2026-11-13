<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/** Login session helpers, flash messages and CSRF tokens */
class SessionAndCsrfTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
        $_POST = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        $_POST = [];
    }

    /* ---------- session helpers ---------- */

    public function testNobodyIsLoggedInByDefault(): void
    {
        $this->assertFalse(is_logged_in());
        $this->assertSame(0, user_id());
        $this->assertSame('', role());
        $this->assertSame([], user());
    }

    public function testLoggedInUserIsReadFromSession(): void
    {
        $_SESSION['user'] = ['id' => 4, 'name' => 'Tanvir Ahmed', 'role' => 'student'];

        $this->assertTrue(is_logged_in());
        $this->assertSame(4, user_id());
        $this->assertSame('student', role());
        $this->assertSame('Tanvir Ahmed', user()['name']);
    }

    /* ---------- flash messages ---------- */

    public function testFlashMessageIsShownOnceThenRemoved(): void
    {
        flash('success', 'Saved <b>OK</b>');

        ob_start();
        show_flash();
        $html = ob_get_clean();

        $this->assertStringContainsString('is-success', $html);
        $this->assertStringContainsString('Saved &lt;b&gt;OK&lt;/b&gt;', $html);   // escaped
        $this->assertArrayNotHasKey('flash', $_SESSION);                         // cleared after showing
    }

    /* ---------- CSRF ---------- */

    public function testCsrfTokenIs64HexCharacters(): void
    {
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', csrf_token());
    }

    public function testCsrfTokenStaysTheSameDuringASession(): void
    {
        $this->assertSame(csrf_token(), csrf_token());
    }

    public function testNewSessionGetsADifferentToken(): void
    {
        $first = csrf_token();
        $_SESSION = [];   // simulate a new session
        $this->assertNotSame($first, csrf_token());
    }

    public function testCsrfFieldContainsTheToken(): void
    {
        $this->assertStringContainsString('value="' . csrf_token() . '"', csrf_field());
    }

    public function testVerifyCsrfAcceptsTheCorrectToken(): void
    {
        $_POST['csrf'] = csrf_token();
        verify_csrf();   // would die() with "Invalid form token" if wrong

        $this->addToAssertionCount(1);   // reaching this line means it passed
    }
}
