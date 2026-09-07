<?php

namespace App\Enums;

enum PaymentMode: string
{
    case Online = 'online';
    case AtProperty = 'at_property';

    public function label(): string
    {
        return match ($this) {
            self::Online => 'Pay online now',
            self::AtProperty => 'Pay at the property',
        };
    }

    /**
     * Only the online path takes an unpaid hold.
     *
     * An at-property reservation has no payment by definition, so applying the
     * 30-minute hold to it would expire every such booking. Those go straight
     * to confirmed and are protected by the no-show grace period instead.
     */
    public function takesUnpaidHold(): bool
    {
        return $this === self::Online;
    }
}
