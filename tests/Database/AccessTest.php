<?php

namespace Tests\Database;

/**
 * FR-AUTH-4 (RBAC): which batches and subjects a teacher/admin may manage.
 *
 * Demo data: teacher1 (users.id 2, teachers.id 1) teaches Physics and Higher Math,
 * both in "HSC Science - Batch A" (batch 1). He does not teach anything in batch 2.
 *
 * Note: my_teacher_id() and my_student_id() remember their answer in a "static"
 * variable for the rest of the request. So in these tests we always log in as
 * the SAME teacher (teacher1) and the SAME student (student1).
 */
class AccessTest extends DatabaseTestCase
{
    public function testTeacherSeesOnlyTheSubjectsHeTeaches(): void
    {
        $this->loginAs(2, 'teacher');

        $this->assertSame(1, my_teacher_id());
        $this->assertSame(['Higher Math', 'Physics'], array_column(teacher_subjects(), 'name'));
    }

    public function testTeacherCanManageOnlyHisBatch(): void
    {
        $this->loginAs(2, 'teacher');

        $this->assertTrue(can_manage_batch(1));
        $this->assertFalse(can_manage_batch(2));
    }

    public function testTeacherCanManageOnlyHisSubjects(): void
    {
        $this->loginAs(2, 'teacher');

        $this->assertTrue(can_manage_subject(1));    // Physics
        $this->assertFalse(can_manage_subject(2));   // Chemistry - taught by teacher2
    }

    public function testAdminCanManageAllBatchesAndSubjects(): void
    {
        $this->loginAs(1, 'admin');

        $this->assertCount(5, manageable_subjects());
        $this->assertSame(['HSC Science - Batch A', 'IELTS Evening'], array_column(manageable_batches(), 'name'));
        $this->assertTrue(can_manage_batch(2));
        $this->assertTrue(can_manage_subject(2));
    }

    public function testStudentIsLinkedToHisOwnStudentRecord(): void
    {
        $this->loginAs(4, 'student');   // users.id 4 = Tanvir = students.id 1
        $this->assertSame(1, my_student_id());
    }
}
