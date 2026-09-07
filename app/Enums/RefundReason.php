<?php

namespace App\Enums;

enum RefundReason: string
{
    case CustomerCancellation = 'customer_cancellation';
    case HotelCancellation = 'hotel_cancellation';
    case Reschedule = 'reschedule';
    case Overpayment = 'overpayment';
    case Goodwill = 'goodwill';

    public function label(): string
    {
        return match ($this) {
            self::CustomerCancellation => 'Customer cancellation',
            self::HotelCancellation => 'Hotel cancellation',
            self::Reschedule => 'Reschedule',
            self::Overpayment => 'Overpayment',
            self::Goodwill => 'Goodwill',
        };
    }
}
