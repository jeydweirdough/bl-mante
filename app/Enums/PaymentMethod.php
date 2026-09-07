<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case MockGateway = 'mock_gateway';
    case Cash = 'cash';
    case CardTerminal = 'card_terminal';
    case BankTransfer = 'bank_transfer';

    public function label(): string
    {
        return match ($this) {
            self::MockGateway => 'Online checkout',
            self::Cash => 'Cash',
            self::CardTerminal => 'Card terminal',
            self::BankTransfer => 'Bank transfer',
        };
    }

    public function isOnline(): bool
    {
        return $this === self::MockGateway;
    }

    /** Methods a staff member may record face to face. */
    public static function faceToFace(): array
    {
        return [self::Cash, self::CardTerminal, self::BankTransfer];
    }
}
