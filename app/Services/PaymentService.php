<?php

namespace App\Services;

use App\Enums\PaymentChannel;
use App\Enums\PaymentKind;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\RefundReason;
use App\Enums\RefundStatus;
use App\Enums\RefundTier;
use App\Enums\ReservationStatus;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Reservation;
use App\Models\User;
use App\Payments\CheckoutRequest;
use App\Payments\CheckoutSession;
use App\Payments\GatewayRefundRequest;
use App\Payments\PaymentGateway;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Everything that moves money, and nothing that decides whether it should.
 *
 * The gateway is reached only through the PaymentGateway interface, so no
 * provider name appears anywhere below. Face-to-face payments never touch it
 * at all -- they are recorded directly and attributed to the staff member who
 * took them, which is the requirement.
 *
 * Every method that writes a payment or refund row also recomputes the
 * reservation's money roll-ups inside the same transaction, so the ledger and
 * the denormalised columns can never be observed out of step.
 */
class PaymentService
{
    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly ReservationStateMachine $stateMachine,
        private readonly AuditLogger $audit,
    ) {}

    // -----------------------------------------------------------------------
    // Online
    // -----------------------------------------------------------------------

    /**
     * Open a hosted checkout and record the in-flight payment against it.
     *
     * The payment row is written first and deliberately left Pending: if the
     * guest disappears at the provider's page, we still have a record that a
     * charge was attempted and can ask the provider what became of it.
     *
     * @return array{payment: Payment, session: CheckoutSession}
     */
    public function startOnlineCheckout(
        Reservation $reservation,
        PaymentKind $kind,
        int $amountCents,
        string $returnUrl,
        string $cancelUrl,
    ): array {
        if ($amountCents <= 0) {
            throw new InvalidArgumentException('An online checkout needs a positive amount.');
        }

        return DB::transaction(function () use ($reservation, $kind, $amountCents, $returnUrl, $cancelUrl) {
            // Reuse an open checkout for the same reservation, kind and amount
            // rather than opening a second one when a guest double-submits or
            // reloads the payment page.
            $existing = $reservation->payments()
                ->where('status', PaymentStatus::Pending)
                ->where('kind', $kind)
                ->where('amount_cents', $amountCents)
                ->whereNotNull('provider_reference')
                ->latest('id')
                ->first();

            $payment = $existing ?? Payment::create([
                'reservation_id' => $reservation->id,
                'kind' => $kind->value,
                'channel' => PaymentChannel::Online->value,
                'method' => PaymentMethod::MockGateway->value,
                'amount_cents' => $amountCents,
                'currency' => $reservation->currency,
                'status' => PaymentStatus::Pending->value,
                'provider' => $this->gateway->name(),
                'idempotency_key' => (string) Str::uuid(),
                'initiated_at' => now(),
            ]);

            $session = $this->gateway->createCheckout(new CheckoutRequest(
                amountCents: $amountCents,
                currency: $reservation->currency,
                description: sprintf('%s for booking %s', $kind->label(), $reservation->reference),
                idempotencyKey: $payment->idempotency_key,
                returnUrl: $returnUrl,
                cancelUrl: $cancelUrl,
                customerName: $reservation->guestName(),
                customerEmail: $reservation->guestEmail(),
                metadata: [
                    'reservation_reference' => $reservation->reference,
                    'payment_id' => $payment->id,
                ],
            ));

            $payment->forceFill([
                'provider_reference' => $session->providerReference,
                'provider' => $this->gateway->name(),
            ])->save();

            return ['payment' => $payment, 'session' => $session];
        });
    }

    /**
     * Ask the provider what happened and apply the answer.
     *
     * This is the authority on whether a payment settled -- never the URL the
     * guest came back on. A return URL can be reloaded, shared or forged; the
     * provider's own record cannot.
     */
    public function syncFromGateway(Payment $payment): Payment
    {
        if ($payment->provider_reference === null || $payment->status !== PaymentStatus::Pending) {
            return $payment;
        }

        $status = $this->gateway->retrieve($payment->provider_reference);

        return DB::transaction(function () use ($payment, $status) {
            $payment->status = $status->status;
            $payment->provider_payload = $status->raw;

            if ($status->isSettled()) {
                $payment->paid_at = $status->paidAt ?? now();
            } elseif ($status->status !== PaymentStatus::Pending) {
                $payment->failed_at = now();
                $payment->failure_reason = $status->failureReason;
            }

            $payment->save();

            if ($status->isSettled()) {
                $this->afterSettlement($payment);
            }

            return $payment;
        });
    }

    // -----------------------------------------------------------------------
    // At the property
    // -----------------------------------------------------------------------

    /**
     * Record a payment taken at the desk.
     *
     * The staff member is required, not optional: the whole point of this path
     * is that face-to-face money is attributable to a person.
     */
    public function recordFaceToFace(
        Reservation $reservation,
        int $amountCents,
        PaymentMethod $method,
        User $staff,
        PaymentKind $kind,
        ?string $notes = null,
    ): Payment {
        if ($amountCents <= 0) {
            throw new InvalidArgumentException('A recorded payment needs a positive amount.');
        }

        if ($method->isOnline()) {
            throw new InvalidArgumentException('An online method cannot be recorded as a face-to-face payment.');
        }

        return DB::transaction(function () use ($reservation, $amountCents, $method, $staff, $kind, $notes) {
            $payment = Payment::create([
                'reservation_id' => $reservation->id,
                'kind' => $kind->value,
                'channel' => PaymentChannel::AtProperty->value,
                'method' => $method->value,
                'amount_cents' => $amountCents,
                'currency' => $reservation->currency,
                'status' => PaymentStatus::Succeeded->value,
                'idempotency_key' => (string) Str::uuid(),
                'recorded_by_user_id' => $staff->id,
                'initiated_at' => now(),
                'paid_at' => now(),
                'notes' => $notes,
            ]);

            $this->audit->record(
                action: 'payment.recorded_at_property',
                subject: $payment,
                actor: $staff,
                after: [
                    'reservation' => $reservation->reference,
                    'amount' => Money::format($amountCents),
                    'method' => $method->value,
                ],
            );

            $this->afterSettlement($payment);

            return $payment;
        });
    }

    // -----------------------------------------------------------------------
    // Refunds
    // -----------------------------------------------------------------------

    /**
     * Return money against a reservation.
     *
     * The amount is allocated across settled payments newest first. Online
     * payments are reversed through the gateway; anything taken at the desk is
     * recorded as a counter refund for a staff member to hand back, because
     * this system cannot push cash out of a drawer.
     *
     * @return list<Refund>
     */
    public function refund(
        Reservation $reservation,
        int $amountCents,
        RefundReason $reason,
        ?RefundTier $tier = null,
        ?User $actor = null,
        ?string $notes = null,
    ): array {
        if ($amountCents <= 0) {
            return [];
        }

        return DB::transaction(function () use ($reservation, $amountCents, $reason, $tier, $actor, $notes) {
            $remaining = $amountCents;
            $created = [];

            $payments = $reservation->payments()
                ->where('status', PaymentStatus::Succeeded)
                ->orderByDesc('paid_at')
                ->orderByDesc('id')
                ->get();

            foreach ($payments as $payment) {
                if ($remaining <= 0) {
                    break;
                }

                $available = $payment->amount_cents - $payment->refundedCents();

                if ($available <= 0) {
                    continue;
                }

                $slice = min($available, $remaining);

                $created[] = $this->refundAgainst($payment, $slice, $reason, $tier, $actor, $notes);
                $remaining -= $slice;
            }

            $reservation->refresh()->recalculateFinancials();
            $reservation->save();

            $this->audit->record(
                action: 'payment.refunded',
                subject: $reservation,
                actor: $actor,
                after: [
                    'requested' => Money::format($amountCents),
                    'issued' => Money::format($amountCents - $remaining),
                    'tier' => $tier?->value,
                    'reason' => $reason->value,
                ],
            );

            return $created;
        });
    }

    private function refundAgainst(
        Payment $payment,
        int $amountCents,
        RefundReason $reason,
        ?RefundTier $tier,
        ?User $actor,
        ?string $notes,
    ): Refund {
        $refund = Refund::create([
            'reservation_id' => $payment->reservation_id,
            'payment_id' => $payment->id,
            'amount_cents' => $amountCents,
            'currency' => $payment->currency,
            'reason' => $reason->value,
            'tier_applied' => $tier?->value,
            'status' => RefundStatus::Pending->value,
            'method' => $payment->method->value,
            'processed_by_user_id' => $actor?->id,
            'notes' => $notes,
        ]);

        if ($payment->method->isOnline() && $payment->provider_reference !== null) {
            $result = $this->gateway->refund(new GatewayRefundRequest(
                providerReference: $payment->provider_reference,
                amountCents: $amountCents,
                currency: $payment->currency,
                reason: $reason->value,
                idempotencyKey: (string) Str::uuid(),
            ));

            $refund->forceFill([
                'status' => $result->succeeded ? RefundStatus::Succeeded->value : RefundStatus::Failed->value,
                'provider_reference' => $result->providerReference,
                'processed_at' => $result->succeeded ? now() : null,
                'notes' => $result->failureReason ?? $refund->notes,
            ])->save();

            return $refund;
        }

        // Money taken at the counter goes back over the counter. It is marked
        // settled because the staff member issuing it is the one performing
        // the action, and the attribution is on the row.
        $refund->forceFill([
            'status' => RefundStatus::Succeeded->value,
            'processed_at' => now(),
        ])->save();

        return $refund;
    }

    // -----------------------------------------------------------------------

    /**
     * What a settled payment implies for the reservation.
     *
     * Roll-ups first, then the state change: a pending reservation that has
     * received money is confirmed, and its unpaid hold is released by the
     * state machine.
     */
    private function afterSettlement(Payment $payment): void
    {
        $reservation = $payment->reservation()->first();

        $reservation->recalculateFinancials();
        $reservation->save();

        if ($reservation->status === ReservationStatus::Pending) {
            $this->stateMachine->transition(
                reservation: $reservation,
                to: ReservationStatus::Confirmed,
                actor: $payment->recordedBy,
                reason: 'Payment received.',
                context: [
                    'payment_id' => $payment->id,
                    'amount_cents' => $payment->amount_cents,
                ],
            );
        }
    }
}
