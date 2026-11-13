<?php

namespace Tests\Database;

/**
 * FR-EXM-3: every stored result must have the grade and GPA that
 * calc_grade() gives for its marks and the exam's total marks.
 */
class ResultsTest extends DatabaseTestCase
{
    public function testStoredGradesMatchCalcGrade(): void
    {
        $rows = $this->db->query(
            'SELECT r.marks, r.grade, r.gpa, e.total_marks
               FROM results r JOIN exams e ON e.id = r.exam_id'
        )->fetchAll();

        $this->assertNotEmpty($rows);
        foreach ($rows as $r) {
            [$grade, $gpa] = calc_grade((float)$r['marks'], (float)$r['total_marks']);
            $this->assertSame($r['grade'], $grade, "Wrong grade for {$r['marks']}/{$r['total_marks']}");
            $this->assertEquals((float)$r['gpa'], $gpa);
        }
    }
}
