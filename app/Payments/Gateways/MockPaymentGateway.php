<?php

namespace App\Payments\Gateways;

use App\Enums\PaymentStatus;
use App\Payments\CheckoutRequest;
use App\Payments\CheckoutSession;
use App\Payments\GatewayPaymentStatus;
use App\Payments\GatewayRefundRequest;
use App\Payments\GatewayRefundResult;
use App\Payments\PaymentGateway;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * A stand-in provider that behaves like a redirect checkout.
 *
 * It holds session state in the cache rather than in the application's own
 * tables, on purpose: a real provider's records live outside this system, and
 * keeping the mock's there too means nothing in the domain schema is shaped
 * around the mock and has to be unpicked later.
 *
 * The hosted page it redirects to is served by MockGatewayController, which
 * stands in for the provider's own domain. No card fields exist on it, because
 * none exist in the real flow either -- the guest would be on the provider's
 * page at that point.
 *
 * Everything here is confined to this class and that one controller. Replacing
 * it with a real provider is a matter of writing a sibling class and changing
 * HOTEL_PAYMENT_DRIVER.
 */
class MockPaymentGateway implements PaymentGateway
{
    private const CACHE_PREFIX = 'mock-gateway:';

    public function __construct(private readonly array $config = []) {}

    public function name(): string
    {
        return 'mock';
    }

    public function createCheckout(CheckoutRequest $request): CheckoutSession
    {
        // Honour the idempotency key: a resubmitted booking form must reuse
        // the open session rather than start a second charge.
        if ($existing = $this->findByIdempotencyKey($request->idempotencyKey)) {
            return new CheckoutSession(
                providerReference: $existing['reference'],
                redirectUrl: route('payments.mock.show', $existing['reference']),
                expiresAt: CarbonImmutable::parse($existing['expires_at']),
                raw: $existing,
            );
        }

        $reference = 'mock_'.Str::lower(Str::random(24));
        $expiresAt = CarbonImmutable::now()->addMinutes($this->sessionTtl());

        $session = [
            'reference' => $reference,
            'idempotency_key' => $request->idempotencyKey,
            'amount_cents' => $request->amountCents,
            'currency' => $request->currency,
            'description' => $request->description,
            'customer_name' => $request->customerName,
            'customer_email' => $request->customerEmail,
            'return_url' => $request->returnUrl,
            'cancel_url' => $request->cancelUrl,
            'metadata' => $request->metadata,
            'status' => PaymentStatus::Pending->value,
            'created_at' => CarbonImmutable::now()->toIso8601String(),
            'expires_at' => $expiresAt->toIso8601String(),
            'paid_at' => null,
            'failure_reason' => null,
            'refunds' => [],
        ];

        $this->put($session);
        $this->rememberIdempotencyKey($request->idempotencyKey, $reference);

        return new CheckoutSession(
            providerReference: $reference,
            redirectUrl: route('payments.mock.show', $reference),
            expiresAt: $expiresAt,
            raw: $session,
        );
    }

    public function retrieve(string $providerReference): GatewayPaymentStatus
    {
        $session = $this->get($providerReference);

        if ($session === null) {
            return new GatewayPaymentStatus(
                providerReference: $providerReference,
                status: PaymentStatus::Expired,
                amountCents: 0,
                failureReason: 'The checkout session is no longer known to the provider.',
            );
        }

        // Expiry is evaluated on read, the way a real provider reports a stale
        // session rather than pushing an event when the clock ticks past it.
        if ($session['status'] === PaymentStatus::Pending->value
            && CarbonImmutable::parse($session['expires_at'])->isPast()) {
            $session['status'] = PaymentStatus::Expired->value;
            $this->put($session);
        }

        return new GatewayPaymentStatus(
            providerReference: $providerReference,
            status: PaymentStatus::from($session['status']),
            amountCents: $session['amount_cents'],
            paidAt: $session['paid_at'] ? CarbonImmutable::parse($session['paid_at']) : null,
            failureReason: $session['failure_reason'],
            raw: $session,
        );
    }

    public function refund(GatewayRefundRequest $request): GatewayRefundResult
    {
        $session = $this->get($request->providerReference);

        if ($session === null || $session['status'] !== PaymentStatus::Succeeded->value) {
            return new GatewayRefundResult(
                succeeded: false,
                failureReason: 'Only a settled charge can be refunded.',
            );
        }

        $alreadyRefunded = array_sum(array_column($session['refunds'], 'amount_cents'));

        if ($alreadyRefunded + $request->amountCents > $session['amount_cents']) {
            return new GatewayRefundResult(
                succeeded: false,
                failureReason: 'The refund would exceed the amount charged.',
            );
        }

        $reference = 'mockref_'.Str::lower(Str::random(20));

        $session['refunds'][] = [
            'reference' => $reference,
            'amount_cents' => $request->amountCents,
            'reason' => $request->reason,
            'idempotency_key' => $request->idempotencyKey,
            'created_at' => CarbonImmutable::now()->toIso8601String(),
        ];

        $this->put($session);

        return new GatewayRefundResult(
            succeeded: true,
            providerReference: $reference,
            raw: $session['refunds'],
        );
    }

    // -----------------------------------------------------------------------
    // Simulation surface
    //
    // These are what the stand-in hosted page calls. A real gateway class
    // would have no equivalent -- the provider's own site does this.
    // -----------------------------------------------------------------------

    public function session(string $providerReference): ?array
    {
        return $this->get($providerReference);
    }

    /** The guest pressed "pay" on the stand-in hosted page. */
    public function simulateApproval(string $providerReference): GatewayPaymentStatus
    {
        $session = $this->get($providerReference);

        if ($session === null || $session['status'] !== PaymentStatus::Pending->value) {
            return $this->retrieve($providerReference);
        }

        // An optional decline rate, so the failure path can be exercised
        // locally without hand-editing anything.
        $declined = $this->failureRate() > 0 && (mt_rand() / mt_getrandmax()) < $this->failureRate();

        $session['status'] = $declined ? PaymentStatus::Failed->value : PaymentStatus::Succeeded->value;
        $session['paid_at'] = $declined ? null : CarbonImmutable::now()->toIso8601String();
        $session['failure_reason'] = $declined ? 'The card issuer declined this payment.' : null;

        $this->put($session);

        return $this->retrieve($providerReference);
    }

    /** The guest pressed "cancel", or backed out of the hosted page. */
    public function simulateCancellation(string $providerReference): GatewayPaymentStatus
    {
        $session = $this->get($providerReference);

        if ($session !== null && $session['status'] === PaymentStatus::Pending->value) {
            $session['status'] = PaymentStatus::Cancelled->value;
            $session['failure_reason'] = 'The guest cancelled at the checkout page.';
            $this->put($session);
        }

        return $this->retrieve($providerReference);
    }

    // -----------------------------------------------------------------------

    private function sessionTtl(): int
    {
        return (int) ($this->config['session_ttl_minutes'] ?? 20);
    }

    private function failureRate(): float
    {
        return (float) ($this->config['failure_rate'] ?? 0.0);
    }

    private function key(string $reference): string
    {
        return self::CACHE_PREFIX.$reference;
    }

    private function get(string $reference): ?array
    {
        return Cache::get($this->key($reference));
    }

    private function put(array $session): void
    {
        // Kept well past expiry so a late return from the guest still finds a
        // definite answer rather than a missing session.
        Cache::put($this->key($session['reference']), $session, now()->addDay());
    }

    private function rememberIdempotencyKey(string $key, string $reference): void
    {
        Cache::put(self::CACHE_PREFIX.'idem:'.$key, $reference, now()->addDay());
    }

    private function findByIdempotencyKey(string $key): ?array
    {
        $reference = Cache::get(self::CACHE_PREFIX.'idem:'.$key);

        if ($reference === null) {
            return null;
        }

        $session = $this->get($reference);

        // Only an still-open session may be reused; a settled or dead one must
        // not be handed back as if it were live.
        return ($session && $session['status'] === PaymentStatus::Pending->value) ? $session : null;
    }
}
