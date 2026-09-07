<?php

namespace Tests\Feature;

use App\Enums\PaymentMode;
use App\Enums\ReservationChannel;
use App\Exceptions\DuplicateReservationException;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\User;
use App\Services\AvailabilityService;
use App\Services\ReservationService;
use App\Support\BookingRequest;
use App\Support\BookingWindow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsProperty;
use Tests\TestCase;

/**
 * A customer may hold several bookings on one day, but not two that overlap.
 *
 * The distinction that matters: the customer rule compares *stay* intervals,
 * while the room rule compares stay-plus-buffer. The buffer belongs to the
 * room, not the guest.
 */
class DuplicateBookingTest extends TestCase
{
    use BuildsProperty, RefreshDatabase;

    private ReservationService $reservations;

    private User $guest;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildProperty(roomCount: 4, bufferMinutes: 60);
        $this->reservations = app(ReservationService::class);
        $this->guest = $this->customer();
    }

    private function bookAt(int $hour, ?User $customer = null): Reservation
    {
        return $this->reservations->book(new BookingRequest(
            roomType: $this->roomType,
            package: $this->sixHours,
            startsAt: $this->tomorrowAt($hour),
            paymentMode: PaymentMode::AtProperty,
            adults: 1,
            customer: $customer ?? $this->guest,
        ));
    }

    public function test_a_customer_may_hold_two_non_overlapping_bookings_on_the_same_day(): void
    {
        $morning = $this->bookAt(6);   // 06:00-12:00
        $evening = $this->bookAt(12);  // 12:00-18:00, adjacent but not overlapping

        $this->assertNotSame($morning->id, $evening->id);
        $this->assertSame(2, Reservation::where('user_id', $this->guest->id)->count());

        $this->assertNotSame(
            $morning->room_id,
            $evening->room_id,
            'The first room is still in turnover at 12:00, so the second booking must take another room.',
        );
    }

    public function test_a_customer_cannot_hold_two_overlapping_bookings(): void
    {
        $first = $this->bookAt(10);

        try {
            $this->bookAt(12);
            $this->fail('An overlapping booking for the same customer should have been refused.');
        } catch (DuplicateReservationException $e) {
            $this->assertTrue($e->conflictingReservation->is($first));

            // The requirement is explicit that the message names the booking
            // that is in the way.
            $this->assertStringContainsString($first->reference, $e->getMessage());
            $this->assertStringContainsString('overlap', $e->getMessage());
        }

        $this->assertSame(1, Reservation::count());
    }

    public function test_different_customers_may_book_overlapping_windows(): void
    {
        $this->bookAt(10);
        $other = $this->bookAt(10, $this->customer());

        $this->assertSame(2, Reservation::count());
        $this->assertNotSame($this->guest->id, $other->user_id);
    }

    /**
     * The buffer is a property of the room. Two stays that merely touch are
     * fine for the guest even though the first room stays blocked.
     */
    public function test_the_customer_rule_ignores_the_turnover_buffer(): void
    {
        $this->bookAt(6);

        // 12:00 sits inside the first room's buffer (11:00-13:00 blocked from
        // a 06:00-12:00 stay) but not inside the guest's stay.
        $second = $this->bookAt(12);

        $this->assertNotNull($second->id);
    }

    public function test_a_cancelled_booking_no_longer_blocks_the_customer(): void
    {
        $first = $this->bookAt(10);
        $this->reservations->cancel($first);

        $second = $this->bookAt(10);

        $this->assertSame(2, Reservation::count());
        $this->assertNotNull($second->id);
    }

    /**
     * Walk-in guests have no account, so there is no identity to compare. The
     * desk gets an advisory match instead of a hard block.
     */
    public function test_walk_in_guests_are_not_subject_to_the_duplicate_rule(): void
    {
        $staff = $this->staffMember();

        $make = fn () => $this->reservations->book(new BookingRequest(
            roomType: $this->roomType,
            package: $this->sixHours,
            startsAt: $this->tomorrowAt(10),
            paymentMode: PaymentMode::AtProperty,
            adults: 1,
            guestName: 'Juan dela Cruz',
            guestPhone: '+63 917 555 0000',
            channel: ReservationChannel::WalkIn,
            createdBy: $staff,
        ));

        $first = $make();
        $second = $make();

        $this->assertSame(2, Reservation::count());
        $this->assertNotSame($first->room_id, $second->room_id);

        // But the desk is warned.
        $similar = app(AvailabilityService::class)->similarGuestReservations(
            'Juan dela Cruz',
            '+63 917 555 0000',
            BookingWindow::make($this->tomorrowAt(10), 6, 60),
        );

        $this->assertCount(2, $similar);
    }

    public function test_the_duplicate_check_runs_across_different_rooms(): void
    {
        // Explicitly target two different rooms, to prove the rule is about
        // the customer rather than about a room collision.
        $this->reservations->book(new BookingRequest(
            roomType: $this->roomType,
            package: $this->sixHours,
            startsAt: $this->tomorrowAt(10),
            paymentMode: PaymentMode::AtProperty,
            customer: $this->guest,
            preferredRoomId: $this->room(0)->id,
        ));

        $this->expectException(DuplicateReservationException::class);

        $this->reservations->book(new BookingRequest(
            roomType: $this->roomType,
            package: $this->sixHours,
            startsAt: $this->tomorrowAt(10),
            paymentMode: PaymentMode::AtProperty,
            customer: $this->guest,
            preferredRoomId: $this->room(1)->id,
        ));
    }
}
