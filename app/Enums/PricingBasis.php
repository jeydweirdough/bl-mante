<?php

namespace App\Enums;

enum PricingBasis: string
{
    case PerBooking = 'per_booking';
    case PerHour = 'per_hour';
    case PerPerson = 'per_person';

    public function label(): string
    {
        return match ($this) {
            self::PerBooking => 'Per booking',
            self::PerHour => 'Per hour',
            self::PerPerson => 'Per person',
        };
    }

    public function unitNoun(): string
    {
        return match ($this) {
            self::PerBooking => 'booking',
            self::PerHour => 'hour',
            self::PerPerson => 'person',
        };
    }

    /**
     * The multiplier applied to the unit price, on top of quantity.
     *
     * Hours and persons are passed in explicitly rather than read from the
     * reservation, because a per-hour extra added mid-stay may cover fewer
     * hours than the package, and a per-person extra must not silently
     * re-price if the guest count is edited later.
     */
    public function multiplier(int $hours, int $persons): int
    {
        return match ($this) {
            self::PerBooking => 1,
            self::PerHour => max(1, $hours),
            self::PerPerson => max(1, $persons),
        };
    }
}
