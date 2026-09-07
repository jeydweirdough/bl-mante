<?php

namespace App\Enums;

enum CancellationInitiator: string
{
    case Customer = 'customer';
    case Hotel = 'hotel';
    case System = 'system';

    public function label(): string
    {
        return match ($this) {
            self::Customer => 'Customer',
            self::Hotel => 'Hotel',
            self::System => 'System',
        };
    }

    /**
     * Hotel-initiated cancellations are refunded in full regardless of timing,
     * so the tier calculation is skipped entirely for them.
     */
    public function forcesFullRefund(): bool
    {
        return $this === self::Hotel;
    }
}
