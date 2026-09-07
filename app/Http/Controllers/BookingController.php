<?php

namespace App\Http\Controllers;

use App\Enums\PaymentMode;
use App\Enums\ReservationChannel;
use App\Http\Requests\StoreBookingRequest;
use App\Models\DurationPackage;
use App\Models\Extra;
use App\Models\RoomType;
use App\Services\AvailabilityService;
use App\Services\PolicyService;
use App\Services\PricingService;
use App\Services\ReservationService;
use App\Support\BookingRequest;
use App\Support\BookingWindow;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The customer booking flow.
 *
 * The form shows a live quote and the cancellation terms; the store method
 * hands everything to ReservationService, which is where the availability
 * re-check and the transaction live. Nothing here decides whether a room is
 * free -- if it did, that decision would be outside the transaction that
 * writes the row, which is exactly what must not happen.
 */
class BookingController extends Controller
{
    public function __construct(
        private readonly ReservationService $reservations,
        private readonly AvailabilityService $availability,
        private readonly PricingService $pricing,
        private readonly PolicyService $policies,
    ) {}

    public function create(Request $request): View|RedirectResponse
    {
        $roomType = RoomType::active()->find($request->integer('room_type_id'));
        $package = DurationPackage::active()->find($request->integer('duration_package_id'));

        if (! $roomType || ! $package || ! $request->filled(['date', 'hour'])) {
            return redirect()
                ->route('availability')
                ->with('status', 'Choose a date, a start time and a package first.');
        }

        $startsAt = CarbonImmutable::createFromFormat(
            'Y-m-d H',
            $request->string('date').' '.str_pad((string) $request->integer('hour'), 2, '0', STR_PAD_LEFT),
        )->startOfHour();

        $policy = $this->policies->current();
        $window = BookingWindow::forPackage($startsAt, $package, $policy);

        $adults = max(1, $request->integer('adults', 1));
        $children = max(0, $request->integer('children', 0));

        // Shown for information. It is not a hold, and it is checked again at
        // the moment of booking.
        $stillFree = $this->availability
            ->freeRooms($window, $roomType->id, $adults + $children)
            ->count();

        $extras = Extra::active()->ordered()->get();

        $quote = $this->pricing->quote(
            roomType: $roomType,
            package: $package,
            window: $window,
            extraSelections: [],
            partySize: $adults + $children,
            policy: $policy,
        );

        return view('booking.create', compact(
            'roomType', 'package', 'window', 'adults', 'children',
            'stillFree', 'extras', 'quote', 'policy',
        ) + ['paymentModes' => PaymentMode::cases()]);
    }

    public function store(StoreBookingRequest $request): RedirectResponse
    {
        $reservation = $this->reservations->book(new BookingRequest(
            roomType: $request->roomType(),
            package: $request->package(),
            startsAt: $request->startsAt(),
            paymentMode: $request->paymentMode(),
            adults: $request->integer('adults'),
            children: $request->integer('children'),
            customer: $request->user(),
            channel: ReservationChannel::Online,
            extraSelections: $request->extraSelections(),
            customerNotes: $request->input('customer_notes'),
        ));

        // Online payers go straight to checkout while the hold is running;
        // pay-at-property bookings are already confirmed and just need the
        // confirmation page.
        if ($reservation->payment_mode === PaymentMode::Online) {
            return redirect()->route('reservations.pay', $reservation);
        }

        return redirect()
            ->route('reservations.show', $reservation)
            ->with('status', 'Your booking is confirmed. Payment is due when you arrive.');
    }
}
