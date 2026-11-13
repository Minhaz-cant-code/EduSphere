<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/** Small output/formatting helpers from includes/functions.php */
class HelpersTest extends TestCase
{
    protected function tearDown(): void
    {
        $_POST = [];
        $_GET = [];
    }

    /* ---------- e(): XSS protection (NFR-2) ---------- */

    public function testEscapeTurnsHtmlIntoSafeText(): void
    {
        $this->assertSame(
            '&lt;script&gt;alert(&quot;hi&quot;)&lt;/script&gt;',
            e('<script>alert("hi")</script>')
        );
    }

    public function testEscapeHandlesSingleQuotes(): void
    {
        $this->assertSame('O&#039;Brien', e("O'Brien"));
    }

    public function testEscapeOfNullIsEmptyString(): void
    {
        $this->assertSame('', e(null));
    }

    /* ---------- initials() ---------- */

    public function testInitialsOfTwoWordName(): void
    {
        $this->assertSame('TA', initials('Tanvir Ahmed'));
    }

    public function testInitialsUseOnlyFirstTwoWordsAndIgnoreExtraSpaces(): void
    {
        $this->assertSame('AK', initials('  abdul   karim khan '));
    }

    public function testInitialsOfEmptyNameIsQuestionMark(): void
    {
        $this->assertSame('?', initials(''));
    }

    /* ---------- labels & money ---------- */

    public function testRoleLabels(): void
    {
        $this->assertSame('Administrator', role_label('admin'));
        $this->assertSame('Teacher', role_label('teacher'));
        $this->assertSame('Guest', role_label('guest'));   // unknown role -> capitalised
    }

    public function testMoneyHasTakaSignAndTwoDecimals(): void
    {
        $this->assertSame("৳\u{00A0}1,500.00", money(1500));
        $this->assertSame("৳\u{00A0}0.00", money(null));
    }

    public function testMoneyKpiHidesDecimalsForWholeAmounts(): void
    {
        $this->assertSame('<span class="unit">৳</span>2,500', money_kpi(2500));
        $this->assertSame('<span class="unit">৳</span>2,500.50', money_kpi(2500.5));
    }

    public function testDateFormatting(): void
    {
        $this->assertSame('19 Oct 2026', fmt_date('2026-10-19'));
        $this->assertSame('-', fmt_date(null));
        $this->assertSame('-', fmt_date(''));
    }

    public function testUrlAddsBaseUrl(): void
    {
        $this->assertSame('/edusphere/login.php', url('login.php'));
        $this->assertSame('/edusphere/admin/students.php', url('/admin/students.php'));
    }

    /* ---------- status_badge() ---------- */

    public function testStatusBadgeColours(): void
    {
        $this->assertStringContainsString('text-bg-success', status_badge('Present'));
        $this->assertStringContainsString('text-bg-danger', status_badge('Unpaid'));
        $this->assertStringContainsString('text-bg-warning', status_badge('Late'));
        $this->assertStringContainsString('text-bg-secondary', status_badge('Unknown'));
    }

    public function testStatusBadgeEscapesText(): void
    {
        $this->assertStringNotContainsString('<b>', status_badge('<b>'));
    }

    /* ---------- request helpers ---------- */

    public function testPostIsTrimmedAndHasDefault(): void
    {
        $_POST['name'] = '   Tanvir   ';
        $this->assertSame('Tanvir', post('name'));
        $this->assertSame('none', post('missing', 'none'));
    }

    public function testGetIntConvertsToInteger(): void
    {
        $_GET['id'] = '42abc';
        $this->assertSame(42, get_int('id'));
        $this->assertSame(7, get_int('page', 7));
    }
}
