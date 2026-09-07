<?php

namespace App\Http\Controllers;

use App\Models\RoomType;
use Illuminate\Http\Response;

/**
 * The XML sitemap and robots.txt.
 *
 * Generated rather than checked in, so adding a room type publishes its page
 * to search engines without anyone remembering to edit a file.
 *
 * Only genuinely public, indexable pages appear. Booking flows, account pages,
 * the staff and admin areas and parameterised availability results are all
 * excluded -- a sitemap that lists URLs marked noindex sends contradictory
 * signals and gets reported as an error in Search Console.
 */
class SitemapController extends Controller
{
    public function index(): Response
    {
        $urls = collect([
            ['loc' => url('/'), 'priority' => '1.0', 'changefreq' => 'weekly'],
            ['loc' => route('rooms.index'), 'priority' => '0.9', 'changefreq' => 'daily'],
            ['loc' => route('about'), 'priority' => '0.7', 'changefreq' => 'monthly'],
            ['loc' => route('contact'), 'priority' => '0.7', 'changefreq' => 'monthly'],
            ['loc' => route('availability'), 'priority' => '0.8', 'changefreq' => 'daily'],
        ]);

        $rooms = RoomType::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->map(fn (RoomType $type) => [
                'loc' => route('rooms.show', $type),
                'lastmod' => $type->updated_at?->toAtomString(),
                'priority' => '0.8',
                'changefreq' => 'monthly',
            ]);

        $xml = view('sitemap', ['urls' => $urls->concat($rooms)])->render();

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    public function robots(): Response
    {
        $lines = [
            'User-agent: *',
            '',
            '# Private, personal or duplicate. Nothing here belongs in an index.',
            'Disallow: /my/',
            'Disallow: /book',
            'Disallow: /staff/',
            'Disallow: /admin/',
            'Disallow: /profile',
            'Disallow: /login',
            'Disallow: /register',
            'Disallow: /password',
            'Disallow: /checkout-simulator/',
            'Disallow: /payments/',
            '',
            '# Search result pages: one URL per date, hour and package. Crawling',
            '# them burns budget on content that is stale within the hour.',
            'Disallow: /availability?',
            '',
            'Sitemap: '.route('sitemap'),
        ];

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
