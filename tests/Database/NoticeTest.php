<?php

namespace Tests\Database;

/**
 * FR-NOT-1/2: each role only sees notices meant for it; expired notices are hidden.
 *
 * Demo data: 2 notices for "all", 1 notice for "student".
 */
class NoticeTest extends DatabaseTestCase
{
    public function testStudentSeesNoticesForAllAndForStudents(): void
    {
        $this->loginAs(4, 'student');
        $this->assertCount(3, visible_notices());
    }

    public function testTeacherDoesNotSeeStudentOnlyNotice(): void
    {
        $this->loginAs(2, 'teacher');
        $titles = array_column(visible_notices(), 'title');

        $this->assertCount(2, $titles);
        $this->assertNotContains('Higher Math Midterm on 5 November', $titles);
    }

    public function testAdminSeesEverything(): void
    {
        $this->loginAs(1, 'admin');
        $this->assertCount(3, visible_notices());
    }

    public function testNewestNoticeComesFirstAndLimitWorks(): void
    {
        $this->loginAs(4, 'student');
        $notices = visible_notices(1);

        $this->assertCount(1, $notices);
        $this->assertSame('Office closed on Friday', $notices[0]['title']);
        $this->assertSame('Institute Admin', $notices[0]['author']);
    }

    public function testExpiredNoticeIsHidden(): void
    {
        $st = $this->db->prepare(
            "INSERT INTO notices (title, body, target_role, posted_by, expires_on) VALUES (?, 'x', 'all', 1, ?)"
        );
        $st->execute(['Old notice', date('Y-m-d', strtotime('-1 day'))]);
        $st->execute(['Ends today', today()]);

        $this->loginAs(4, 'student');
        $titles = array_column(visible_notices(), 'title');

        $this->assertNotContains('Old notice', $titles);
        $this->assertContains('Ends today', $titles);   // still visible on its last day
    }
}
