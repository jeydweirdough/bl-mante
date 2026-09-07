<?php

namespace Tests\Feature;

use App\Enums\PaymentMode;
use App\Enums\ReservationStatus;
use App\Enums\RoomStatus;
use App\Models\Extra;
use App\Models\Payment;
use App\Models\Reservation;
use App\Services\ReservationService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsProperty;
use Tests\TestCase;

/**
 * The journeys end to end, through the HTTP layer, for all three actors.
 *
 * These double as the render check on every page: a broken view fails here
 * rather than in front of a guest.
 */
class BookingJourneyTest extends TestCase
{
    use BuildsProperty, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::today()->setHour(9));
        $this->buildProperty(roomCount: 3, bufferMinutes: 60);
    }

    // -----------------------------------------------------------------------
    // Public
    // -----------------------------------------------------------------------

    public function test_a_visitor_can_search_availability(): void
    {
        $response = $this->get(route('availability', [
            'date' => CarbonImmutable::tomorrow()->format('Y-m-d'),
            'hour' => 10,
            'duration_package_id' => $this->sixHours->id,
            'adults' => 2,
            'children' => 0,
        ]));

        $response->assertOk()
            ->assertSee($this->roomType->name)
            ->assertSee('3 rooms free');
    }

    public function test_the_search_reports_when_nothing_is_free(): void
    {
        foreach ($this->rooms as $room) {
            Reservation::factory()
                ->forWindow($room, $this->tomorrowAt(10), 6)
                ->create(['policy_version_id' => $this->policy->id]);
        }

        $this->get(route('availability', [
            'date' => CarbonImmutable::tomorrow()->format('Y-m-d'),
            'hour' => 10,
            'duration_package_id' => $this->sixHours->id,
        ]))
            ->assertOk()
            ->assertSee('Nothing is free for that window.');
    }

    public function test_the_search_rejects_a_date_in_the_past(): void
    {
        $this->get(route('availability', [
            'date' => CarbonImmutable::yesterday()->format('Y-m-d'),
            'hour' => 10,
            'duration_package_id' => $this->sixHours->id,
        ]))->assertSessionHasErrors('date');
    }

    // -----------------------------------------------------------------------
    // Customer
    // -----------------------------------------------------------------------

    public function test_a_customer_can_book_and_pay_online(): void
    {
        $customer = $this->customer();

        $this->actingAs($customer)
            ->get(route('booking.create', [
                'room_type_id' => $this->roomType->id,
                'duration_package_id' => $this->sixHours->id,
                'date' => CarbonImmutable::tomorrow()->format('Y-m-d'),
                'hour' => 10,
                'adults' => 2,
            ]))
            ->assertOk()
            ->assertSee('Confirm your booking')
            ->assertSee('Cancellation terms');

        $response = $this->actingAs($customer)->post(route('booking.store'), [
            'room_type_id' => $this->roomType->id,
            'duration_package_id' => $this->sixHours->id,
            'date' => CarbonImmutable::tomorrow()->format('Y-m-d'),
            'hour' => 10,
            'adults' => 2,
            'children' => 0,
            'payment_mode' => PaymentMode::Online->value,
            'terms' => '1',
        ]);

        $reservation = Reservation::firstOrFail();

        $response->assertRedirect(route('reservations.pay', $reservation));
        $this->assertSame(ReservationStatus::Pending, $reservation->status);

        // The payment choice screen.
        $this->actingAs($customer)
            ->get(route('reservations.pay', $reservation))
            ->assertOk()
            ->assertSee('Pay in full');

        // Off to the provider.
        $this->actingAs($customer)
            ->post(route('reservations.pay.checkout', $reservation), ['portion' => 'full'])
            ->assertRedirectContains('checkout-simulator');

        $payment = Payment::firstOrFail();

        // The stand-in hosted page renders, and has no card fields on it.
        $this->get(route('payments.mock.show', $payment->provider_reference))
            ->assertOk()
            ->assertSee('Approve payment')
            ->assertDontSee('card number', false);

        $this->post(route('payments.mock.approve', $payment->provider_reference))
            ->assertRedirect(route('payments.return', $reservation));

        $this->actingAs($customer)
            ->get(route('payments.return', $reservation))
            ->assertRedirect(route('reservations.show', $reservation))
            ->assertSessionHas('status');

        $reservation->refresh();

        $this->assertSame(ReservationStatus::Confirmed, $reservation->status);
        $this->assertSame(0, $reservation->balance_due_cents);
    }

    public function test_a_customer_can_book_to_pay_at_the_property(): void
    {
        $customer = $this->customer();

        $this->actingAs($customer)->post(route('booking.store'), [
            'room_type_id' => $this->roomType->id,
            'duration_package_id' => $this->sixHours->id,
            'date' => CarbonImmutable::tomorrow()->format('Y-m-d'),
            'hour' => 10,
            'adults' => 1,
            'children' => 0,
            'payment_mode' => PaymentMode::AtProperty->value,
            'terms' => '1',
        ]);

        $reservation = Reservation::firstOrFail();

        $this->assertSame(ReservationStatus::Confirmed, $reservation->status);
        $this->assertNull($reservation->hold_expires_at);
    }

    public function test_the_booking_form_requires_the_terms_to_be_accepted(): void
    {
        $this->actingAs($this->customer())
            ->post(route('booking.store'), [
                'room_type_id' => $this->roomType->id,
                'duration_package_id' => $this->sixHours->id,
                'date' => CarbonImmutable::tomorrow()->format('Y-m-d'),
                'hour' => 10,
                'adults' => 1,
                'children' => 0,
                'payment_mode' => PaymentMode::AtProperty->value,
            ])
            ->assertSessionHasErrors('terms');

        $this->assertSame(0, Reservation::count());
    }

    public function test_a_party_larger_than_the_room_is_refused(): void
    {
        $this->actingAs($this->customer())
            ->post(route('booking.store'), [
                'room_type_id' => $this->roomType->id,
                'duration_package_id' => $this->sixHours->id,
                'date' => CarbonImmutable::tomorrow()->format('Y-m-d'),
                'hour' => 10,
                'adults' => 5,
                'children' => 2,
                'payment_mode' => PaymentMode::AtProperty->value,
                'terms' => '1',
            ])
            ->assertSessionHasErrors('adults');
    }

    public function test_a_duplicate_booking_is_reported_on_the_form(): void
    {
        $customer = $this->customer();

        $payload = [
            'room_type_id' => $this->roomType->id,
            'duration_package_id' => $this->sixHours->id,
            'date' => CarbonImmutable::tomorrow()->format('Y-m-d'),
            'hour' => 10,
            'adults' => 1,
            'children' => 0,
            'payment_mode' => PaymentMode::AtProperty->value,
            'terms' => '1',
        ];

        $this->actingAs($customer)->post(route('booking.store'), $payload);

        // Same customer, an overlapping window.
        $this->actingAs($customer)
            ->post(route('booking.store'), array_merge($payload, ['hour' => 12]))
            ->assertSessionHasErrors('starts_at');

        $this->assertSame(1, Reservation::count());
    }

    public function test_a_customer_sees_their_bookings_and_can_cancel(): void
    {
        $customer = $this->customer();

        $reservation = Reservation::factory()
            ->forWindow($this->room(), CarbonImmutable::now()->addHours(48)->startOfHour(), 6)
            ->create([
                'user_id' => $customer->id,
                'policy_version_id' => $this->policy->id,
            ]);

        $this->actingAs($customer)
            ->get(route('reservations.index'))
            ->assertOk()
            ->assertSee($reservation->reference);

        $this->actingAs($customer)
            ->get(route('reservations.cancel.confirm', $reservation))
            ->assertOk()
            ->assertSee('What you get back');

        $this->actingAs($customer)
            ->delete(route('reservations.cancel', $reservation), ['reason' => 'Plans changed'])
            ->assertRedirect(route('reservations.show', $reservation));

        $this->assertSame(ReservationStatus::Cancelled, $reservation->refresh()->status);
    }

    public function test_a_customer_can_reschedule_and_check_a_slot_first(): void
    {
        $customer = $this->customer();

        $reservation = Reservation::factory()
            ->forWindow($this->room(), CarbonImmutable::now()->addHours(48)->startOfHour(), 6)
            ->create([
                'user_id' => $customer->id,
                'policy_version_id' => $this->policy->id,
            ]);

        $this->actingAs($customer)
            ->get(route('reservations.reschedule.edit', $reservation))
            ->assertOk()
            ->assertSee('Move to another time');

        $newDate = CarbonImmutable::now()->addDays(3);

        $this->actingAs($customer)
            ->getJson(route('reservations.reschedule.check', $reservation).'?date='.$newDate->format('Y-m-d').'&hour=14')
            ->assertOk()
            ->assertJson(['available' => true]);

        $this->actingAs($customer)
            ->put(route('reservations.reschedule', $reservation), [
                'date' => $newDate->format('Y-m-d'),
                'hour' => 14,
            ])
            ->assertRedirect(route('reservations.show', $reservation));

        $reservation->refresh();

        $this->assertSame(14, $reservation->starts_at->hour);
        $this->assertSame(1, $reservation->reschedule_count);
        $this->assertDatabaseCount('reservation_reschedules', 1);
    }

    public function test_a_customer_can_add_an_extra_which_changes_the_balance(): void
    {
        $customer = $this->customer();
        $extra = Extra::factory()->costing(50000)->create();

        $reservation = Reservation::factory()
            ->forWindow($this->room(), $this->tomorrowAt(10), 6)
            ->create([
                'user_id' => $customer->id,
                'policy_version_id' => $this->policy->id,
            ]);

        $before = $reservation->balance_due_cents;

        $this->actingAs($customer)
            ->post(route('reservations.extras.store', $reservation), [
                'extra_id' => $extra->id,
                'quantity' => 2,
            ])
            ->assertRedirect();

        $reservation->refresh();

        $this->assertSame(100000, $reservation->extras_total_cents);
        $this->assertGreaterThan($before, $reservation->balance_due_cents);
    }

    // -----------------------------------------------------------------------
    // Front desk
    // -----------------------------------------------------------------------

    public function test_the_daily_board_renders_with_arrivals_and_departures(): void
    {
        Reservation::factory()
            ->forWindow($this->room(0), CarbonImmutable::today()->setHour(11), 6)
            ->create(['policy_version_id' => $this->policy->id]);

        Reservation::factory()
            ->forWindow($this->room(1), CarbonImmutable::today()->setHour(8), 6)
            ->status(ReservationStatus::CheckedIn)
            ->create(['policy_version_id' => $this->policy->id]);

        $this->actingAs($this->staffMember())
            ->get(route('staff.dashboard'))
            ->assertOk()
            ->assertSee('Arrivals')
            ->assertSee('In house')
            ->assertSee('By the hour');
    }

    public function test_staff_can_create_a_walk_in_booking(): void
    {
        $staff = $this->staffMember();

        $this->actingAs($staff)
            ->get(route('staff.reservations.create', [
                'date' => CarbonImmutable::today()->format('Y-m-d'),
                'hour' => 10,
                'duration_package_id' => $this->sixHours->id,
            ]))
            ->assertOk()
            ->assertSee('Walk-in or phone booking');

        $this->actingAs($staff)->post(route('staff.reservations.store'), [
            'channel' => 'walk_in',
            'room_type_id' => $this->roomType->id,
            'room_id' => $this->room()->id,
            'duration_package_id' => $this->sixHours->id,
            'date' => CarbonImmutable::today()->format('Y-m-d'),
            'hour' => 10,
            'adults' => 2,
            'children' => 0,
            'guest_name' => 'Juan dela Cruz',
            'guest_phone' => '+63 917 555 0000',
            'payment_mode' => PaymentMode::AtProperty->value,
        ])->assertRedirect();

        $reservation = Reservation::firstOrFail();

        $this->assertNull($reservation->user_id, 'A walk-in guest has no account.');
        $this->assertSame('Juan dela Cruz', $reservation->guest_name);
        $this->assertSame($staff->id, $reservation->created_by_user_id);
        $this->assertSame(ReservationStatus::Confirmed, $reservation->status);
    }

    public function test_staff_can_check_a_guest_in_take_payment_and_check_them_out(): void
    {
        $staff = $this->staffMember();

        $reservation = Reservation::factory()
            ->forWindow($this->room(), CarbonImmutable::now()->startOfHour(), 6)
            ->create(['policy_version_id' => $this->policy->id]);

        $this->actingAs($staff)
            ->get(route('staff.reservations.show', $reservation))
            ->assertOk()
            ->assertSee('Record a payment taken at the desk');

        $this->actingAs($staff)
            ->post(route('staff.reservations.check-in', $reservation))
            ->assertRedirect();

        $reservation->refresh();
        $this->assertSame(ReservationStatus::CheckedIn, $reservation->status);
        $this->assertSame(RoomStatus::Occupied, $reservation->room->refresh()->status);

        $this->actingAs($staff)->post(route('staff.payments.store', $reservation), [
            'amount' => number_format($reservation->total_cents / 100, 2, '.', ''),
            'method' => 'cash',
            'kind' => 'full',
        ])->assertRedirect();

        $this->assertSame(0, $reservation->refresh()->balance_due_cents);

        $this->actingAs($staff)
            ->post(route('staff.reservations.check-out', $reservation))
            ->assertRedirect();

        $reservation->refresh();

        $this->assertSame(ReservationStatus::CheckedOut, $reservation->status);
        $this->assertSame(
            RoomStatus::Cleaning,
            $reservation->room->refresh()->status,
            'A departure puts the room into turnover.',
        );
    }

    public function test_check_in_is_refused_while_the_room_is_still_being_cleaned(): void
    {
        $staff = $this->staffMember();

        $reservation = Reservation::factory()
            ->forWindow($this->room(), CarbonImmutable::now()->startOfHour(), 6)
            ->create(['policy_version_id' => $this->policy->id]);

        $reservation->room->update(['status' => RoomStatus::Cleaning]);

        $this->actingAs($staff)
            ->post(route('staff.reservations.check-in', $reservation))
            ->assertSessionHas('unavailable');

        $this->assertSame(ReservationStatus::Confirmed, $reservation->refresh()->status);
    }

    public function test_staff_can_reassign_a_room(): void
    {
        $staff = $this->staffMember();

        $reservation = Reservation::factory()
            ->forWindow($this->room(0), $this->tomorrowAt(10), 6)
            ->create(['policy_version_id' => $this->policy->id]);

        $this->actingAs($staff)->post(route('staff.reservations.assign-room', $reservation), [
            'room_id' => $this->room(1)->id,
            'reason' => 'Guest asked for a higher floor',
        ])->assertRedirect();

        $reservation->refresh();

        $this->assertSame($this->room(1)->id, $reservation->room_id);

        // The move is recorded, with where it came from and who did it. (The
        // factory does not write an initial assignment row, so this is the
        // only one; a booking made through the service would have two.)
        $this->assertDatabaseHas('room_assignments', [
            'reservation_id' => $reservation->id,
            'from_room_id' => $this->room(0)->id,
            'to_room_id' => $this->room(1)->id,
            'changed_by_user_id' => $staff->id,
        ]);

        $this->assertDatabaseHas('audit_logs', ['action' => 'reservation.room_reassigned']);
    }

    public function test_reassigning_to_an_occupied_room_is_refused_kindly(): void
    {
        $staff = $this->staffMember();

        $reservation = Reservation::factory()
            ->forWindow($this->room(0), $this->tomorrowAt(10), 6)
            ->create(['policy_version_id' => $this->policy->id]);

        Reservation::factory()
            ->forWindow($this->room(1), $this->tomorrowAt(10), 6)
            ->create(['policy_version_id' => $this->policy->id]);

        $this->actingAs($staff)
            ->post(route('staff.reservations.assign-room', $reservation), ['room_id' => $this->room(1)->id])
            ->assertSessionHas('unavailable');

        $this->assertSame($this->room(0)->id, $reservation->refresh()->room_id);
    }

    public function test_the_housekeeping_board_renders_and_a_room_can_be_cleared(): void
    {
        $staff = $this->staffMember();
        $this->room()->update(['status' => RoomStatus::Cleaning]);

        $this->actingAs($staff)
            ->get(route('staff.housekeeping'))
            ->assertOk()
            ->assertSee('Cleaning done');

        $this->actingAs($staff)
            ->post(route('staff.rooms.cleaning-complete', $this->room()))
            ->assertRedirect();

        $this->assertNotSame(RoomStatus::Cleaning, $this->room()->refresh()->status);
    }

    public function test_the_bookings_list_can_be_searched(): void
    {
        $reservation = Reservation::factory()
            ->forWindow($this->room(), $this->tomorrowAt(10), 6)
            ->walkIn()
            ->create(['policy_version_id' => $this->policy->id]);

        $this->actingAs($this->staffMember())
            ->get(route('staff.reservations.index', ['q' => $reservation->reference]))
            ->assertOk()
            ->assertSee($reservation->reference);
    }

    // -----------------------------------------------------------------------
    // Admin
    // -----------------------------------------------------------------------

    public function test_the_admin_screens_render(): void
    {
        $admin = $this->admin();
        Extra::factory()->create();

        $this->actingAs($admin);

        $this->get(route('admin.room-types.index'))->assertOk()->assertSee($this->roomType->name);
        $this->get(route('admin.room-types.create'))->assertOk();
        $this->get(route('admin.room-types.edit', $this->roomType))->assertOk();
        $this->get(route('admin.rooms.index'))->assertOk();
        $this->get(route('admin.pricing.index'))->assertOk();
        $this->get(route('admin.extras.index'))->assertOk();
        $this->get(route('admin.policy.edit'))->assertOk()->assertSee('Booking policy');
        $this->get(route('admin.staff.index'))->assertOk();
        $this->get(route('admin.reports.index'))->assertOk()->assertSee('Occupancy');
    }

    public function test_publishing_a_policy_creates_a_new_version(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->put(route('admin.policy.update'), [
            'turnover_buffer_minutes' => 90,
            'unpaid_hold_minutes' => 45,
            'downpayment_percent' => 40,
            'full_refund_hours_before' => 48,
            'partial_refund_hours_before' => 12,
            'no_show_grace_minutes' => 30,
            'free_reschedule_hours_before' => 36,
            'tax_percent' => 12,
            'service_fee' => '0',
            'change_note' => 'Longer turnover for the new cleaning contract.',
        ])->assertRedirect();

        $this->assertDatabaseHas('policy_versions', [
            'turnover_buffer_minutes' => 90,
            'is_current' => true,
        ]);

        // The previous version survives, still pointed at by old bookings.
        $this->assertDatabaseHas('policy_versions', [
            'id' => $this->policy->id,
            'is_current' => false,
        ]);

        $this->assertDatabaseHas('audit_logs', ['action' => 'policy.published']);
    }

    public function test_the_policy_form_rejects_inverted_refund_tiers(): void
    {
        $this->actingAs($this->admin())->put(route('admin.policy.update'), [
            'turnover_buffer_minutes' => 60,
            'unpaid_hold_minutes' => 30,
            'downpayment_percent' => 50,
            'full_refund_hours_before' => 6,
            'partial_refund_hours_before' => 24,
            'no_show_grace_minutes' => 60,
            'free_reschedule_hours_before' => 24,
            'tax_percent' => 12,
            'service_fee' => '0',
        ])->assertSessionHasErrors('partial_refund_hours_before');
    }

    public function test_disabling_an_extra_leaves_existing_bookings_alone(): void
    {
        $admin = $this->admin();
        $extra = Extra::factory()->costing(50000)->create();

        $reservation = Reservation::factory()
            ->forWindow($this->room(), $this->tomorrowAt(10), 6)
            ->create(['policy_version_id' => $this->policy->id]);

        app(ReservationService::class)->addExtra($reservation, $extra, 1);

        $this->actingAs($admin)
            ->post(route('admin.extras.toggle', $extra))
            ->assertRedirect();

        $this->assertFalse($extra->refresh()->is_active);

        $line = $reservation->refresh()->extras->first();

        $this->assertSame($extra->name, $line->name_snapshot);
        $this->assertSame(50000, $line->line_total_cents);
        $this->assertSame(50000, $reservation->extras_total_cents);
    }

    public function test_changing_a_price_does_not_alter_an_existing_booking(): void
    {
        $admin = $this->admin();

        $reservation = Reservation::factory()
            ->forWindow($this->room(), $this->tomorrowAt(10), 6)
            ->create(['policy_version_id' => $this->policy->id]);

        $priceBefore = $reservation->package_price_cents;

        $this->actingAs($admin)->put(route('admin.pricing.update'), [
            'prices' => [
                $this->roomType->id => [$this->sixHours->id => '9999.00'],
            ],
        ])->assertRedirect();

        $this->assertSame(999900, $this->roomType->packagePrices()->first()->price_cents);
        $this->assertSame($priceBefore, $reservation->refresh()->package_price_cents);
    }
}
