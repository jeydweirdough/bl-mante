<?php

namespace App\Services;

use App\Enums\CancellationInitiator;
use App\Enums\RefundTier;
use App\Models\Reservation;
use App\Support\Money;
use App\Support\RefundOutcome;
use Carbon\CarbonInterface;

/**
 * The cancellation tiers.
 *
 * Every threshold is read from the reservation's own policy version, never
 * from the current one. A guest who booked under a 24-hour full-refund window
 * keeps that window even if an admin later tightens it -- which is the whole
 * point of freezing the policy at booking time.
 *
 * The tiers, from the requirements:
 *
 *   more than 24h before start   full refund of everything paid
 *   between 6h and 24h           downpayment forfeited, any excess refunded
 *   under 6h                     no refund
 *   no-show                      no refund, room released
 *   hotel-initiated              full refund regardless of timing
 *
 * The boundaries are inclusive at the lower edge of the middle tier: exactly
 * 24 hours out is the partial tier, exactly 6 hours out is still the partial
 * tier, and anything under 6 is nothing. That reading matches "between 6 and
 * 24" and means a guest is never worse off for being exactly on a boundary
 * than a second either side of it.
 */
class RefundCalculator
{
    /**
     * What cancelling this reservation right now would return.
     *
     * $at exists so the cancel screen and the transaction that follows it can
     * be handed the same instant, rather than each calling now() and landing
     * on opposite sides of a boundary.
     */
    public function forCancellation(
        Reservation $reservation,
        CancellationInitiator $initiator = CancellationInitiator::Customer,
        ?CarbonInterface $at = null,
    ): RefundOutcome {
        $at ??= now();
        $policy = $reservation->policyVersion;

        // Money actually in hand: settled payments less anything already
        // returned. Using the reservation's roll-ups keeps this consistent
        // with what the guest sees on their booking.
        $paidNet = max(0, $reservation->amount_paid_cents - $reservation->amount_refunded_cents);

        if ($paidNet === 0) {
            return new RefundOutcome(
                tier: RefundTier::None,
                refundableCents: 0,
                forfeitedCents: 0,
                paidNetCents: 0,
                explanation: 'Nothing has been paid on this booking, so there is nothing to refund.',
            );
        }

        // A hotel-initiated cancellation short-circuits the tiers entirely.
        if ($initiator->forcesFullRefund()) {
            return new RefundOutcome(
                tier: RefundTier::Full,
                refundableCents: $paidNet,
                forfeitedCents: 0,
                paidNetCents: $paidNet,
                explanation: 'The hotel cancelled this booking, so everything paid is returned in full.',
            );
        }

        $hoursUntilStart = $at->diffInHours($reservation->starts_at, false);

        if ($hoursUntilStart > $policy->full_refund_hours_before) {
            return new RefundOutcome(
                tier: RefundTier::Full,
                refundableCents: $paidNet,
                forfeitedCents: 0,
                paidNetCents: $paidNet,
                explanation: sprintf(
                    'Cancelled more than %d hours before the stay begins, so everything paid is returned.',
                    $policy->full_refund_hours_before,
                ),
            );
        }

        if ($hoursUntilStart >= $policy->partial_refund_hours_before) {
            // The downpayment is forfeited whether or not the guest actually
            // chose the downpayment option: it is the cancellation fee, and a
            // guest who paid in full is refunded everything above it.
            $forfeited = min($policy->downpaymentFor($reservation->total_cents), $paidNet);

            return new RefundOutcome(
                tier: RefundTier::DownpaymentForfeited,
                refundableCents: $paidNet - $forfeited,
                forfeitedCents: $forfeited,
                paidNetCents: $paidNet,
                explanation: sprintf(
                    'Cancelled between %d and %d hours before the stay, so the %s downpayment is forfeited and the rest is returned.',
                    $policy->partial_refund_hours_before,
                    $policy->full_refund_hours_before,
                    Money::format($forfeited),
                ),
            );
        }

        return new RefundOutcome(
            tier: RefundTier::None,
            refundableCents: 0,
            forfeitedCents: $paidNet,
            paidNetCents: $paidNet,
            explanation: sprintf(
                'Cancelled less than %d hours before the stay begins, so no amount is returned.',
                $policy->partial_refund_hours_before,
            ),
        );
    }

    /** A no-show forfeits everything paid and releases the room. */
    public function forNoShow(Reservation $reservation): RefundOutcome
    {
        $paidNet = max(0, $reservation->amount_paid_cents - $reservation->amount_refunded_cents);

        return new RefundOutcome(
            tier: RefundTier::None,
            refundableCents: 0,
            forfeitedCents: $paidNet,
            paidNetCents: $paidNet,
            explanation: 'Marked as a no-show, so all payment is forfeited and the room is released.',
        );
    }

    /**
     * Whether the free reschedule is still on the table.
     *
     * One free move, more than the configured window out, and the reservation
     * must not have been moved before. Anything else is handled as a
     * cancellation followed by a new booking, which prices under today's terms.
     */
    public function freeRescheduleAvailable(Reservation $reservation, ?CarbonInterface $at = null): bool
    {
        $at ??= now();

        return $reservation->hasFreeRescheduleLeft()
            && $at->diffInHours($reservation->starts_at, false) > $reservation->policyVersion->free_reschedule_hours_before;
    }
}
