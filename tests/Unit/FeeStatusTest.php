<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/** FR-FEE-2: a fee is Unpaid, Partial or Paid depending on how much was paid */
class FeeStatusTest extends TestCase
{
    public function testNothingPaidIsUnpaid(): void
    {
        $this->assertSame('Unpaid', fee_status(3000, 0));
    }

    public function testSomePaidIsPartial(): void
    {
        $this->assertSame('Partial', fee_status(3000, 1500));
    }

    public function testFullAmountPaidIsPaid(): void
    {
        $this->assertSame('Paid', fee_status(3000, 3000));
    }

    public function testOverpaidStillCountsAsPaid(): void
    {
        $this->assertSame('Paid', fee_status(3000, 3500));
    }
}
