<?php

namespace App\Enums;

enum PaymentChannel: string
{
    case Online = 'online';
    case AtProperty = 'at_property';

    public function label(): string
    {
        return match ($this) {
            self::Online => 'Online',
            self::AtProperty => 'At the property',
        };
    }

    /** Face-to-face payments must be attributed to the staff member who took them. */
    public function requiresStaffAttribution(): bool
    {
        return $this === self::AtProperty;
    }
}
