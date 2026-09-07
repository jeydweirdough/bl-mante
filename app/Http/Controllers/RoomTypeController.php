<?php

namespace App\Http\Controllers;

use App\Models\DurationPackage;
use App\Models\RoomType;
use App\Support\Money;
use App\Support\StructuredData;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Public browsing of room types, amenities, photos and package prices.
 * No account required, which is a requirement.
 *
 * Each room type is its own indexable page with its own title, description and
 * HotelRoom markup. Room descriptions are written for this site rather than
 * lifted from a listing site: duplicated OTA copy is the single most common
 * reason a hotel's own pages rank below the OTAs selling it.
 */
class RoomTypeController extends Controller
{
    public function index(): View
    {
        $roomTypes = RoomType::active()
            ->ordered()
            ->with(['photos', 'amenities', 'packagePrices.durationPackage'])
            ->withCount('bookableRooms')
            ->get();

        return view('public.rooms.index', [
            'roomTypes' => $roomTypes,
            'packages' => DurationPackage::active()->ordered()->get(),

            'title' => 'Rooms and hourly rates',
            'description' => sprintf(
                'Compare the %d room types at %s in %s. Hourly packages from %s, every room soundproofed with blackout curtains and a full-size desk.',
                $roomTypes->count(),
                config('hotel.name'),
                config('hotel.address.district'),
                $this->cheapest($roomTypes),
            ),
            'canonical' => route('rooms.index'),
            'noindex' => false,
            'schema' => [
                StructuredData::breadcrumbs([
                    ['name' => 'Home', 'url' => url('/')],
                    ['name' => 'Rooms', 'url' => route('rooms.index')],
                ]),
            ],
        ]);
    }

    public function show(RoomType $roomType): View
    {
        abort_unless($roomType->is_active, 404);

        $roomType->load(['photos', 'amenities', 'packagePrices.durationPackage']);

        return view('public.rooms.show', [
            'roomType' => $roomType,
            'packages' => DurationPackage::active()->ordered()->get(),

            'title' => $roomType->name.' — hourly rates',

            // Prefer the hand-written summary; fall back to a trimmed opening
            // of the long description rather than shipping the site default,
            // which would make every room page look identical to a crawler.
            'description' => Str::limit(
                $roomType->short_description
                    ?: strip_tags((string) $roomType->description)
                    ?: config('hotel.seo.default_description'),
                158,
                '',
            ),
            'canonical' => route('rooms.show', $roomType),
            'noindex' => false,
            'schema' => [
                StructuredData::hotelRoom($roomType),
                StructuredData::breadcrumbs([
                    ['name' => 'Home', 'url' => url('/')],
                    ['name' => 'Rooms', 'url' => route('rooms.index')],
                    ['name' => $roomType->name, 'url' => route('rooms.show', $roomType)],
                ]),
            ],
        ]);
    }

    /** The lowest package price across the listed types, for the meta description. */
    private function cheapest(Collection $roomTypes): string
    {
        $cents = $roomTypes
            ->flatMap(fn (RoomType $type) => $type->packagePrices->where('is_active', true)->pluck('price_cents'))
            ->min();

        return $cents ? Money::format((int) $cents) : 'a fair rate';
    }
}
