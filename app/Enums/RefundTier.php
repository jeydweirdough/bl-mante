<?php

namespace App\Enums;

enum RefundTier: string
{
    /** More than `full_refund_hours_before` out, or hotel-initiated: everything back. */
    case Full = 'full';

    /** Inside the partial window: the downpayment is forfeited, any excess returned. */
    case DownpaymentForfeited = 'downpayment_forfeited';

    /** Inside the no-refund window, or a no-show: nothing returned. */
    case None = 'none';

    public function label(): string
    {
        return match ($this) {
            self::Full => 'Full refund',
            self::DownpaymentForfeited => 'Downpayment forfeited',
            self::None => 'No refund',
        };
    }

    public function explanation(): string
    {
        return match ($this) {
            self::Full => 'Everything paid is returned.',
            self::DownpaymentForfeited => 'The downpayment is kept by the hotel; anything paid above it is returned.',
            self::None => 'No amount is returned.',
        };
    }
}
