<?php

namespace Tests\Feature;

use App\Models\Reservation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsProperty;
use Tests\TestCase;

/**
 * Who can reach what.
 *
 * The staff and admin sections are gated by middleware so a customer never
 * gets a policy failure deep inside a page; individual actions are gated by
 * the policies on top of that.
 */
class AuthorizationTest extends TestCase
{
    use BuildsProperty, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildProperty(roomCount: 2);
    }

    public function test_the_public_can_browse_without_an_account(): void
    {
        $this->get(route('home'))->assertOk();
        $this->get(route('rooms.index'))->assertOk();
        $this->get(route('rooms.show', $this->roomType))->assertOk();
        $this->get(route('availability'))->assertOk();
    }

    public function test_booking_requires_an_account(): void
    {
        $this->get(route('booking.create'))->assertRedirect(route('login'));
        $this->post(route('booking.store'))->assertRedirect(route('login'));
    }

    public function test_a_customer_cannot_reach_the_staff_area(): void
    {
        $this->actingAs($this->customer());

        $this->get(route('staff.dashboard'))->assertForbidden();
        $this->get(route('staff.reservations.index'))->assertForbidden();
        $this->get(route('staff.housekeeping'))->assertForbidden();
    }

    public function test_a_customer_cannot_reach_the_admin_area(): void
    {
        $this->actingAs($this->customer());

        $this->get(route('admin.policy.edit'))->assertForbidden();
        $this->get(route('admin.pricing.index'))->assertForbidden();
        $this->get(route('admin.staff.index'))->assertForbidden();
        $this->get(route('admin.reports.index'))->assertForbidden();
    }

    public function test_staff_cannot_reach_the_admin_area(): void
    {
        $this->actingAs($this->staffMember());

        $this->get(route('staff.dashboard'))->assertOk();

        $this->get(route('admin.policy.edit'))->assertForbidden();
        $this->get(route('admin.reports.index'))->assertForbidden();
        $this->get(route('admin.room-types.index'))->assertForbidden();
    }

    public function test_an_admin_reaches_everything(): void
    {
        $this->actingAs($this->admin());

        $this->get(route('staff.dashboard'))->assertOk();
        $this->get(route('admin.policy.edit'))->assertOk();
        $this->get(route('admin.reports.index'))->assertOk();
    }

    public function test_a_customer_cannot_view_another_customers_booking(): void
    {
        $reservation = Reservation::factory()
            ->forWindow($this->room(), $this->tomorrowAt(10), 6)
            ->create(['policy_version_id' => $this->policy->id]);

        $this->actingAs($this->customer())
            ->get(route('reservations.show', $reservation))
            ->assertForbidden();
    }

    public function test_a_customer_can_view_their_own_booking(): void
    {
        $customer = $this->customer();

        $reservation = Reservation::factory()
            ->forWindow($this->room(), $this->tomorrowAt(10), 6)
            ->create([
                'user_id' => $customer->id,
                'policy_version_id' => $this->policy->id,
            ]);

        $this->actingAs($customer)
            ->get(route('reservations.show', $reservation))
            ->assertOk()
            ->assertSee($reservation->reference);
    }

    public function test_a_customer_cannot_cancel_someone_elses_booking(): void
    {
        $reservation = Reservation::factory()
            ->forWindow($this->room(), $this->tomorrowAt(10), 6)
            ->create(['policy_version_id' => $this->policy->id]);

        $this->actingAs($this->customer())
            ->delete(route('reservations.cancel', $reservation))
            ->assertForbidden();
    }

    public function test_staff_may_view_any_booking(): void
    {
        $reservation = Reservation::factory()
            ->forWindow($this->room(), $this->tomorrowAt(10), 6)
            ->create(['policy_version_id' => $this->policy->id]);

        $this->actingAs($this->staffMember())
            ->get(route('staff.reservations.show', $reservation))
            ->assertOk();
    }

    /**
     * Deactivated staff keep their attribution on past records but must not be
     * able to act.
     */
    public function test_a_deactivated_account_is_signed_out(): void
    {
        $inactive = $this->staffMember();
        $inactive->update(['is_active' => false]);

        $this->actingAs($inactive)
            ->get(route('staff.dashboard'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_a_customer_cannot_record_a_payment(): void
    {
        $customer = $this->customer();

        $reservation = Reservation::factory()
            ->forWindow($this->room(), $this->tomorrowAt(10), 6)
            ->create([
                'user_id' => $customer->id,
                'policy_version_id' => $this->policy->id,
            ]);

        $this->actingAs($customer)
            ->post(route('staff.payments.store', $reservation), [
                'amount' => '100.00',
                'method' => 'cash',
                'kind' => 'balance',
            ])
            ->assertForbidden();
    }

    public function test_only_an_admin_may_take_a_room_out_of_service(): void
    {
        $this->actingAs($this->staffMember())
            ->post(route('staff.rooms.out-of-service', $this->room()))
            ->assertForbidden();

        $this->actingAs($this->admin())
            ->post(route('staff.rooms.out-of-service', $this->room()))
            ->assertRedirect();
    }

    public function test_an_admin_cannot_deactivate_themselves(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('admin.staff.toggle', $admin))
            ->assertForbidden();

        $this->assertTrue($admin->refresh()->is_active);
    }
}
