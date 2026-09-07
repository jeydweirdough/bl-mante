<?php

namespace Tests\Feature;

use App\Enums\ReservationStatus;
use App\Models\Enquiry;
use App\Models\Reservation;
use App\Services\AvailabilityService;
use App\Support\Availability;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\Support\BuildsProperty;
use Tests\TestCase;

/**
 * The four public pages: home, about, rooms and contact.
 *
 * The rooms page carries the interesting requirement -- when a room type is
 * full for the requested window it must say when it is next free, not just
 * refuse. A dead end loses the visitor.
 */
class PublicPagesTest extends TestCase
{
    use BuildsProperty, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // On the hour, and early enough that "the next free slot" stays inside
        // the same day for the assertions below.
        $this->travelTo(CarbonImmutable::today()->setHour(8));

        $this->buildProperty(roomCount: 2, bufferMinutes: 60);
    }

    private function roomsPageAt(int $hour): TestResponse
    {
        return $this->get(route('rooms.index', [
            'date' => CarbonImmutable::today()->format('Y-m-d'),
            'hour' => $hour,
            'duration_package_id' => $this->sixHours->id,
        ]));
    }

    /** Fill every room in the property for a window. */
    private function fillProperty(CarbonImmutable $start, int $hours = 6): void
    {
        foreach ($this->rooms as $room) {
            Reservation::factory()
                ->forWindow($room, $start, $hours)
                ->create(['policy_version_id' => $this->policy->id]);
        }
    }

    // -----------------------------------------------------------------------
    // Reachability
    // -----------------------------------------------------------------------

    public function test_all_four_public_pages_render_without_an_account(): void
    {
        $this->get(route('home'))->assertOk()->assertSee(config('hotel.name'));
        $this->get(route('about'))->assertOk()->assertSee('About '.config('hotel.name'));
        $this->get(route('rooms.index'))->assertOk()->assertSee('Rooms and hourly rates');
        $this->get(route('contact'))->assertOk()->assertSee('Contact us');
    }

    public function test_every_page_links_to_the_others(): void
    {
        foreach ([route('home'), route('about'), route('rooms.index'), route('contact')] as $page) {
            $html = $this->get($page)->assertOk()->getContent();

            foreach ([route('home'), route('about'), route('rooms.index'), route('contact')] as $link) {
                $this->assertStringContainsString(
                    'href="'.$link.'"',
                    $html,
                    "$page does not link to $link",
                );
            }
        }
    }

    // -----------------------------------------------------------------------
    // Rooms: available
    // -----------------------------------------------------------------------

    public function test_the_rooms_page_shows_rooms_free_for_the_requested_window(): void
    {
        $this->roomsPageAt(14)
            ->assertOk()
            ->assertSee($this->roomType->name)
            ->assertSee('2 rooms free')
            ->assertSee('Book this room');
    }

    public function test_it_warns_when_a_room_type_is_nearly_gone(): void
    {
        Reservation::factory()
            ->forWindow($this->room(0), CarbonImmutable::today()->setHour(14), 6)
            ->create(['policy_version_id' => $this->policy->id]);

        $this->roomsPageAt(14)
            ->assertOk()
            ->assertSee('1 room free')
            ->assertSee('Almost gone for this time.');
    }

    public function test_it_defaults_to_the_next_bookable_hour(): void
    {
        // 08:00 frozen, so the default window starts at 09:00.
        $this->get(route('rooms.index'))
            ->assertOk()
            ->assertSee('from <strong class="text-slate-900">09:00</strong>', escape: false);
    }

    public function test_a_start_time_in_the_past_is_pulled_forward_not_rejected(): void
    {
        $this->get(route('rooms.index', [
            'date' => CarbonImmutable::yesterday()->format('Y-m-d'),
            'hour' => 10,
            'duration_package_id' => $this->sixHours->id,
        ]))->assertOk()->assertSee('09:00');
    }

    // -----------------------------------------------------------------------
    // Rooms: full, and when it is next free -- the actual requirement
    // -----------------------------------------------------------------------

    public function test_a_fully_booked_type_says_when_it_is_next_free(): void
    {
        // Everything taken 10:00-16:00, so blocked until 17:00 by the buffer.
        $this->fillProperty(CarbonImmutable::today()->setHour(10));

        $response = $this->roomsPageAt(12)->assertOk();

        $response->assertSee('Fully booked at 12:00');
        $response->assertSee('Next free today at 17:00.');

        // And it is offered as something to click, not just stated.
        $response->assertSee('Book '.CarbonImmutable::today()->setHour(17)->format('D H:i'));
    }

    public function test_the_next_free_link_lands_on_a_window_that_is_actually_bookable(): void
    {
        $this->fillProperty(CarbonImmutable::today()->setHour(10));

        $next = app(AvailabilityService::class)
            ->overview(CarbonImmutable::today()->setHour(12), $this->sixHours)
            ->first()
            ->nextWindow;

        $this->assertNotNull($next);
        $this->assertSame(17, $next->startsAt->hour, 'The buffer pushes the next free start to 17:00.');

        // Following the suggestion must actually show availability.
        $this->roomsPageAt($next->startsAt->hour)
            ->assertOk()
            ->assertSee('rooms free');
    }

    public function test_the_whole_property_being_full_is_announced_once_at_the_top(): void
    {
        $this->fillProperty(CarbonImmutable::today()->setHour(10));

        $this->roomsPageAt(12)
            ->assertOk()
            ->assertSee('Nothing is free at 12:00')
            ->assertSee('call the desk');
    }

    public function test_no_such_banner_when_something_is_free(): void
    {
        $this->roomsPageAt(14)
            ->assertOk()
            ->assertDontSee('Nothing is free at');
    }

    /**
     * When the forward search finds nothing at all within the horizon, the
     * page must hand the visitor a phone number rather than a shrug.
     */
    public function test_a_type_full_for_the_whole_horizon_offers_the_phone(): void
    {
        // Book every room solidly across the lookahead window.
        $lookahead = config('hotel.rooms_lookahead_days');
        $cursor = CarbonImmutable::today()->setHour(9);
        $end = $cursor->addDays($lookahead + 1);

        while ($cursor->lt($end)) {
            foreach ($this->rooms as $room) {
                Reservation::factory()
                    ->forWindow($room, $cursor, 22)
                    ->create(['policy_version_id' => $this->policy->id]);
            }
            // 22 hours + 1 hour buffer = the next legal start.
            $cursor = $cursor->addHours(23);
        }

        $row = app(AvailabilityService::class)
            ->overview(CarbonImmutable::today()->setHour(10), $this->sixHours)
            ->first();

        $this->assertFalse($row->isAvailable());
        $this->assertNull($row->nextWindow, 'Nothing should be free anywhere in the horizon.');
        $this->assertStringContainsString('Call us', $row->unavailableMessage());
    }

    public function test_a_type_not_priced_for_the_package_is_distinguished_from_being_full(): void
    {
        $this->roomType->packagePrices()->delete();

        $this->roomsPageAt(14)
            ->assertOk()
            ->assertSee('Not offered as a 6-hour stay')
            ->assertDontSee('Fully booked');
    }

    public function test_cancelled_bookings_do_not_make_a_room_look_full(): void
    {
        $this->fillProperty(CarbonImmutable::today()->setHour(14));

        Reservation::query()->update(['status' => ReservationStatus::Cancelled]);

        $this->roomsPageAt(14)->assertOk()->assertSee('2 rooms free');
    }

    public function test_the_forward_search_costs_a_fixed_number_of_queries(): void
    {
        $this->fillProperty(CarbonImmutable::today()->setHour(10));

        DB::enableQueryLog();

        app(AvailabilityService::class)->overview(CarbonImmutable::today()->setHour(12), $this->sixHours);

        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        // Rooms, reservations, prices, and the policy lookup. Scanning a
        // fortnight hour by hour against the database would be thousands.
        $this->assertLessThanOrEqual(
            6,
            $queries,
            "The overview took $queries queries; it should scan in memory.",
        );
    }

    // -----------------------------------------------------------------------
    // Contact
    // -----------------------------------------------------------------------

    public function test_a_visitor_can_send_a_message(): void
    {
        $this->post(route('contact.store'), [
            'name' => 'Juan dela Cruz',
            'email' => 'JUAN@example.com',
            'phone' => '+63 917 555 0000',
            'subject' => 'Late arrival',
            'message' => 'My flight lands at 3am, is that a problem for a 4am booking?',
            'rendered_at' => now()->subMinute()->timestamp,
        ])
            ->assertRedirect(route('contact'))
            ->assertSessionHas('status');

        $enquiry = Enquiry::sole();

        $this->assertSame('Juan dela Cruz', $enquiry->name);
        $this->assertSame('juan@example.com', $enquiry->email, 'Emails are normalised to lower case.');
        $this->assertTrue($enquiry->isUnread());
    }

    public function test_a_signed_in_customer_is_linked_to_their_message(): void
    {
        $customer = $this->customer();

        $this->actingAs($customer)->post(route('contact.store'), [
            'name' => $customer->name,
            'email' => $customer->email,
            'message' => 'Could I have a quiet room away from the lift, please?',
            'rendered_at' => now()->subMinute()->timestamp,
        ])->assertRedirect();

        $this->assertSame($customer->id, Enquiry::sole()->user_id);
    }

    public function test_the_form_validates(): void
    {
        $this->post(route('contact.store'), ['name' => '', 'email' => 'nope', 'message' => 'short'])
            ->assertSessionHasErrors(['name', 'email', 'message']);

        $this->assertSame(0, Enquiry::count());
    }

    public function test_the_honeypot_rejects_a_bot(): void
    {
        $this->post(route('contact.store'), [
            'name' => 'Spam Bot',
            'email' => 'bot@example.com',
            'message' => 'Buy cheap things at this link right now please.',
            'website' => 'http://spam.example.com',
        ])->assertSessionHasErrors('website');

        $this->assertSame(0, Enquiry::count());
    }

    public function test_a_form_submitted_impossibly_fast_is_rejected(): void
    {
        $this->post(route('contact.store'), [
            'name' => 'Too Quick',
            'email' => 'quick@example.com',
            'message' => 'This was submitted a fraction of a second after loading.',
            'rendered_at' => now()->timestamp,
        ])->assertSessionHasErrors('message');

        $this->assertSame(0, Enquiry::count());
    }

    // -----------------------------------------------------------------------
    // The staff inbox -- so the form is not a black hole
    // -----------------------------------------------------------------------

    public function test_staff_can_read_an_enquiry_and_it_is_marked_read(): void
    {
        $enquiry = Enquiry::factory()->create();
        $staff = $this->staffMember();

        $this->actingAs($staff)
            ->get(route('staff.enquiries.show', $enquiry))
            ->assertOk()
            ->assertSee($enquiry->message);

        $enquiry->refresh();

        $this->assertFalse($enquiry->isUnread());
        $this->assertSame($staff->id, $enquiry->read_by_user_id);
    }

    public function test_the_inbox_puts_unread_messages_first(): void
    {
        Enquiry::factory()->read()->create(['subject' => 'Older and read']);
        Enquiry::factory()->create(['subject' => 'Newer and unread']);

        $html = $this->actingAs($this->staffMember())
            ->get(route('staff.enquiries.index'))
            ->assertOk()
            ->getContent();

        $this->assertLessThan(
            strpos($html, 'Older and read'),
            strpos($html, 'Newer and unread'),
            'Unread enquiries must sort above read ones.',
        );
    }

    public function test_a_customer_cannot_read_the_inbox(): void
    {
        $enquiry = Enquiry::factory()->create();

        // Guest first: actingAs persists for the remainder of the test, so a
        // signed-out assertion after it would silently run as the customer.
        $this->get(route('staff.enquiries.index'))->assertRedirect(route('login'));

        $this->actingAs($this->customer())->get(route('staff.enquiries.index'))->assertForbidden();
        $this->actingAs($this->customer())->get(route('staff.enquiries.show', $enquiry))->assertForbidden();
    }

    public function test_a_quoted_booking_reference_is_resolved_for_the_desk(): void
    {
        $reservation = Reservation::factory()
            ->forWindow($this->room(), $this->tomorrowAt(10), 6)
            ->create(['policy_version_id' => $this->policy->id]);

        $enquiry = Enquiry::factory()->create(['reservation_reference' => $reservation->reference]);

        $this->actingAs($this->staffMember())
            ->get(route('staff.enquiries.show', $enquiry))
            ->assertOk()
            ->assertSee($reservation->reference)
            ->assertSee('Open booking');
    }

    public function test_an_unknown_reference_is_reported_rather_than_erroring(): void
    {
        $enquiry = Enquiry::factory()->create(['reservation_reference' => 'BM-NOSUCH']);

        $this->actingAs($this->staffMember())
            ->get(route('staff.enquiries.show', $enquiry))
            ->assertOk()
            ->assertSee('No booking matches that reference.');
    }

    // -----------------------------------------------------------------------
    // SEO of the new pages
    // -----------------------------------------------------------------------

    public function test_the_new_pages_are_indexable_and_in_the_sitemap(): void
    {
        foreach ([route('about'), route('contact')] as $page) {
            $html = $this->get($page)->assertOk()->getContent();

            $this->assertStringContainsString('index, follow', $html);
            $this->assertStringContainsString('rel="canonical" href="'.$page.'"', $html);
        }

        $xml = $this->get('/sitemap.xml')->assertOk()->getContent();

        $this->assertStringContainsString('<loc>'.route('about').'</loc>', $xml);
        $this->assertStringContainsString('<loc>'.route('contact').'</loc>', $xml);
    }

    public function test_the_contact_page_publishes_contact_markup(): void
    {
        $html = $this->get(route('contact'))->assertOk()->getContent();

        preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $html, $m);

        $types = array_map(
            fn ($json) => json_decode($json, true)['@type'],
            $m[1],
        );

        $this->assertContains('ContactPage', $types);
    }
}
