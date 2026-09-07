<?php

namespace App\Support;

use App\Enums\RefundTier;

/**
 * What a cancellation would return, and why.
 *
 * Computed before anything is written so the cancel screen can show the guest
 * the figure and the reason before they commit, and computed again inside the
 * cancellation transaction so the number acted on is the number quoted.
 */
final class RefundOutcome
{
    public function __construct(
        public readonly RefundTier $tier,
        public readonly int $refundableCents,
        public readonly int $forfeitedCents,
        public readonly int $paidNetCents,
        public readonly string $explanation,
    ) {}

    public function isRefundable(): bool
    {
        return $this->refundableCents > 0;
    }
}
