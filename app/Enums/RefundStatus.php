<?php

namespace App\Enums;

enum RefundStatus: string
{
    case Pending = 'pending';
    case Succeeded = 'succeeded';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Processing',
            self::Succeeded => 'Refunded',
            self::Failed => 'Failed',
        };
    }
}
