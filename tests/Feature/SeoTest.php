<?php

namespace Tests\Feature;

use App\Models\RoomType;
use App\Services\PolicyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\BuildsProperty;
use Tests\TestCase;

/**
 * The public pages' search-engine surface.
 *
 * Meta tags and structured data are the kind of thing that breaks silently:
 * nothing errors, the page still renders, and it is months before anyone
 * notices the site stopped being indexable. These assertions are the alarm.
 */
class SeoTest extends TestCase
{
    use BuildsProperty, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildProperty(roomCount: 3);
    }

    /** @return array<int, array<string, mixed>> The JSON-LD graphs on a page. */
    private function structuredData(string $html): array
    {
        preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $html, $matches);

        return array_map(
            fn (string $json) => json_decode($json, true, 512, JSON_THROW_ON_ERROR),
            $matches[1],
        );
    }

    private function meta(string $html, string $name): ?string
    {
        preg_match('#<meta name="'.preg_quote($name, '#').'" content="([^"]*)"#', $html, $m);

        return $m[1] ?? null;
    }

    // -----------------------------------------------------------------------
    // The essentials, on every public page
    // -----------------------------------------------------------------------

    public static function publicPages(): array
    {
        return [
            'home' => ['/'],
            'rooms index' => ['/rooms'],
            'availability' => ['/availability'],
        ];
    }

    #[DataProvider('publicPages')]
    public function test_every_public_page_is_indexable_and_described(string $path): void
    {
        $html = $this->get($path)->assertOk()->getContent();

        $this->assertStringContainsString('<title>', $html);
        $this->assertStringContainsString('rel="canonical"', $html);
        $this->assertStringContainsString('index, follow', $this->meta($html, 'robots'));

        $description = $this->meta($html, 'description');
        $this->assertNotEmpty($description, "$path has no meta description.");
        $this->assertLessThanOrEqual(
            160,
            strlen($description),
            "$path has a meta description Google will truncate.",
        );
    }

    #[DataProvider('publicPages')]
    public function test_every_public_page_has_social_card_tags(string $path): void
    {
        $html = $this->get($path)->assertOk()->getContent();

        foreach (['og:title', 'og:description', 'og:url', 'og:image'] as $property) {
            $this->assertStringContainsString(
                '<meta property="'.$property.'"',
                $html,
                "$path is missing $property.",
            );
        }

        $this->assertStringContainsString('name="twitter:card"', $html);
    }

    public function test_each_page_has_exactly_one_h1(): void
    {
        foreach (['/', '/rooms/'.$this->roomType->slug] as $path) {
            $html = $this->get($path)->assertOk()->getContent();

            $this->assertSame(
                1,
                preg_match_all('#<h1[\s>]#', $html),
                "$path should have exactly one h1.",
            );
        }
    }

    // -----------------------------------------------------------------------
    // Structured data
    // -----------------------------------------------------------------------

    public function test_the_homepage_publishes_hotel_website_and_faq_markup(): void
    {
        $graphs = $this->structuredData($this->get('/')->assertOk()->getContent());

        $types = array_column($graphs, '@type');

        $this->assertContains('Hotel', $types);
        $this->assertContains('WebSite', $types);
        $this->assertContains('FAQPage', $types);
    }

    public function test_the_hotel_markup_carries_a_locatable_address(): void
    {
        $hotel = collect($this->structuredData($this->get('/')->getContent()))
            ->firstWhere('@type', 'Hotel');

        $this->assertSame(config('hotel.name'), $hotel['name']);
        $this->assertSame(config('hotel.contact_phone'), $hotel['telephone']);
        $this->assertSame('PostalAddress', $hotel['address']['@type']);
        $this->assertNotEmpty($hotel['address']['streetAddress']);
        $this->assertNotEmpty($hotel['address']['addressLocality']);
        $this->assertSame('GeoCoordinates', $hotel['geo']['@type']);
        $this->assertIsFloat($hotel['geo']['latitude']);
    }

    /**
     * The guardrail that matters most here. Publishing a rating for reviews
     * the site does not collect and display is a policy violation and a
     * fabricated claim about real guests -- so if someone adds it, this fails.
     */
    public function test_no_review_or_rating_markup_is_published(): void
    {
        $json = json_encode($this->structuredData($this->get('/')->getContent()));

        $this->assertStringNotContainsString('aggregateRating', $json);
        $this->assertStringNotContainsString('"review"', $json);
        $this->assertStringNotContainsString('reviewCount', $json);
    }

    public function test_a_room_page_publishes_its_own_room_markup(): void
    {
        $html = $this->get('/rooms/'.$this->roomType->slug)->assertOk()->getContent();
        $graphs = $this->structuredData($html);
        $types = array_column($graphs, '@type');

        $this->assertContains('HotelRoom', $types);
        $this->assertContains('BreadcrumbList', $types);

        $room = collect($graphs)->firstWhere('@type', 'HotelRoom');

        $this->assertSame($this->roomType->name, $room['name']);
        $this->assertSame($this->roomType->max_occupancy, $room['occupancy']['maxValue']);
        $this->assertArrayHasKey('offers', $room, 'A priced room type should publish an offer.');
    }

    public function test_room_pages_do_not_share_one_description(): void
    {
        $other = RoomType::factory()->create(['short_description' => 'A completely different room.']);
        $other->packagePrices()->create([
            'duration_package_id' => $this->sixHours->id,
            'price_cents' => 123400,
            'currency' => config('hotel.currency'),
            'is_active' => true,
        ]);

        $first = $this->meta($this->get('/rooms/'.$this->roomType->slug)->getContent(), 'description');
        $second = $this->meta($this->get('/rooms/'.$other->slug)->getContent(), 'description');

        $this->assertNotSame(
            $first,
            $second,
            'Room pages sharing a description look like duplicate content.',
        );
    }

    // -----------------------------------------------------------------------
    // What must stay out of the index
    // -----------------------------------------------------------------------

    public function test_availability_results_are_not_indexed_and_point_home(): void
    {
        $html = $this->get(route('availability', [
            'date' => now()->addDay()->format('Y-m-d'),
            'hour' => 14,
            'duration_package_id' => $this->sixHours->id,
        ]))->assertOk()->getContent();

        $this->assertStringContainsString('noindex', $this->meta($html, 'robots'));

        // Every result set canonicalises back to the bare search page rather
        // than competing with itself across thousands of parameter variants.
        $this->assertStringContainsString(
            'href="'.route('availability').'"',
            $html,
        );
    }

    public function test_private_pages_are_not_indexed(): void
    {
        $customer = $this->customer();

        $html = $this->actingAs($customer)->get(route('reservations.index'))->assertOk()->getContent();

        $this->assertStringContainsString('noindex', $this->meta($html, 'robots'));
    }

    // -----------------------------------------------------------------------
    // Crawl directives
    // -----------------------------------------------------------------------

    public function test_robots_txt_blocks_private_areas_and_names_the_sitemap(): void
    {
        $body = $this->get('/robots.txt')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->getContent();

        foreach (['/my/', '/staff/', '/admin/', '/book', '/checkout-simulator/'] as $path) {
            $this->assertStringContainsString("Disallow: $path", $body);
        }

        $this->assertStringContainsString('Sitemap: '.route('sitemap'), $body);
    }

    public function test_the_sitemap_lists_public_pages_only(): void
    {
        $xml = $this->get('/sitemap.xml')->assertOk()->getContent();

        $this->assertStringContainsString('<loc>'.url('/').'</loc>', $xml);
        $this->assertStringContainsString('<loc>'.route('rooms.index').'</loc>', $xml);
        $this->assertStringContainsString('<loc>'.route('rooms.show', $this->roomType).'</loc>', $xml);

        // Nothing private, and nothing marked noindex.
        foreach (['/staff', '/admin', '/my/', '/book'] as $path) {
            $this->assertStringNotContainsString($path.'</loc>', $xml);
        }

        $this->assertNotFalse(simplexml_load_string($xml), 'The sitemap must be valid XML.');
    }

    public function test_an_inactive_room_type_is_not_listed_or_reachable(): void
    {
        $hidden = RoomType::factory()->inactive()->create();

        $this->assertStringNotContainsString($hidden->slug, $this->get('/sitemap.xml')->getContent());
        $this->get('/rooms/'.$hidden->slug)->assertNotFound();
    }

    // -----------------------------------------------------------------------
    // Copy that cannot drift from the rules
    // -----------------------------------------------------------------------

    /**
     * The FAQ answers quote the live policy. If an admin changes the refund
     * window, the homepage must say the new number -- otherwise the site is
     * promising terms the booking engine will refuse to honour.
     */
    public function test_the_faq_quotes_the_policy_actually_in_force(): void
    {
        $this->get('/')->assertOk()->assertSee(
            'more than '.$this->policy->full_refund_hours_before.' hours',
            escape: false,
        );

        app(PolicyService::class)->publish(
            ['full_refund_hours_before' => 72, 'partial_refund_hours_before' => 8],
            $this->admin(),
        );

        $this->get('/')->assertOk()->assertSee('more than 72 hours', escape: false);
    }

    public function test_no_unreplaced_content_tokens_reach_the_page(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        // Strip the markup so a legitimate ":" in an attribute is not a match,
        // then look for the placeholder pattern the content file uses.
        $text = strip_tags($html);

        $this->assertDoesNotMatchRegularExpression(
            '/:(hotel|city|district|phone|email|packages|buffer_minutes|hold_minutes|downpayment_percent|full_refund_hours|partial_refund_hours|grace_minutes|reschedule_hours|cheapest_price)\b/',
            $text,
            'A content token was rendered to the page without being replaced.',
        );
    }

    public function test_the_homepage_carries_enough_content_to_rank(): void
    {
        $text = strip_tags($this->get('/')->getContent());
        $words = str_word_count($text);

        // Thin homepages that are a photograph and a booking widget give a
        // search engine nothing to work with. This is a floor, not a target.
        $this->assertGreaterThan(
            800,
            $words,
            "The homepage has only $words words of copy.",
        );
    }
}
