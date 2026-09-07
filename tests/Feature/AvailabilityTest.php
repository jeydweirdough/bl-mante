<?php

namespace Tests\Feature;

use App\Enums\ReservationStatus;
use App\Enums\RoomStatus;
use App\Models\Reservation;
use App\Services\AvailabilityService;
use App\Support\BookingWindow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsProperty;
use Tests\TestCase;

/**
 * The turnover buffer and the shape of the overlap rule.
 */
class AvailabilityTest extends TestCase
{
    use BuildsProperty, RefreshDatabase;

    private AvailabilityService $availability;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildProperty(roomCount: 1, bufferMinutes: 60);
        $this->availability = app(AvailabilityService::class);
    }

    public function test_a_free_room_is_offered(): void
    {
        $window = BookingWindow::make($this->tomorrowAt(10), 6, 60);

        $this->assertTrue($this->availability->isRoomFree($this->room()->id, $window));
        $this->assertCount(1, $this->availability->freeRooms($window));
    }

    public function test_an_overlapping_reservation_removes_the_room(): void
    {
        Reservation::factory()
            ->forWindow($this->room(), $this->tomorrowAt(10), 6)
            ->create();

        // 12:00 falls inside the 10:00-16:00 stay.
        $window = BookingWindow::make($this->tomorrowAt(12), 6, 60);

        $this->assertFalse($this->availability->isRoomFree($this->room()->id, $window));
        $this->assertTrue($this->availability->freeRooms($window)->isEmpty());
    }

    /**
     * The buffer is the whole point of blocked_until: the stay ends at 16:00
     * but the room is not sellable until 17:00.
     */
    public function test_the_turnover_buffer_blocks_the_hour_after_a_stay(): void
    {
        Reservation::factory()
            ->forWindow($this->room(), $this->tomorrowAt(10), 6, bufferMinutes: 60)
            ->create();

        $duringBuffer = BookingWindow::make($this->tomorrowAt(16), 6, 60);
        $this->assertFalse(
            $this->availability->isRoomFree($this->room()->id, $duringBuffer),
            'A booking starting the moment the previous stay ends must be refused: the room is still in turnover.',
        );

        $afterBuffer = BookingWindow::make($this->tomorrowAt(17), 6, 60);
        $this->assertTrue(
            $this->availability->isRoomFree($this->room()->id, $afterBuffer),
            'The room is free again the instant the buffer expires.',
        );
    }

    /**
     * The comparison is half-open, so the two directions of the rule are not
     * accidentally asymmetric.
     */
    public function test_a_new_stay_may_not_run_into_an_existing_one(): void
    {
        Reservation::factory()
            ->forWindow($this->room(), $this->tomorrowAt(17), 6, bufferMinutes: 60)
            ->create();

        // 10:00-16:00 plus an hour of buffer ends exactly at 17:00, which is
        // the instant the existing booking starts. Half-open: no collision.
        $upToTheBoundary = BookingWindow::make($this->tomorrowAt(10), 6, 60);
        $this->assertTrue($this->availability->isRoomFree($this->room()->id, $upToTheBoundary));

        // One hour later and the buffer would run into the existing stay.
        $oneHourTooLate = BookingWindow::make($this->tomorrowAt(11), 6, 60);
        $this->assertFalse($this->availability->isRoomFree($this->room()->id, $oneHourTooLate));
    }

    public function test_a_zero_buffer_allows_back_to_back_stays(): void
    {
        Reservation::factory()
            ->forWindow($this->room(), $this->tomorrowAt(10), 6, bufferMinutes: 0)
            ->create();

        $window = BookingWindow::make($this->tomorrowAt(16), 6, 0);

        $this->assertTrue(
            $this->availability->isRoomFree($this->room()->id, $window),
            'With no buffer configured, a stay may start the moment the previous one ends.',
        );
    }

    /**
     * Cancelled, expired, no-show and checked-out bookings keep their room and
     * datetimes for history, but must not hold the slot.
     */
    public function test_terminal_reservations_release_the_room(): void
    {
        $window = BookingWindow::make($this->tomorrowAt(10), 6, 60);

        foreach ([ReservationStatus::Cancelled, ReservationStatus::Expired, ReservationStatus::NoShow] as $status) {
            Reservation::query()->delete();

            Reservation::factory()
                ->forWindow($this->room(), $this->tomorrowAt(10), 6)
                ->status($status)
                ->create();

            $this->assertTrue(
                $this->availability->isRoomFree($this->room()->id, $window),
                "A {$status->value} reservation must not block its room.",
            );
        }
    }

    public function test_each_reservation_keeps_its_own_buffer(): void
    {
        // Booked when the buffer was two hours; the policy has since changed.
        Reservation::factory()
            ->forWindow($this->room(), $this->tomorrowAt(10), 6, bufferMinutes: 120)
            ->create();

        $this->assertFalse(
            $this->availability->isRoomFree($this->room()->id, BookingWindow::make($this->tomorrowAt(17), 6, 60)),
            'The existing booking was made under a two-hour buffer and keeps it.',
        );

        $this->assertTrue(
            $this->availability->isRoomFree($this->room()->id, BookingWindow::make($this->tomorrowAt(18), 6, 60)),
        );
    }

    public function test_rooms_taken_off_sale_are_never_offered(): void
    {
        $this->room()->update(['is_bookable' => false]);

        $window = BookingWindow::make($this->tomorrowAt(10), 6, 60);

        $this->assertTrue($this->availability->freeRooms($window)->isEmpty());
    }

    public function test_out_of_service_rooms_are_never_offered(): void
    {
        $this->room()->update(['status' => RoomStatus::OutOfService]);

        $window = BookingWindow::make($this->tomorrowAt(10), 6, 60);

        $this->assertTrue($this->availability->freeRooms($window)->isEmpty());
    }

    /**
     * Housekeeping state answers "can somebody walk in now", not "is this
     * window free". A room being cleaned today is still sellable next week.
     */
    public function test_a_room_being_cleaned_is_still_offered_for_a_later_window(): void
    {
        $this->room()->update(['status' => RoomStatus::Cleaning]);

        $soon = BookingWindow::make(now()->addMinutes(30)->startOfHour(), 6, 60);
        $later = BookingWindow::make($this->tomorrowAt(10), 6, 60);

        $this->assertTrue(
            $this->availability->freeRooms($soon)->isEmpty(),
            'A room mid-clean should not be offered for a window starting imminently.',
        );

        $this->assertCount(
            1,
            $this->availability->freeRooms($later),
            'It will have been cleaned long before tomorrow, so it must still appear.',
        );
    }

    public function test_a_room_type_too_small_for_the_party_is_not_offered(): void
    {
        $window = BookingWindow::make($this->tomorrowAt(10), 6, 60);

        $this->assertCount(1, $this->availability->freeRooms($window, partySize: 4));
        $this->assertTrue($this->availability->freeRooms($window, partySize: 5)->isEmpty());
    }
}
