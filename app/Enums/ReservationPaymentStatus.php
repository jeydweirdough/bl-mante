<?php

namespace App\Enums;

enum ReservationPaymentStatus: string
{
    case Unpaid = 'unpaid';
    case PartiallyPaid = 'partially_paid';
    case PaidInFull = 'paid_in_full';
    case PartiallyRefunded = 'partially_refunded';
    case Refunded = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::Unpaid => 'Unpaid',
            self::PartiallyPaid => 'Downpayment received',
            self::PaidInFull => 'Paid in full',
            self::PartiallyRefunded => 'Partially refunded',
            self::Refunded => 'Refunded',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Unpaid => 'bg-rose-100 text-rose-800 ring-rose-600/20',
            self::PartiallyPaid => 'bg-amber-100 text-amber-800 ring-amber-600/20',
            self::PaidInFull => 'bg-emerald-100 text-emerald-800 ring-emerald-600/20',
            self::PartiallyRefunded => 'bg-sky-100 text-sky-800 ring-sky-600/20',
            self::Refunded => 'bg-slate-100 text-slate-700 ring-slate-500/20',
        };
    }
}
