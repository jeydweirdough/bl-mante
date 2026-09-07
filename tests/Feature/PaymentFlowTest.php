<?php

namespace Tests\Feature;

use App\Enums\PaymentKind;
use App\Enums\PaymentMethod;
use App\Enums\PaymentMode;
use App\Enums\PaymentStatus;
use App\Enums\RefundReason;
use App\Enums\RefundStatus;
use App\Enums\RefundTier;
use App\Enums\ReservationPaymentStatus;
use App\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Payments\Gateways\MockPaymentGateway;
use App\Payments\PaymentGateway;
use App\Services\PaymentService;
use App\Services\ReservationService;
use App\Support\BookingRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\Support\BuildsProperty;
use Tests\TestCase;

/**
 * Payment, both paths.
 *
 * Nothing here names a provider: everything goes through the PaymentGateway
 * interface, which is the point of the boundary. The mock's simulate* methods
 * stand in for the guest pressing a button on the provider's own page.
 */
class PaymentFlowTest extends TestCase
{
    use BuildsProperty, RefreshDatabase;

    private PaymentService $payments;

    private ReservationService $reservations;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildProperty(roomCount: 2, bufferMinutes: 60);

        $this->payments = app(PaymentService::class);
        $this->reservations = app(ReservationService::class);
    }

    private function booking(PaymentMode $mode = PaymentMode::Online): Reservation
    {
        return $this->reservations->book(new BookingRequest(
            roomType: $this->roomType,
            package: $this->sixHours,
            startsAt: $this->tomorrowAt(10),
            paymentMode: $mode,
            adults: 2,
            customer: $this->customer(),
        ));
    }

    // -----------------------------------------------------------------------
    // Which path takes a hold
    // -----------------------------------------------------------------------

    public function test_an_online_booking_is_held_pending_payment(): void
    {
        $reservation = $this->booking(PaymentMode::Online);

        $this->assertSame(ReservationStatus::Pending, $reservation->status);
        $this->assertNotNull($reservation->hold_expires_at);
        $this->assertEqualsWithDelta(
            now()->addMinutes($this->policy->unpaid_hold_minutes)->timestamp,
            $reservation->hold_expires_at->timestamp,
            5,
        );
    }

    /**
     * An at-property booking has no payment by definition, so holding it to
     * the unpaid window would expire every one of them.
     */
    public function test_an_at_property_booking_is_confirmed_immediately_with_no_hold(): void
    {
        $reservation = $this->booking(PaymentMode::AtProperty);

        $this->assertSame(ReservationStatus::Confirmed, $reservation->status);
        $this->assertNull($reservation->hold_expires_at);
        $this->assertNotNull($reservation->confirmed_at);
    }

    // -----------------------------------------------------------------------
    // Online
    // -----------------------------------------------------------------------

    public function test_a_settled_online_payment_confirms_the_booking(): void
    {
        $reservation = $this->booking();

        ['payment' => $payment] = $this->payments->startOnlineCheckout(
            reservation: $reservation,
            kind: PaymentKind::Full,
            amountCents: $reservation->total_cents,
            returnUrl: 'http://localhost/return',
            cancelUrl: 'http://localhost/cancel',
        );

        // Arriving at the gateway is not payment.
        $this->assertSame(PaymentStatus::Pending, $payment->status);
        $this->assertSame(ReservationStatus::Pending, $reservation->refresh()->status);

        $this->gateway()->simulateApproval($payment->provider_reference);
        $this->payments->syncFromGateway($payment);

        $reservation->refresh();

        $this->assertSame(PaymentStatus::Succeeded, $payment->refresh()->status);
        $this->assertSame(ReservationStatus::Confirmed, $reservation->status);
        $this->assertSame(ReservationPaymentStatus::PaidInFull, $reservation->payment_status);
        $this->assertSame(0, $reservation->balance_due_cents);
        $this->assertNull($reservation->hold_expires_at, 'Confirming must clear the hold.');
    }

    public function test_a_cancelled_checkout_leaves_the_booking_pending(): void
    {
        $reservation = $this->booking();

        ['payment' => $payment] = $this->payments->startOnlineCheckout(
            $reservation, PaymentKind::Full, $reservation->total_cents,
            'http://localhost/return', 'http://localhost/cancel',
        );

        $this->gateway()->simulateCancellation($payment->provider_reference);
        $this->payments->syncFromGateway($payment);

        $this->assertSame(PaymentStatus::Cancelled, $payment->refresh()->status);
        $this->assertSame(ReservationStatus::Pending, $reservation->refresh()->status);
        $this->assertSame(0, $reservation->amount_paid_cents);
    }

    public function test_a_downpayment_confirms_the_booking_and_leaves_a_balance(): void
    {
        $reservation = $this->booking();
        $downpayment = $reservation->downpaymentDueCents();

        ['payment' => $payment] = $this->payments->startOnlineCheckout(
            $reservation, PaymentKind::Downpayment, $downpayment,
            'http://localhost/return', 'http://localhost/cancel',
        );

        $this->gateway()->simulateApproval($payment->provider_reference);
        $this->payments->syncFromGateway($payment);

        $reservation->refresh();

        $this->assertSame(ReservationStatus::Confirmed, $reservation->status);
        $this->assertSame(ReservationPaymentStatus::PartiallyPaid, $reservation->payment_status);
        $this->assertSame($reservation->total_cents - $downpayment, $reservation->balance_due_cents);
    }

    /**
     * A guest who reloads the payment page, or double-submits, must not end up
     * with two charges.
     */
    public function test_reopening_checkout_reuses_the_open_session(): void
    {
        $reservation = $this->booking();

        ['payment' => $first] = $this->payments->startOnlineCheckout(
            $reservation, PaymentKind::Full, $reservation->total_cents,
            'http://localhost/return', 'http://localhost/cancel',
        );

        ['payment' => $second] = $this->payments->startOnlineCheckout(
            $reservation, PaymentKind::Full, $reservation->total_cents,
            'http://localhost/return', 'http://localhost/cancel',
        );

        $this->assertTrue($first->is($second));
        $this->assertSame(1, $reservation->payments()->count());
    }

    public function test_syncing_a_settled_payment_twice_does_not_double_count_it(): void
    {
        $reservation = $this->booking();

        ['payment' => $payment] = $this->payments->startOnlineCheckout(
            $reservation, PaymentKind::Full, $reservation->total_cents,
            'http://localhost/return', 'http://localhost/cancel',
        );

        $this->gateway()->simulateApproval($payment->provider_reference);

        $this->payments->syncFromGateway($payment);
        $this->payments->syncFromGateway($payment->refresh());

        $reservation->refresh();

        $this->assertSame($reservation->total_cents, $reservation->amount_paid_cents);
        $this->assertSame(0, $reservation->balance_due_cents);
    }

    // -----------------------------------------------------------------------
    // At the property
    // -----------------------------------------------------------------------

    public function test_a_face_to_face_payment_is_attributed_to_the_staff_member(): void
    {
        $reservation = $this->booking(PaymentMode::AtProperty);
        $staff = $this->staffMember();

        $payment = $this->payments->recordFaceToFace(
            reservation: $reservation,
            amountCents: 50000,
            method: PaymentMethod::Cash,
            staff: $staff,
            kind: PaymentKind::Balance,
        );

        $this->assertSame($staff->id, $payment->recorded_by_user_id);
        $this->assertSame(PaymentStatus::Succeeded, $payment->status);
        $this->assertNull($payment->provider_reference, 'Counter payments never touch the gateway.');

        $this->assertSame(50000, $reservation->refresh()->amount_paid_cents);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'payment.recorded_at_property',
            'actor_user_id' => $staff->id,
        ]);
    }

    public function test_a_face_to_face_payment_cannot_use_an_online_method(): void
    {
        $reservation = $this->booking(PaymentMode::AtProperty);

        $this->expectException(InvalidArgumentException::class);

        $this->payments->recordFaceToFace(
            $reservation, 50000, PaymentMethod::MockGateway, $this->staffMember(), PaymentKind::Balance,
        );
    }

    public function test_paying_at_the_desk_confirms_a_pending_online_booking(): void
    {
        $reservation = $this->booking(PaymentMode::Online);

        $this->assertSame(ReservationStatus::Pending, $reservation->status);

        $this->payments->recordFaceToFace(
            $reservation, $reservation->total_cents, PaymentMethod::Cash, $this->staffMember(), PaymentKind::Full,
        );

        $this->assertSame(ReservationStatus::Confirmed, $reservation->refresh()->status);
    }

    // -----------------------------------------------------------------------
    // The boundary itself
    // -----------------------------------------------------------------------

    public function test_the_gateway_is_resolved_from_configuration(): void
    {
        $this->assertInstanceOf(MockPaymentGateway::class, app(PaymentGateway::class));
        $this->assertSame('mock', app(PaymentGateway::class)->name());
    }

    public function test_a_refund_goes_back_through_the_gateway_for_online_payments(): void
    {
        $reservation = $this->booking();

        ['payment' => $payment] = $this->payments->startOnlineCheckout(
            $reservation, PaymentKind::Full, $reservation->total_cents,
            'http://localhost/return', 'http://localhost/cancel',
        );

        $this->gateway()->simulateApproval($payment->provider_reference);
        $this->payments->syncFromGateway($payment);

        $refunds = $this->payments->refund(
            reservation: $reservation->refresh(),
            amountCents: $reservation->total_cents,
            reason: RefundReason::CustomerCancellation,
            tier: RefundTier::Full,
        );

        $this->assertCount(1, $refunds);
        $this->assertSame(RefundStatus::Succeeded, $refunds[0]->status);
        $this->assertNotNull($refunds[0]->provider_reference);
        $this->assertSame($reservation->total_cents, $reservation->refresh()->amount_refunded_cents);
    }

    public function test_a_refund_cannot_exceed_what_was_taken(): void
    {
        $reservation = $this->booking(PaymentMode::AtProperty);

        $this->payments->recordFaceToFace(
            $reservation, 100000, PaymentMethod::Cash, $this->staffMember(), PaymentKind::Downpayment,
        );

        // Ask for more than was ever collected: only what exists comes back.
        $refunds = $this->payments->refund(
            $reservation->refresh(), 500000, RefundReason::Goodwill,
        );

        $this->assertSame(100000, (int) collect($refunds)->sum('amount_cents'));
    }

    private function gateway(): MockPaymentGateway
    {
        return app(PaymentGateway::class);
    }
}
