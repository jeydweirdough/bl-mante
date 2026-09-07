<?php

namespace App\Http\Controllers;

use App\Models\DurationPackage;
use App\Models\RoomType;
use App\Support\SiteContent;
use App\Support\StructuredData;
use Illuminate\Contracts\View\View;

/**
 * The public homepage.
 *
 * It is the property's website first and the booking engine's front door
 * second: a page that is only a search widget gives a visitor no reason to
 * trust the place and gives a search engine nothing to rank. The copy lives in
 * config/content.php, and every policy figure quoted in it is interpolated
 * from the live policy version by SiteContent.
 */
class HomeController extends Controller
{
    public function __construct(private readonly SiteContent $content) {}

    public function __invoke(): View
    {
        $faqs = $this->content->get('faqs', []);

        return view('public.home', [
            'roomTypes' => RoomType::active()
                ->ordered()
                ->with(['photos', 'amenities', 'packagePrices.durationPackage'])
                ->get(),

            'packages' => DurationPackage::active()->ordered()->get(),

            'c' => $this->content,
            'faqs' => $faqs,

            // The homepage carries the anchor entity for the whole site, so
            // every other page can reference it by @id rather than repeating
            // the address.
            'schema' => array_filter([
                StructuredData::hotel(),
                StructuredData::website(),
                StructuredData::faqPage($faqs),
            ]),

            'title' => null, // falls back to "<name> — <tagline>"
            'description' => config('hotel.seo.default_description'),
            'canonical' => url('/'),

            // The homepage is indexable whether or not somebody happens to be
            // signed in; the layout's default would otherwise hide it from a
            // logged-in crawl of the site.
            'noindex' => false,
        ]);
    }
}
