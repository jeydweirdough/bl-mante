<?php

namespace App\Payments;

use Carbon\CarbonImmutable;

/** A hosted checkout the guest can be sent to. */
final class CheckoutSession
{
    public function __construct(
        public readonly string $providerReference,
        public readonly string $redirectUrl,
        public readonly CarbonImmutable $expiresAt,
        public readonly array $raw = [],
    ) {}
}
