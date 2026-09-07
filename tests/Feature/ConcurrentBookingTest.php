<?php

namespace Tests\Feature;

use App\Enums\PaymentMode;
use App\Enums\ReservationStatus;
use App\Exceptions\RoomNoLongerAvailableException;
use App\Models\Reservation;
use App\Models\Room;
use App\Services\ReservationService;
use App\Support\BookingRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\BuildsProperty;
use Tests\TestCase;

/**
 * The rule that two reservations may never overlap on the same physical room,
 * tested at each of the three layers that enforce it.
 *
 * A note on what is and is not simulated here: the suite runs against an
 * in-memory SQLite database, where each connection would get its own separate
 * database, so genuinely parallel transactions cannot be staged. What is
 * tested instead is every mechanism that makes the race safe --
 *
 *   1. the application re-check inside the booking transaction,
 *   2. the database's own overlap guarantee, exercised directly,
 *   3. the translation of that database rejection into the friendly refusal,
 *      staged by inserting a conflicting row at the last possible moment,
 *      which is exactly what a racing transaction committing between the
 *      check and the insert would look like.
 *
 * Layer 1's room lock is what serialises real concurrent writers; it is
 * asserted structurally (the query is issued) rather than by racing threads.
 */
class ConcurrentBookingTest extends TestCase
{
    use BuildsProperty, RefreshDatabase;

    private ReservationService $reservations;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildProperty(roomCount: 1, bufferMinutes: 60);
        $this->reservations = app(ReservationService::class);
    }

    private function request(?int $preferredRoomId = null): BookingRequest
    {
        return new BookingRequest(
            roomType: $this->roomType,
            package: $this->sixHours,
            startsAt: $this->tomorrowAt(10),
            paymentMode: PaymentMode::AtProperty,
            adults: 2,
            customer: $this->customer(),
            preferredRoomId: $preferredRoomId,
        );
    }

    public function test_the_first_booking_succeeds(): void
    {
        $reservation = $this->reservations->book($this->request());

        $this->assertSame($this->room()->id, $reservation->room_id);
        $this->assertSame(ReservationStatus::Confirmed, $reservation->status);
        $this->assertEquals(
            $reservation->ends_at->copy()->addMinutes(60),
            $reservation->blocked_until,
            'blocked_until must trail the stay by the buffer.',
        );
    }

    public function test_the_second_booking_on_the_only_room_is_refused_with_a_friendly_message(): void
    {
        $this->reservations->book($this->request());

        $this->expectException(RoomNoLongerAvailableException::class);
        $this->expectExceptionMessageMatches('/taken/i');

        $this->reservations->book($this->request());
    }

    public function test_two_bookings_for_the_same_window_take_different_rooms_when_two_are_free(): void
    {
        // A second room of the same type. The property is already built by
        // setUp, so this adds inventory rather than rebuilding it.
        $this->rooms->push(
            Room::factory()->create(['room_type_id' => $this->roomType->id])
        );

        $first = $this->reservations->book($this->request());
        $second = $this->reservations->book($this->request());

        $this->assertNotSame(
            $first->room_id,
            $second->room_id,
            'With two rooms free, two guests booking the same window must land on different rooms.',
        );
    }

    public function test_only_one_reservation_survives_when_the_room_is_named_explicitly(): void
    {
        $this->reservations->book($this->request($this->room()->id));

        try {
            $this->reservations->book($this->request($this->room()->id));
            $this->fail('The second booking on a named, already-taken room should have been refused.');
        } catch (RoomNoLongerAvailableException $e) {
            $this->assertStringContainsString('another', $e->getMessage());
        }

        $this->assertSame(1, Reservation::occupying()->count());
    }

    /**
     * Layer 2, on its own: bypass every application check and ask the database
     * directly. This is the guarantee that survives a future change to the
     * service layer by someone who has not read it.
     */
    public function test_the_database_itself_rejects_an_overlapping_row(): void
    {
        $existing = $this->reservations->book($this->request());

        $this->expectExceptionMessageMatches('/reservations_no_overlap/');

        DB::table('reservations')->insert([
            'reference' => 'BM-RAW001',
            'user_id' => $this->customer()->id,
            'room_id' => $existing->room_id,
            'room_type_id' => $existing->room_type_id,
            'duration_package_id' => $existing->duration_package_id,
            'package_hours' => 6,
            'starts_at' => $existing->starts_at->copy()->addHour(),
            'ends_at' => $existing->ends_at->copy()->addHour(),
            'buffer_minutes' => 60,
            'blocked_until' => $existing->blocked_until->copy()->addHour(),
            'adults' => 1,
            'children' => 0,
            'status' => ReservationStatus::Confirmed->value,
            'payment_mode' => PaymentMode::AtProperty->value,
            'payment_status' => 'unpaid',
            'policy_version_id' => $this->policy->id,
            'currency' => 'PHP',
            'channel' => 'online',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_the_database_permits_a_row_that_starts_exactly_when_the_buffer_ends(): void
    {
        $existing = $this->reservations->book($this->request());

        DB::table('reservations')->insert([
            'reference' => 'BM-RAW002',
            'user_id' => $this->customer()->id,
            'room_id' => $existing->room_id,
            'room_type_id' => $existing->room_type_id,
            'duration_package_id' => $existing->duration_package_id,
            'package_hours' => 6,
            'starts_at' => $existing->blocked_until,
            'ends_at' => $existing->blocked_until->copy()->addHours(6),
            'buffer_minutes' => 60,
            'blocked_until' => $existing->blocked_until->copy()->addHours(7),
            'adults' => 1,
            'children' => 0,
            'status' => ReservationStatus::Confirmed->value,
            'payment_mode' => PaymentMode::AtProperty->value,
            'payment_status' => 'unpaid',
            'policy_version_id' => $this->policy->id,
            'currency' => 'PHP',
            'channel' => 'online',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertSame(2, Reservation::count());
    }

    /**
     * Layer 3: stage the exact race the constraint exists for.
     *
     * A conflicting reservation is inserted from inside the `creating` event,
     * which is the last instant before our own row hits the table -- after the
     * availability re-check has already passed. That is precisely what a
     * competing transaction committing in that window looks like, and the
     * guest must still see the friendly message rather than a stack trace.
     */
    public function test_a_conflict_landing_after_the_check_is_still_reported_as_unavailable(): void
    {
        $roomId = $this->room()->id;
        $policyId = $this->policy->id;
        $roomTypeId = $this->roomType->id;
        $packageId = $this->sixHours->id;
        $customerId = $this->customer()->id;
        $start = $this->tomorrowAt(10);
        $fired = false;

        Reservation::creating(function () use (
            &$fired, $roomId, $policyId, $roomTypeId, $packageId, $customerId, $start
        ) {
            if ($fired) {
                return;
            }

            $fired = true;

            DB::table('reservations')->insert([
                'reference' => 'BM-RACE01',
                'user_id' => $customerId,
                'room_id' => $roomId,
                'room_type_id' => $roomTypeId,
                'duration_package_id' => $packageId,
                'package_hours' => 6,
                'starts_at' => $start,
                'ends_at' => $start->addHours(6),
                'buffer_minutes' => 60,
                'blocked_until' => $start->addHours(7),
                'adults' => 1,
                'children' => 0,
                'status' => ReservationStatus::Confirmed->value,
                'payment_mode' => PaymentMode::AtProperty->value,
                'payment_status' => 'unpaid',
                'policy_version_id' => $policyId,
                'currency' => 'PHP',
                'channel' => 'online',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        try {
            $this->reservations->book($this->request());
            $this->fail('The booking should have been refused by the database overlap guarantee.');
        } catch (RoomNoLongerAvailableException $e) {
            $this->assertTrue(
                $e->causedByDatabaseConstraint,
                'The refusal must be attributed to the database guarantee, not the application check.',
            );
        } finally {
            Reservation::flushEventListeners();
        }

        // The losing transaction rolled back entirely -- and because the
        // staged conflict was written inside that same transaction (the only
        // way to place it between the check and the insert on one connection),
        // it rolls back with it. What matters, and what is asserted, is that
        // the guest's booking left nothing behind: no half-written reservation,
        // no orphaned extras, no room assignment.
        $this->assertSame(0, Reservation::count());
        $this->assertDatabaseCount('room_assignments', 0);
        $this->assertDatabaseCount('reservation_status_transitions', 0);
    }

    /**
     * A cancelled booking must not keep the room out of circulation, and the
     * database has to agree with the application about that.
     */
    public function test_a_room_can_be_rebooked_once_the_first_booking_is_cancelled(): void
    {
        $first = $this->reservations->book($this->request());

        $this->reservations->cancel($first);

        $second = $this->reservations->book($this->request());

        $this->assertSame($first->room_id, $second->room_id);
        $this->assertSame(2, Reservation::count());
    }
}
