<?php

namespace Tests\Database;

/**
 * FR-FEE-2: dues are never stored, they are calculated as fees - payments.
 *
 * Demo data:
 *   student 1: Admission 2000 (paid 2000) + Monthly 3000 (paid 1500)  -> due 1500
 *   student 2: Monthly 3000 (paid 3000)                                -> due 0
 *   student 3: Monthly 3000 + Fine 200, nothing paid                   -> due 3200
 */
class FeeTest extends DatabaseTestCase
{
    public function testFeeSummaryForPartlyPaidStudent(): void
    {
        $s = fee_summary(1);

        $this->assertEquals(5000, $s['total']);
        $this->assertEquals(3500, $s['paid']);
        $this->assertEquals(1500, $s['due']);
    }

    public function testFeeSummaryForFullyPaidStudent(): void
    {
        $this->assertEquals(0, fee_summary(2)['due']);
    }

    public function testFeeSummaryForStudentWhoPaidNothing(): void
    {
        $s = fee_summary(3);

        $this->assertEquals(3200, $s['total']);
        $this->assertEquals(0, $s['paid']);
        $this->assertEquals(3200, $s['due']);
    }

    public function testStudentWithNoFeesOwesNothing(): void
    {
        $this->assertEquals(['total' => 0, 'paid' => 0, 'due' => 0], fee_summary(9999));
    }

    public function testStudentFeesListsEachFeeWithItsDue(): void
    {
        $fees = student_fees(1);

        $this->assertCount(2, $fees);
        // Newest due date first: October monthly fee, then admission fee
        $this->assertSame('Monthly', $fees[0]['fee_type']);
        $this->assertEquals(1500, $fees[0]['due']);
        $this->assertSame('Partial', fee_status((float)$fees[0]['amount'], (float)$fees[0]['paid']));

        $this->assertSame('Admission', $fees[1]['fee_type']);
        $this->assertEquals(0, $fees[1]['due']);
        $this->assertSame('Paid', fee_status((float)$fees[1]['amount'], (float)$fees[1]['paid']));
    }

    public function testNewPaymentReducesTheDue(): void
    {
        $this->db->exec(
            "INSERT INTO payments (fee_id, amount_paid, payment_date, method, receipt_no)
             VALUES (2, 1500, '2026-11-01', 'Cash', 'RCPT-TEST-0001')"
        );

        $this->assertEquals(0, fee_summary(1)['due']);
    }
}
