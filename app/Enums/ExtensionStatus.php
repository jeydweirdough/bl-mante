<?php

namespace App\Enums;

enum ExtensionStatus: string
{
    case Requested = 'requested';
    case Approved = 'approved';
    case Refused = 'refused';

    public function label(): string
    {
        return match ($this) {
            self::Requested => 'Awaiting decision',
            self::Approved => 'Approved',
            self::Refused => 'Refused',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Requested => 'bg-amber-100 text-amber-800 ring-amber-600/20',
            self::Approved => 'bg-emerald-100 text-emerald-800 ring-emerald-600/20',
            self::Refused => 'bg-rose-100 text-rose-800 ring-rose-600/20',
        };
    }
}
