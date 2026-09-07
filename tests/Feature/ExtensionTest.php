<?php

namespace Tests\Feature;

use App\Enums\ExtensionStatus;
use App\Enums\ReservationStatus;
use App\Exceptions\ExtensionNotAvailableException;
use App\Models\Reservation;
use App\Services\ReservationService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsProperty;
use Tests\TestCase;

/**
 * Extensions are granted only if the room is free for the extra hours plus
 * the trailing buffer. The system never relocates another guest to make one
 * fit -- it refuses instead.
 */
class ExtensionTest extends TestCase
{
    use BuildsProperty, RefreshDatabase;

    private ReservationService $reservations;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildProperty(roomCount: 2, bufferMinutes: 60);
        $this->reservations = app(ReservationService::class);
    }

    /** A guest currently in house: 6-hour stay that started an hour ago. */
    private function inHouseStay(): Reservation
    {
        $start = CarbonImmutable::now()->subHour()->startOfHour();

        return Reservation::factory()
            ->forWindow($this->room(), $start, 6)
            ->status(ReservationStatus::CheckedIn)
            ->create([
                'user_id' => $this->customer()->id,
                'policy_version_id' => $this->policy->id,
                'checked_in_at' => $start,
            ]);
    }

    public function test_an_extension_is_granted_when_the_room_is_free(): void
    {
        $reservation = $this->inHouseStay();
        $originalEnd = $reservation->ends_at->copy();

        $extension = $this->reservations->requestExtension($reservation, 2, $reservation->customer);
        $this->reservations->approveExtension($extension, $this->staffMember());

        $reservation->refresh();

        $this->assertSame(ExtensionStatus::Approved, $extension->refresh()->status);
        $this->assertEquals($originalEnd->addHours(2), $reservation->ends_at);
        $this->assertEquals(
            $reservation->ends_at->copy()->addMinutes(60),
            $reservation->blocked_until,
            'The buffer must follow the new end time, not the old one.',
        );
        $this->assertSame(8, $reservation->package_hours);
    }

    public function test_the_extension_charge_uses_the_room_type_rate_and_is_added_to_the_balance(): void
    {
        $reservation = $this->inHouseStay();
        $balanceBefore = $reservation->balance_due_cents;

        $extension = $this->reservations->requestExtension($reservation, 3, $reservation->customer);

        // 3 hours at 30000 each.
        $this->assertSame(90000, $extension->charge_cents);
        $this->assertSame(30000, $extension->hourly_rate_cents_snapshot);

        $this->reservations->approveExtension($extension, $this->staffMember());

        $reservation->refresh();

        $this->assertSame(90000, $reservation->extensions_total_cents);
        $this->assertGreaterThan($balanceBefore, $reservation->balance_due_cents);
    }

    public function test_an_extension_is_refused_when_the_next_guest_is_already_booked(): void
    {
        $reservation = $this->inHouseStay();

        // Somebody is on the same room the moment the buffer expires.
        Reservation::factory()
            ->forWindow($this->room(), $reservation->blocked_until, 6)
            ->create(['policy_version_id' => $this->policy->id]);

        $this->expectException(ExtensionNotAvailableException::class);

        $this->reservations->requestExtension($reservation, 2, $reservation->customer);
    }

    /**
     * Availability is checked when the guest asks and again when the desk
     * answers, because the room may have gone in between.
     */
    public function test_availability_is_rechecked_at_approval(): void
    {
        $reservation = $this->inHouseStay();

        $extension = $this->reservations->requestExtension($reservation, 2, $reservation->customer);

        // The following slot is taken after the request but before the answer.
        Reservation::factory()
            ->forWindow($this->room(), $reservation->blocked_until, 6)
            ->create(['policy_version_id' => $this->policy->id]);

        $this->expectException(ExtensionNotAvailableException::class);

        $this->reservations->approveExtension($extension, $this->staffMember());
    }

    public function test_a_refused_extension_is_kept_with_its_reason(): void
    {
        $reservation = $this->inHouseStay();
        $staff = $this->staffMember();

        $extension = $this->reservations->requestExtension($reservation, 2, $reservation->customer);

        $this->reservations->refuseExtension($extension, $staff, 'Room is booked from 18:00.');

        $extension->refresh();

        $this->assertSame(ExtensionStatus::Refused, $extension->status);
        $this->assertSame('Room is booked from 18:00.', $extension->refusal_reason);
        $this->assertSame($staff->id, $extension->decided_by_user_id);
        $this->assertNotNull($extension->decided_at);

        // The stay itself is untouched.
        $this->assertSame(6, $reservation->refresh()->package_hours);
    }

    public function test_a_refused_extension_does_not_move_another_guest(): void
    {
        $reservation = $this->inHouseStay();

        $next = Reservation::factory()
            ->forWindow($this->room(), $reservation->blocked_until, 6)
            ->create(['policy_version_id' => $this->policy->id]);

        $nextRoom = $next->room_id;

        try {
            $this->reservations->requestExtension($reservation, 2, $reservation->customer);
        } catch (ExtensionNotAvailableException) {
            // expected
        }

        $this->assertSame($nextRoom, $next->refresh()->room_id, 'The following guest must not be relocated.');
        $this->assertSame(6, $reservation->refresh()->package_hours);
    }

    public function test_only_a_stay_that_is_under_way_can_be_extended(): void
    {
        $reservation = Reservation::factory()
            ->forWindow($this->room(), $this->tomorrowAt(10), 6)
            ->status(ReservationStatus::Confirmed)
            ->create(['policy_version_id' => $this->policy->id]);

        $this->expectExceptionMessage('Only a stay that is under way can be extended.');

        $this->reservations->requestExtension($reservation, 2);
    }

    public function test_an_extension_cannot_be_decided_twice(): void
    {
        $reservation = $this->inHouseStay();
        $staff = $this->staffMember();

        $extension = $this->reservations->requestExtension($reservation, 1, $reservation->customer);
        $this->reservations->approveExtension($extension, $staff);

        $this->expectExceptionMessage('That extension has already been decided.');

        $this->reservations->approveExtension($extension->refresh(), $staff);
    }
}
