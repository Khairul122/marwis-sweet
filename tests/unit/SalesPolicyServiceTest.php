<?php

use App\Services\Money;
use App\Services\SalesPolicyService;
use PHPUnit\Framework\TestCase;

/** @internal */
final class SalesPolicyServiceTest extends TestCase
{
    public function testPercentageDiscountAndChargesRoundHalfUpToWholeRupiah(): void
    {
        $discount = SalesPolicyService::calculateDiscount(432000, 'percent', '10.00', 0, 0);

        $this->assertSame(43200, $discount['amount_cents']);
        $this->assertSame(49900, SalesPolicyService::chargeCents(453600, 1100));
        $this->assertSame(100, Money::percentageRupiahCents(50, 10000));
    }

    public function testRupiahAllocationsLeaveRemainderOnLastLine(): void
    {
        $this->assertSame([3300, 3300, 3400], Money::allocateRupiahCents(10000, [1, 1, 1]));
    }

    public function testNominalDiscountRejectsFractionalRupiah(): void
    {
        $this->expectException(RuntimeException::class);
        SalesPolicyService::calculateDiscount(100000, 'amount', '10.50', 0, 0);
    }
}
