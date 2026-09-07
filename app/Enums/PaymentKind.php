<?php

namespace App\Enums;

enum PaymentKind: string
{
    case Downpayment = 'downpayment';
    case Balance = 'balance';
    case Full = 'full';
    case Extension = 'extension';
    case Extra = 'extra';

    public function label(): string
    {
        return match ($this) {
            self::Downpayment => 'Downpayment',
            self::Balance => 'Balance',
            self::Full => 'Full payment',
            self::Extension => 'Extension charge',
            self::Extra => 'Extra charge',
        };
    }
}
