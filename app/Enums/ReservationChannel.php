<?php

namespace App\Enums;

enum ReservationChannel: string
{
    case Online = 'online';
    case WalkIn = 'walk_in';
    case Phone = 'phone';

    public function label(): string
    {
        return match ($this) {
            self::Online => 'Online',
            self::WalkIn => 'Walk-in',
            self::Phone => 'Phone',
        };
    }

    public function isStaffCreated(): bool
    {
        return $this !== self::Online;
    }
}
