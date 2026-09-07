<?php

namespace App\Support;

use App\Enums\PricingBasis;
use App\Models\Extra;

/** One priced extra within a Quote, carrying enough to write the snapshot row. */
final class QuoteLine
{
    public function __construct(
        public readonly Extra $extra,
        public readonly int $quantity,
        public readonly int $hours,
        public readonly int $persons,
        public readonly int $unitPriceCents,
        public readonly PricingBasis $basis,
        public readonly int $lineTotalCents,
    ) {}

    public function describe(): string
    {
        return match ($this->basis) {
            PricingBasis::PerBooking => $this->quantity.' x '.Money::format($this->unitPriceCents),
            PricingBasis::PerHour => $this->quantity.' x '.$this->hours.'h x '.Money::format($this->unitPriceCents),
            PricingBasis::PerPerson => $this->quantity.' x '.$this->persons.' guests x '.Money::format($this->unitPriceCents),
        };
    }

    /** The reservation_extras row this line becomes, minus the reservation id. */
    public function toSnapshot(): array
    {
        return [
            'extra_id' => $this->extra->id,
            'name_snapshot' => $this->extra->name,
            'unit_price_cents_snapshot' => $this->unitPriceCents,
            'pricing_basis_snapshot' => $this->basis->value,
            'quantity' => $this->quantity,
            'hours' => $this->basis === PricingBasis::PerHour ? $this->hours : null,
            'persons' => $this->basis === PricingBasis::PerPerson ? $this->persons : null,
            'line_total_cents' => $this->lineTotalCents,
        ];
    }
}
