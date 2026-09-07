<?php

namespace App\Payments;

final class GatewayRefundRequest
{
    public function __construct(
        public readonly string $providerReference,
        public readonly int $amountCents,
        public readonly string $currency,
        public readonly string $reason,
        public readonly string $idempotencyKey,
    ) {}
}
