<?php

namespace App\Http\Controllers;

use App\Models\DurationPackage;
use App\Models\RoomType;
use App\Services\AvailabilityService;
use App\Support\Availability;
use App\Support\Money;
use App\Support\StructuredData;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Public browsing of room types, with live availability.
 *
 * The page answers two questions at once: what the rooms are, and whether you
 * can have one. When a type is full for the requested window it says when it
 * is next free rather than stopping at "unavailable" -- a dead end loses the
 * visitor, a next free hour gives them something to click.
 *
 * Room descriptions are written for this site rather than lifted from a
 * listing site: duplicated OTA copy is the most common reason a hotel's own
 * pages rank below the OTAs selling it.
 */
class RoomTypeController extends Controller
{
    public function __construct(private readonly AvailabilityService $availability) {}

    public function index(Request $request): View
    {
        $packages = DurationPackage::active()->ordered()->get();

        // Default to the next bookable hour, so the page always shows a real
        // answer rather than an empty form.
        $package = $packages->firstWhere('id', $request->integer('duration_package_id'))
            ?? $packages->first();

        $startsAt = $this->requestedStart($request);
        $adults = max(1, $request->integer('adults', 1));
        $children = max(0, $request->integer('children', 0));

        $rows = $package
            ? $this->availability->overview($startsAt, $package, $adults + $children)
            : collect();

        $roomTypes = RoomType::active()
            ->ordered()
            ->with(['photos', 'amenities', 'packagePrices.durationPackage'])
            ->withCount('bookableRooms')
            ->get();

        return view('public.rooms.index', [
            'roomTypes' => $roomTypes,
            'packages' => $packages,
            'package' => $package,
            'rows' => $rows->keyBy(fn (Availability $row) => $row->roomType->id),
            'startsAt' => $startsAt,
            'adults' => $adults,
            'children' => $children,
            'anyAvailable' => $rows->contains(fn (Availability $row) => $row->isAvailable()),
            'lookaheadDays' => config('hotel.rooms_lookahead_days'),

            'title' => 'Rooms and hourly rates',
            'description' => sprintf(
                'Compare the %d room types at %s in %s. Hourly packages from %s, with live availability and the next free slot for anything fully booked.',
                $roomTypes->count(),
                config('hotel.name'),
                config('hotel.address.district'),
                $this->cheapest($roomTypes),
            ),
            'canonical' => route('rooms.index'),

            // The list itself is indexable; it is a stable page describing the
            // inventory. Only the availability panel changes with the query
            // string, and the canonical keeps every variant pointing here.
            'noindex' => false,
            'schema' => [
                StructuredData::breadcrumbs([
                    ['name' => 'Home', 'url' => url('/')],
                    ['name' => 'Rooms', 'url' => route('rooms.index')],
                ]),
            ],
        ]);
    }

    public function show(Request $request, RoomType $roomType): View
    {
        abort_unless($roomType->is_active, 404);

        $roomType->load(['photos', 'amenities', 'packagePrices.durationPackage']);

        $packages = DurationPackage::active()->ordered()->get();
        $package = $packages->firstWhere('id', $request->integer('duration_package_id')) ?? $packages->first();
        $startsAt = $this->requestedStart($request);

        $row = $package
            ? $this->availability->overview($startsAt, $package, $roomType->base_occupancy)
                ->first(fn (Availability $candidate) => $candidate->roomType->is($roomType))
            : null;

        return view('public.rooms.show', [
            'roomType' => $roomType,
            'packages' => $packages,
            'package' => $package,
            'row' => $row,
            'startsAt' => $startsAt,

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

    /**
     * The window the visitor asked about, or the next bookable hour.
     *
     * Anything in the past is pulled forward rather than rejected: a stale
     * link or a bookmark from yesterday should still show a useful page.
     */
    private function requestedStart(Request $request): CarbonImmutable
    {
        $nextHour = CarbonImmutable::now()->addHour()->startOfHour();

        if (! $request->filled(['date', 'hour'])) {
            return $nextHour;
        }

        try {
            $requested = CarbonImmutable::createFromFormat(
                'Y-m-d H',
                $request->string('date').' '.str_pad((string) $request->integer('hour'), 2, '0', STR_PAD_LEFT),
            )->startOfHour();
        } catch (\Throwable) {
            return $nextHour;
        }

        return $requested->isPast() ? $nextHour : $requested;
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
