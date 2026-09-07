<?php

namespace App\Payments;

use App\Enums\PaymentStatus;
use Carbon\CarbonImmutable;

/** The provider's answer to "what happened to this charge". */
final class GatewayPaymentStatus
{
    public function __construct(
        public readonly string $providerReference,
        public readonly PaymentStatus $status,
        public readonly int $amountCents,
        public readonly ?CarbonImmutable $paidAt = null,
        public readonly ?string $failureReason = null,
        public readonly array $raw = [],
    ) {}

    public function isSettled(): bool
    {
        return $this->status === PaymentStatus::Succeeded;
    }
}
