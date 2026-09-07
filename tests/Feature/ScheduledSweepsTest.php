<?php

namespace Tests\Feature;

use App\Enums\ReservationStatus;
use App\Models\PolicyVersion;
use App\Models\Reservation;
use App\Services\AvailabilityService;
use App\Support\BookingWindow;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsProperty;
use Tests\TestCase;

/**
 * The two scheduled sweeps: releasing unpaid holds, and marking no-shows once
 * the grace period has passed.
 */
class ScheduledSweepsTest extends TestCase
{
    use BuildsProperty, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Freeze the clock on the hour, so "started 20 minutes ago" does not
        // become "started 80 minutes ago" once the start is snapped back to
        // the hour that reservations are required to begin on.
        $this->travelTo(CarbonImmutable::today()->setHour(14));

        $this->buildProperty(roomCount: 2, bufferMinutes: 60);
    }

    // -----------------------------------------------------------------------
    // Unpaid holds
    // -----------------------------------------------------------------------

    public function test_an_expired_unpaid_hold_is_released(): void
    {
        $reservation = Reservation::factory()
            ->forWindow($this->room(), $this->tomorrowAt(10), 6)
            ->pendingWithHold()
            ->create([
                'policy_version_id' => $this->policy->id,
                'hold_expires_at' => CarbonImmutable::now()->subMinute(),
            ]);

        $this->artisan('reservations:expire-holds')
            ->expectsOutputToContain('Released 1 expired hold.')
            ->assertSuccessful();

        $reservation->refresh();

        $this->assertSame(ReservationStatus::Expired, $reservation->status);
        $this->assertNull($reservation->hold_expires_at, 'A released hold must leave the expiry queue.');
    }

    public function test_releasing_a_hold_frees_the_room_for_someone_else(): void
    {
        $reservation = Reservation::factory()
            ->forWindow($this->room(), $this->tomorrowAt(10), 6)
            ->pendingWithHold()
            ->create([
                'policy_version_id' => $this->policy->id,
                'hold_expires_at' => CarbonImmutable::now()->subMinute(),
            ]);

        $window = BookingWindow::fromReservation($reservation);
        $availability = app(AvailabilityService::class);

        $this->assertFalse($availability->isRoomFree($reservation->room_id, $window));

        $this->artisan('reservations:expire-holds')->assertSuccessful();

        $this->assertTrue(
            $availability->isRoomFree($reservation->room_id, $window),
            'The whole point of expiring a hold is that the slot comes back.',
        );
    }

    public function test_a_hold_that_is_still_running_is_left_alone(): void
    {
        $reservation = Reservation::factory()
            ->forWindow($this->room(), $this->tomorrowAt(10), 6)
            ->pendingWithHold()
            ->create([
                'policy_version_id' => $this->policy->id,
                'hold_expires_at' => CarbonImmutable::now()->addMinutes(10),
            ]);

        $this->artisan('reservations:expire-holds')->assertSuccessful();

        $this->assertSame(ReservationStatus::Pending, $reservation->refresh()->status);
    }

    /**
     * A guest who has paid something is not sitting on an unpaid hold, however
     * long ago the deadline was.
     */
    public function test_a_hold_with_a_payment_against_it_is_never_expired(): void
    {
        $reservation = Reservation::factory()
            ->forWindow($this->room(), $this->tomorrowAt(10), 6)
            ->pendingWithHold()
            ->create([
                'policy_version_id' => $this->policy->id,
                'hold_expires_at' => CarbonImmutable::now()->subHour(),
                'amount_paid_cents' => 50000,
            ]);

        $this->artisan('reservations:expire-holds')->assertSuccessful();

        $this->assertSame(ReservationStatus::Pending, $reservation->refresh()->status);
    }

    public function test_expiry_records_the_transition_with_no_actor(): void
    {
        $reservation = Reservation::factory()
            ->forWindow($this->room(), $this->tomorrowAt(10), 6)
            ->pendingWithHold()
            ->create([
                'policy_version_id' => $this->policy->id,
                'hold_expires_at' => CarbonImmutable::now()->subMinute(),
            ]);

        $this->artisan('reservations:expire-holds')->assertSuccessful();

        $this->assertDatabaseHas('reservation_status_transitions', [
            'reservation_id' => $reservation->id,
            'to_status' => ReservationStatus::Expired->value,
            // Null actor is how "the scheduler did it" is recorded.
            'actor_user_id' => null,
        ]);
    }

    public function test_the_dry_run_changes_nothing(): void
    {
        $reservation = Reservation::factory()
            ->forWindow($this->room(), $this->tomorrowAt(10), 6)
            ->pendingWithHold()
            ->create([
                'policy_version_id' => $this->policy->id,
                'hold_expires_at' => CarbonImmutable::now()->subMinute(),
            ]);

        $this->artisan('reservations:expire-holds --dry-run')->assertSuccessful();

        $this->assertSame(ReservationStatus::Pending, $reservation->refresh()->status);
    }

    // -----------------------------------------------------------------------
    // No-shows
    // -----------------------------------------------------------------------

    public function test_a_guest_past_the_grace_period_is_marked_a_no_show(): void
    {
        // Grace is 60 minutes; this booking started 90 minutes ago.
        $reservation = Reservation::factory()
            ->forWindow($this->room(), CarbonImmutable::now()->subHours(2)->startOfHour(), 6)
            ->status(ReservationStatus::Confirmed)
            ->create(['policy_version_id' => $this->policy->id]);

        $this->artisan('reservations:mark-no-shows')->assertSuccessful();

        $reservation->refresh();

        $this->assertSame(ReservationStatus::NoShow, $reservation->status);
        $this->assertNotNull($reservation->no_show_at);
    }

    public function test_a_guest_still_inside_the_grace_period_is_left_alone(): void
    {
        // Due on the hour, and the clock is moved on 30 minutes rather than
        // the start being backdated -- reservations begin on the hour, so
        // "20 minutes ago" is not a start time that can exist.
        $reservation = Reservation::factory()
            ->forWindow($this->room(), CarbonImmutable::now()->startOfHour(), 6)
            ->status(ReservationStatus::Confirmed)
            ->create(['policy_version_id' => $this->policy->id]);

        $this->travel(30)->minutes();

        $this->artisan('reservations:mark-no-shows')->assertSuccessful();

        $this->assertSame(ReservationStatus::Confirmed, $reservation->refresh()->status);
    }

    public function test_a_checked_in_guest_is_never_marked_a_no_show(): void
    {
        $reservation = Reservation::factory()
            ->forWindow($this->room(), CarbonImmutable::now()->subHours(3)->startOfHour(), 6)
            ->status(ReservationStatus::CheckedIn)
            ->create(['policy_version_id' => $this->policy->id]);

        $this->artisan('reservations:mark-no-shows')->assertSuccessful();

        $this->assertSame(ReservationStatus::CheckedIn, $reservation->refresh()->status);
    }

    /**
     * The grace period is read from each reservation's own policy version, so
     * a booking made under a longer grace keeps it.
     */
    public function test_each_booking_uses_its_own_grace_period(): void
    {
        $generous = PolicyVersion::factory()->create(['no_show_grace_minutes' => 240]);

        $underGenerousTerms = Reservation::factory()
            ->forWindow($this->room(0), CarbonImmutable::now()->subHours(2)->startOfHour(), 6)
            ->status(ReservationStatus::Confirmed)
            ->create(['policy_version_id' => $generous->id]);

        $underStandardTerms = Reservation::factory()
            ->forWindow($this->room(1), CarbonImmutable::now()->subHours(2)->startOfHour(), 6)
            ->status(ReservationStatus::Confirmed)
            ->create(['policy_version_id' => $this->policy->id]);

        $this->artisan('reservations:mark-no-shows')->assertSuccessful();

        $this->assertSame(
            ReservationStatus::Confirmed,
            $underGenerousTerms->refresh()->status,
            'A four-hour grace period has not elapsed after two hours.',
        );

        $this->assertSame(ReservationStatus::NoShow, $underStandardTerms->refresh()->status);
    }

    public function test_a_no_show_releases_the_room(): void
    {
        $reservation = Reservation::factory()
            ->forWindow($this->room(), CarbonImmutable::now()->subHours(2)->startOfHour(), 6)
            ->status(ReservationStatus::Confirmed)
            ->create(['policy_version_id' => $this->policy->id]);

        $this->artisan('reservations:mark-no-shows')->assertSuccessful();

        $this->assertTrue(
            app(AvailabilityService::class)->isRoomFree(
                $reservation->room_id,
                BookingWindow::fromReservation($reservation->refresh()),
            ),
        );
    }
}
