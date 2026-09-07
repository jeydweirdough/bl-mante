<?php

namespace App\Payments;

/** What the application asks a gateway to collect. */
final class CheckoutRequest
{
    public function __construct(
        public readonly int $amountCents,
        public readonly string $currency,
        public readonly string $description,
        public readonly string $idempotencyKey,
        public readonly string $returnUrl,
        public readonly string $cancelUrl,
        public readonly ?string $customerName = null,
        public readonly ?string $customerEmail = null,
        public readonly array $metadata = [],
    ) {}
}
