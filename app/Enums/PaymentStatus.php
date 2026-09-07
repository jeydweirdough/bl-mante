<?php

namespace App\Enums;

enum PaymentStatus: string
{
    /** Guest has been sent to the gateway and has not come back yet. */
    case Pending = 'pending';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case Expired = 'expired';

    public function isSettled(): bool
    {
        return $this === self::Succeeded;
    }

    public function isOpen(): bool
    {
        return $this === self::Pending;
    }

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Awaiting payment',
            self::Succeeded => 'Paid',
            self::Failed => 'Failed',
            self::Cancelled => 'Cancelled',
            self::Expired => 'Expired',
        };
    }
}
