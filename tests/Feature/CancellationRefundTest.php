<?php

namespace Tests\Feature;

use App\Enums\CancellationInitiator;
use App\Enums\PaymentKind;
use App\Enums\PaymentMethod;
use App\Enums\RefundTier;
use App\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Services\AvailabilityService;
use App\Services\PaymentService;
use App\Services\PolicyService;
use App\Services\RefundCalculator;
use App\Services\ReservationService;
use App\Support\BookingWindow;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsProperty;
use Tests\TestCase;

/**
 * The cancellation tiers, and the guarantee that a booking is judged by the
 * policy in force when it was made rather than today's.
 */
class CancellationRefundTest extends TestCase
{
    use BuildsProperty, RefreshDatabase;

    private RefundCalculator $calculator;

    private ReservationService $reservations;

    private PaymentService $payments;

    protected function setUp(): void
    {
        parent::setUp();

        // Freeze the clock on the hour. Reservations must start on the hour,
        // so a booking "exactly 24 hours out" built from a wall clock at 10:37
        // would actually land 23h23m out and quietly test the wrong tier.
        $this->travelTo(CarbonImmutable::today()->setHour(9));

        $this->buildProperty(roomCount: 2, bufferMinutes: 60);

        $this->calculator = app(RefundCalculator::class);
        $this->reservations = app(ReservationService::class);
        $this->payments = app(PaymentService::class);
    }

    /** A confirmed, fully paid booking starting $hoursAhead from now. */
    private function paidBooking(int $hoursAhead, int $totalCents = 200000, int $roomIndex = 0): Reservation
    {
        $start = CarbonImmutable::now()->addHours($hoursAhead)->startOfHour();

        $reservation = Reservation::factory()
            ->forWindow($this->room($roomIndex), $start, 6)
            ->create([
                'user_id' => $this->customer()->id,
                'policy_version_id' => $this->policy->id,
                'total_cents' => $totalCents,
                'package_price_cents' => $totalCents,
                'balance_due_cents' => $totalCents,
            ]);

        $this->payments->recordFaceToFace(
            reservation: $reservation,
            amountCents: $totalCents,
            method: PaymentMethod::Cash,
            staff: $this->staffMember(),
            kind: PaymentKind::Full,
        );

        return $reservation->refresh();
    }

    public function test_more_than_twenty_four_hours_out_is_a_full_refund(): void
    {
        $reservation = $this->paidBooking(hoursAhead: 48);

        $outcome = $this->calculator->forCancellation($reservation);

        $this->assertSame(RefundTier::Full, $outcome->tier);
        $this->assertSame(200000, $outcome->refundableCents);
        $this->assertSame(0, $outcome->forfeitedCents);
    }

    public function test_between_six_and_twenty_four_hours_forfeits_the_downpayment(): void
    {
        $reservation = $this->paidBooking(hoursAhead: 12);

        $outcome = $this->calculator->forCancellation($reservation);

        // 50% of 200000 is kept, the rest returned.
        $this->assertSame(RefundTier::DownpaymentForfeited, $outcome->tier);
        $this->assertSame(100000, $outcome->forfeitedCents);
        $this->assertSame(100000, $outcome->refundableCents);
    }

    public function test_under_six_hours_returns_nothing(): void
    {
        $reservation = $this->paidBooking(hoursAhead: 3);

        $outcome = $this->calculator->forCancellation($reservation);

        $this->assertSame(RefundTier::None, $outcome->tier);
        $this->assertSame(0, $outcome->refundableCents);
        $this->assertSame(200000, $outcome->forfeitedCents);
    }

    /**
     * The boundaries are inclusive at the lower edge of the middle tier, so a
     * guest is never worse off for landing exactly on one.
     */
    public function test_the_tier_boundaries_behave_as_documented(): void
    {
        // Two bookings on different rooms rather than one reused: payments
        // reference reservations, so nothing here is deletable -- which is
        // itself the intended behaviour.
        $this->assertSame(
            RefundTier::DownpaymentForfeited,
            $this->calculator->forCancellation($this->paidBooking(24))->tier,
            'Exactly 24 hours out is the partial tier, not the full one.',
        );

        $this->assertSame(
            RefundTier::DownpaymentForfeited,
            $this->calculator->forCancellation($this->paidBooking(6, roomIndex: 1))->tier,
            'Exactly 6 hours out is still the partial tier.',
        );
    }

    public function test_a_guest_who_only_paid_a_downpayment_gets_nothing_back_in_the_partial_tier(): void
    {
        $start = CarbonImmutable::now()->addHours(12)->startOfHour();

        $reservation = Reservation::factory()
            ->forWindow($this->room(), $start, 6)
            ->create([
                'user_id' => $this->customer()->id,
                'policy_version_id' => $this->policy->id,
                'total_cents' => 200000,
                'package_price_cents' => 200000,
                'balance_due_cents' => 200000,
            ]);

        $this->payments->recordFaceToFace(
            $reservation, 100000, PaymentMethod::Cash, $this->staffMember(), PaymentKind::Downpayment,
        );

        $outcome = $this->calculator->forCancellation($reservation->refresh());

        $this->assertSame(RefundTier::DownpaymentForfeited, $outcome->tier);
        $this->assertSame(100000, $outcome->forfeitedCents);
        $this->assertSame(0, $outcome->refundableCents, 'They paid exactly the downpayment, so nothing is left to return.');
    }

    public function test_a_hotel_cancellation_refunds_in_full_whatever_the_timing(): void
    {
        $reservation = $this->paidBooking(hoursAhead: 1);

        $outcome = $this->calculator->forCancellation($reservation, CancellationInitiator::Hotel);

        $this->assertSame(RefundTier::Full, $outcome->tier);
        $this->assertSame(200000, $outcome->refundableCents);
    }

    public function test_a_no_show_forfeits_everything(): void
    {
        $reservation = $this->paidBooking(hoursAhead: 1);

        $outcome = $this->calculator->forNoShow($reservation);

        $this->assertSame(RefundTier::None, $outcome->tier);
        $this->assertSame(0, $outcome->refundableCents);
        $this->assertSame(200000, $outcome->forfeitedCents);
    }

    public function test_nothing_paid_means_nothing_to_refund(): void
    {
        $reservation = Reservation::factory()
            ->forWindow($this->room(), CarbonImmutable::now()->addHours(48)->startOfHour(), 6)
            ->create(['policy_version_id' => $this->policy->id]);

        $outcome = $this->calculator->forCancellation($reservation);

        $this->assertSame(0, $outcome->refundableCents);
        $this->assertStringContainsString('nothing to refund', $outcome->explanation);
    }

    public function test_cancelling_writes_the_refund_and_releases_the_room(): void
    {
        $reservation = $this->paidBooking(hoursAhead: 48);

        $outcome = $this->reservations->cancel(
            reservation: $reservation,
            actor: $reservation->customer,
            reason: 'Plans changed',
        );

        $reservation->refresh();

        $this->assertSame(ReservationStatus::Cancelled, $reservation->status);
        $this->assertSame(200000, $outcome->refundableCents);
        $this->assertSame(200000, $reservation->amount_refunded_cents);
        $this->assertSame(CancellationInitiator::Customer, $reservation->cancellation_initiator);

        $this->assertDatabaseHas('refunds', [
            'reservation_id' => $reservation->id,
            'amount_cents' => 200000,
            'tier_applied' => RefundTier::Full->value,
            'status' => 'succeeded',
        ]);

        // Freed for someone else.
        $this->assertTrue(
            app(AvailabilityService::class)->isRoomFree(
                $reservation->room_id,
                BookingWindow::fromReservation($reservation),
            ),
        );
    }

    /**
     * The point of versioning the policy: an admin tightening the terms today
     * cannot change what a guest who booked yesterday is owed.
     */
    public function test_a_booking_keeps_the_terms_it_was_made_under(): void
    {
        $reservation = $this->paidBooking(hoursAhead: 12);

        // Tighten the policy after the fact: 50% becomes 90%, and the full
        // refund window shrinks. The reservation still points at version 1.
        app(PolicyService::class)->publish([
            'downpayment_percent' => 90,
            'full_refund_hours_before' => 6,
            'partial_refund_hours_before' => 2,
        ], $this->admin());

        $outcome = $this->calculator->forCancellation($reservation->refresh());

        $this->assertSame(
            RefundTier::DownpaymentForfeited,
            $outcome->tier,
            'Under the new policy 12 hours out would be a full refund; the old terms must still apply.',
        );
        $this->assertSame(
            100000,
            $outcome->forfeitedCents,
            'The forfeited amount must use the 50% the guest agreed to, not the new 90%.',
        );
    }

    public function test_the_cancellation_reason_and_actor_are_recorded(): void
    {
        $reservation = $this->paidBooking(hoursAhead: 48);
        $staff = $this->staffMember();

        $this->reservations->cancel(
            reservation: $reservation,
            initiator: CancellationInitiator::Hotel,
            actor: $staff,
            reason: 'Air conditioning failure',
        );

        $reservation->refresh();

        $this->assertSame($staff->id, $reservation->cancelled_by_user_id);
        $this->assertSame('Air conditioning failure', $reservation->cancellation_reason);
        $this->assertNotNull($reservation->cancelled_at);

        $this->assertDatabaseHas('reservation_status_transitions', [
            'reservation_id' => $reservation->id,
            'from_status' => ReservationStatus::Confirmed->value,
            'to_status' => ReservationStatus::Cancelled->value,
            'actor_user_id' => $staff->id,
        ]);
    }
}
