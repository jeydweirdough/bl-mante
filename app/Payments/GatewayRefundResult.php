<?php

namespace App\Payments;

final class GatewayRefundResult
{
    public function __construct(
        public readonly bool $succeeded,
        public readonly ?string $providerReference = null,
        public readonly ?string $failureReason = null,
        public readonly array $raw = [],
    ) {}
}
