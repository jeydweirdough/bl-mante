<?php

namespace App\Support;

/**
 * Formatting helpers for integer minor units.
 *
 * All money in this system is stored and arithmetic'd as integer cents. This
 * class exists only to turn those integers into something a person reads --
 * it deliberately has no arithmetic of its own, so there is never a question
 * about where rounding happened.
 */
final class Money
{
    public static function format(int $cents, bool $withSymbol = true): string
    {
        $formatted = number_format($cents / 100, 2);

        return $withSymbol
            ? config('hotel.currency_symbol').$formatted
            : $formatted;
    }

    /** Parses user input like "1,250.50" into 125050. */
    public static function parse(string $input): int
    {
        $clean = preg_replace('/[^0-9.\-]/', '', $input) ?? '0';

        return (int) round(((float) $clean) * 100);
    }

    public static function symbol(): string
    {
        return config('hotel.currency_symbol');
    }
}
