<?php

namespace Tests\Database;

/**
 * FR-ATT-2: attendance percentage is calculated automatically.
 * "Late" counts as attended.
 *
 * Demo data (19-22 Oct 2026):
 *   student 1: Present, Present, Absent,  Present
 *   student 2: Present, Late,    Present, Present
 *   student 3: Absent,  Present, Present, Late
 */
class AttendanceTest extends DatabaseTestCase
{
    public function testSummaryCountsEachStatus(): void
    {
        $s = attendance_summary(1);

        $this->assertSame(3, $s['present']);
        $this->assertSame(1, $s['absent']);
        $this->assertSame(0, $s['late']);
        $this->assertSame(4, $s['total']);
        $this->assertEquals(75.0, $s['percent']);
    }

    public function testLateCountsAsAttended(): void
    {
        $s = attendance_summary(2);   // 3 present + 1 late out of 4

        $this->assertSame(1, $s['late']);
        $this->assertEquals(100.0, $s['percent']);
    }

    public function testDateRangeOnlyCountsDaysInsideTheRange(): void
    {
        $s = attendance_summary(1, '2026-10-19', '2026-10-20');   // both Present

        $this->assertSame(2, $s['total']);
        $this->assertEquals(100.0, $s['percent']);
    }

    public function testStudentWithNoAttendanceHasZeroPercent(): void
    {
        $s = attendance_summary(9999);

        $this->assertSame(0, $s['total']);
        $this->assertEquals(0, $s['percent']);
    }

    public function testPercentUpdatesWhenNewAttendanceIsMarked(): void
    {
        $this->db->prepare(
            "INSERT INTO attendance (student_id, batch_id, att_date, status) VALUES (2, 1, '2026-10-23', 'Absent')"
        )->execute();

        $this->assertEquals(80.0, attendance_summary(2)['percent']);   // 4 of 5 attended
    }

    public function testSameStudentCannotBeMarkedTwiceOnTheSameDay(): void
    {
        $this->expectException(\PDOException::class);   // UNIQUE (student_id, att_date)

        $this->db->exec("INSERT INTO attendance (student_id, batch_id, att_date, status) VALUES (1, 1, '2026-10-19', 'Late')");
    }
}
