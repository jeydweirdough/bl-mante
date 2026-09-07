<?php

namespace App\Support;

use App\Models\PolicyVersion;

/**
 * A priced booking, before it is written down.
 *
 * Produced by PricingService and consumed by the booking form, the confirm
 * screen and ReservationService. Every figure is integer minor units.
 *
 * The composition is fixed by the requirements:
 *
 *     package price + extras + extensions
 *   - discount
 *   = taxable
 *   + tax + fees
 *   = total
 */
final class Quote
{
    /**
     * @param  list<QuoteLine>  $extraLines
     */
    public function __construct(
        public readonly int $packagePriceCents,
        public readonly int $extrasTotalCents,
        public readonly int $extensionsTotalCents,
        public readonly int $discountTotalCents,
        public readonly int $taxTotalCents,
        public readonly int $feesTotalCents,
        public readonly int $totalCents,
        public readonly array $extraLines,
        public readonly PolicyVersion $policy,
    ) {}

    /** What must be paid up front to hold the booking under the downpayment option. */
    public function downpaymentCents(): int
    {
        return $this->policy->downpaymentFor($this->totalCents);
    }

    /** What would still be owed at the property after a downpayment. */
    public function balanceAfterDownpaymentCents(): int
    {
        return $this->totalCents - $this->downpaymentCents();
    }

    public function subtotalCents(): int
    {
        return $this->packagePriceCents + $this->extrasTotalCents + $this->extensionsTotalCents;
    }

    public function taxableCents(): int
    {
        return max(0, $this->subtotalCents() - $this->discountTotalCents);
    }

    /** The reservation columns this quote fills in. */
    public function toReservationAttributes(): array
    {
        return [
            'package_price_cents' => $this->packagePriceCents,
            'extras_total_cents' => $this->extrasTotalCents,
            'extensions_total_cents' => $this->extensionsTotalCents,
            'discount_total_cents' => $this->discountTotalCents,
            'tax_total_cents' => $this->taxTotalCents,
            'fees_total_cents' => $this->feesTotalCents,
            'total_cents' => $this->totalCents,
        ];
    }
}
