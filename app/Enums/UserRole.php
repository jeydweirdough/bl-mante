<?php

namespace App\Enums;

enum UserRole: string
{
    case Customer = 'customer';
    case Staff = 'staff';
    case Admin = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::Customer => 'Customer',
            self::Staff => 'Front desk staff',
            self::Admin => 'Administrator',
        };
    }

    /**
     * Admins hold every staff capability, so "is this person hotel personnel"
     * is asked constantly and belongs here rather than in each policy.
     */
    public function isPersonnel(): bool
    {
        return $this === self::Staff || $this === self::Admin;
    }

    public function isAdmin(): bool
    {
        return $this === self::Admin;
    }
}
