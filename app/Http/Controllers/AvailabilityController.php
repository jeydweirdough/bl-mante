<?php

namespace App\Http\Controllers;

use App\Http\Requests\AvailabilitySearchRequest;
use App\Models\DurationPackage;
use App\Models\RoomType;
use App\Services\AvailabilityService;
use App\Services\PolicyService;
use App\Support\BookingWindow;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * The public availability search.
 *
 * What this returns is a snapshot, not a promise. Every result is re-verified
 * inside the transaction that actually writes the reservation, because the
 * time between looking and booking is exactly when someone else takes the
 * room. See ReservationService::book.
 */
class AvailabilityController extends Controller
{
    public function __construct(
        private readonly AvailabilityService $availability,
        private readonly PolicyService $policies,
    ) {}

    public function index(Request $request): View
    {
        $packages = DurationPackage::active()->ordered()->get();
        $roomTypes = RoomType::active()->ordered()->get();

        // An unsubmitted form still renders the search panel, with sensible
        // defaults, rather than an empty page.
        if (! $request->hasAny(['date', 'hour', 'duration_package_id'])) {
            return view('public.availability', [
                'packages' => $packages,
                'roomTypes' => $roomTypes,
                'results' => null,
                'window' => null,
                'defaults' => $this->defaults($packages),

                'title' => 'Check availability',
                'description' => 'See which rooms are free at '.config('hotel.name')
                    .' for any date, start hour and length of stay. No account needed to search.',
                'canonical' => route('availability'),
                'noindex' => false,
            ]);
        }

        $validated = app(AvailabilitySearchRequest::class);

        $package = $validated->package();
        $startsAt = $validated->startsAt();
        $partySize = $validated->partySize();

        $window = BookingWindow::forPackage($startsAt, $package, $this->policies->current());

        $results = $this->availability->search(
            startsAt: $startsAt,
            package: $package,
            roomTypeId: $validated->filled('room_type_id') ? $validated->integer('room_type_id') : null,
            partySize: $partySize,
        );

        return view('public.availability', [
            'packages' => $packages,
            'roomTypes' => $roomTypes,
            'results' => $results,
            'window' => $window,
            'package' => $package,
            'adults' => $validated->integer('adults', 1),
            'children' => $validated->integer('children', 0),
            'selectedRoomTypeId' => $validated->integer('room_type_id') ?: null,
            'defaults' => [
                'date' => $startsAt->format('Y-m-d'),
                'hour' => $startsAt->hour,
                'duration_package_id' => $package->id,
            ],

            'title' => 'Rooms free on '.$startsAt->format('j M Y').' from '.$startsAt->format('H:i'),

            // A result page is a different URL for every date, hour, package
            // and party size -- effectively unlimited near-identical pages
            // whose content changes hourly. Indexing them wastes crawl budget
            // on URLs that will be stale before anyone clicks them, so the
            // parameterless /availability page is the one that gets indexed
            // and every result set points its canonical back at it.
            'noindex' => true,
            'canonical' => route('availability'),
        ]);
    }

    /**
     * The next bookable hour, so a first-time visitor sees a search that would
     * actually return something rather than an empty form.
     */
    private function defaults($packages): array
    {
        $next = CarbonImmutable::now()->addHour()->startOfHour();

        return [
            'date' => $next->format('Y-m-d'),
            'hour' => $next->hour,
            'duration_package_id' => $packages->first()?->id,
        ];
    }
}
