<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * FR-EXM-3: grade & GPA from marks (Bangladesh 5.00 scale).
 * 80+ A+ (5.00), 70 A (4.00), 60 A- (3.50), 50 B (3.00), 40 C (2.00), 33 D (1.00), below 33 F (0.00)
 */
class GradeTest extends TestCase
{
    /** Each row: marks, total marks, expected grade, expected GPA */
    public static function gradeCases(): array
    {
        return [
            'full marks'           => [100, 100, 'A+', 5.00],
            'exactly 80 is A+'     => [80, 100, 'A+', 5.00],
            '79.5 is only A'       => [79.5, 100, 'A', 4.00],
            'exactly 70 is A'      => [70, 100, 'A', 4.00],
            'exactly 60 is A-'     => [60, 100, 'A-', 3.50],
            'exactly 50 is B'      => [50, 100, 'B', 3.00],
            'exactly 40 is C'      => [40, 100, 'C', 2.00],
            'exactly 33 is D'      => [33, 100, 'D', 1.00],
            '32 fails'             => [32, 100, 'F', 0.00],
            'zero marks'           => [0, 100, 'F', 0.00],
            '40 out of 50 = 80%'   => [40, 50, 'A+', 5.00],
            '26 out of 40 = 65%'   => [26, 40, 'A-', 3.50],
        ];
    }

    #[DataProvider('gradeCases')]
    public function testGradeAndGpaFromMarks(float $marks, float $total, string $grade, float $gpa): void
    {
        $this->assertSame([$grade, $gpa], calc_grade($marks, $total));
    }

    public function testTotalOfZeroDoesNotCrashAndGivesF(): void
    {
        // Division by zero is guarded inside calc_grade()
        $this->assertSame(['F', 0.00], calc_grade(10, 0));
    }
}
