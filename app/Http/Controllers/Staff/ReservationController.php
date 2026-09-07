<?php

namespace App\Http\Controllers\Staff;

use App\Enums\CancellationInitiator;
use App\Enums\PaymentMode;
use App\Enums\ReservationChannel;
use App\Enums\ReservationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\StoreWalkInRequest;
use App\Models\DurationPackage;
use App\Models\Extra;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use App\Services\AvailabilityService;
use App\Services\PolicyService;
use App\Services\RefundCalculator;
use App\Services\ReservationService;
use App\Support\BookingRequest;
use App\Support\BookingWindow;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The desk's view of reservations: walk-ins, phone bookings, check-in and
 * check-out, room reassignment and no-shows.
 */
class ReservationController extends Controller
{
    public function __construct(
        private readonly ReservationService $reservations,
        private readonly AvailabilityService $availability,
        private readonly PolicyService $policies,
        private readonly RefundCalculator $refunds,
    ) {}

    public function index(Request $request): View
    {
        $reservations = Reservation::query()
            ->with(['room', 'roomType', 'customer'])
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = trim($request->string('q')->toString());

                $query->where(function ($q) use ($term) {
                    $q->where('reference', 'like', "%{$term}%")
                        ->orWhere('guest_name', 'like', "%{$term}%")
                        ->orWhere('guest_phone', 'like', "%{$term}%")
                        ->orWhere('guest_email', 'like', "%{$term}%")
                        ->orWhereHas('customer', fn ($c) => $c
                            ->where('name', 'like', "%{$term}%")
                            ->orWhere('email', 'like', "%{$term}%"));
                });
            })
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('room_id'), fn ($q) => $q->where('room_id', $request->integer('room_id')))
            ->orderByDesc('starts_at')
            ->paginate(25)
            ->withQueryString();

        return view('staff.reservations.index', [
            'reservations' => $reservations,
            'statuses' => ReservationStatus::cases(),
            'rooms' => Room::orderBy('number')->get(),
        ]);
    }

    public function show(Reservation $reservation): View
    {
        $this->authorize('view', $reservation);

        $reservation->load([
            'room.roomType', 'roomType', 'customer', 'createdBy', 'policyVersion',
            'extras.addedBy', 'extensions.requestedBy', 'extensions.decidedBy',
            'payments.recordedBy', 'refunds.processedBy',
            'statusTransitions.actor', 'assignments.fromRoom', 'assignments.toRoom',
        ]);

        // Alternative rooms for reassignment, checked against this booking's
        // own window and excluding itself.
        $alternatives = $reservation->status->occupiesRoom()
            ? $this->availability->freeRooms(
                BookingWindow::fromReservation($reservation),
                null,
                $reservation->partySize(),
            )->reject(fn (Room $r) => $r->id === $reservation->room_id)->values()
            : collect();

        return view('staff.reservations.show', [
            'reservation' => $reservation,
            'alternatives' => $alternatives,
            'extras' => Extra::active()->ordered()->get(),
            'refundOutcome' => $reservation->isCancellable()
                ? $this->refunds->forCancellation($reservation)
                : null,
        ]);
    }

    /**
     * The walk-in and phone booking form.
     *
     * Staff pick the physical room explicitly, which customers never do -- at
     * the counter the guest is often looking at a specific room, and the desk
     * needs the ability to honour that.
     */
    public function create(Request $request): View
    {
        $packages = DurationPackage::active()->ordered()->get();
        $roomTypes = RoomType::active()->ordered()->with('packagePrices')->get();

        $startsAt = $request->filled(['date', 'hour'])
            ? CarbonImmutable::createFromFormat(
                'Y-m-d H',
                $request->string('date').' '.str_pad((string) $request->integer('hour'), 2, '0', STR_PAD_LEFT),
            )->startOfHour()
            : CarbonImmutable::now()->startOfHour();

        $package = $packages->firstWhere('id', $request->integer('duration_package_id')) ?? $packages->first();

        $freeRooms = collect();
        $window = null;

        if ($package) {
            $window = BookingWindow::forPackage($startsAt, $package, $this->policies->current());
            $freeRooms = $this->availability->freeRooms($window, $request->integer('room_type_id') ?: null);
        }

        return view('staff.reservations.create', [
            'packages' => $packages,
            'roomTypes' => $roomTypes,
            'freeRooms' => $freeRooms,
            'window' => $window,
            'startsAt' => $startsAt,
            'selectedPackage' => $package,
            'extras' => Extra::active()->ordered()->get(),
            'paymentModes' => PaymentMode::cases(),
            'channels' => [ReservationChannel::WalkIn, ReservationChannel::Phone],
            'similar' => $window
                ? $this->availability->similarGuestReservations(
                    $request->input('guest_name'),
                    $request->input('guest_phone'),
                    $window,
                )
                : collect(),
        ]);
    }

    public function store(StoreWalkInRequest $request): RedirectResponse
    {
        $this->authorize('createForGuest', Reservation::class);

        $reservation = $this->reservations->book(new BookingRequest(
            roomType: $request->roomType(),
            package: $request->package(),
            startsAt: $request->startsAt(),
            paymentMode: $request->paymentMode(),
            adults: $request->integer('adults'),
            children: $request->integer('children'),
            customer: $request->existingCustomer(),
            guestName: $request->input('guest_name'),
            guestEmail: $request->input('guest_email'),
            guestPhone: $request->input('guest_phone'),
            channel: $request->channel(),
            createdBy: $request->user(),
            preferredRoomId: $request->integer('room_id') ?: null,
            extraSelections: $request->extraSelections(),
            customerNotes: $request->input('internal_notes'),
        ));

        return redirect()
            ->route('staff.reservations.show', $reservation)
            ->with('status', "Booking {$reservation->reference} created for room {$reservation->room->number}.");
    }

    public function checkIn(Request $request, Reservation $reservation): RedirectResponse
    {
        $this->authorize('checkIn', $reservation);

        $this->reservations->checkIn($reservation, $request->user());

        return back()->with('status', "{$reservation->guestName()} checked into room {$reservation->room->number}.");
    }

    public function checkOut(Request $request, Reservation $reservation): RedirectResponse
    {
        $this->authorize('checkOut', $reservation);

        $this->reservations->checkOut($reservation, $request->user());

        $message = $reservation->balance_due_cents > 0
            ? 'Checked out with an outstanding balance. Settle it before the guest leaves.'
            : 'Checked out. The room is now in cleaning.';

        return back()->with($reservation->balance_due_cents > 0 ? 'unavailable' : 'status', $message);
    }

    public function markNoShow(Request $request, Reservation $reservation): RedirectResponse
    {
        $this->authorize('markNoShow', $reservation);

        $this->reservations->markNoShow($reservation, $request->user());

        return back()->with('status', "{$reservation->reference} marked as a no-show. The room has been released.");
    }

    public function assignRoom(Request $request, Reservation $reservation): RedirectResponse
    {
        $this->authorize('assignRoom', $reservation);

        $validated = $request->validate([
            'room_id' => ['required', 'exists:rooms,id'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $target = Room::findOrFail($validated['room_id']);

        $this->reservations->reassignRoom(
            $reservation,
            $target,
            $request->user(),
            $validated['reason'] ?? null,
        );

        return back()->with('status', "Moved to room {$target->number}.");
    }

    /**
     * A cancellation the hotel initiated -- overbooked event, maintenance,
     * anything on our side. Refunded in full regardless of timing.
     */
    public function cancel(Request $request, Reservation $reservation): RedirectResponse
    {
        $this->authorize('cancel', $reservation);

        $validated = $request->validate([
            'initiator' => ['required', 'in:customer,hotel'],
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $outcome = $this->reservations->cancel(
            reservation: $reservation,
            initiator: CancellationInitiator::from($validated['initiator']),
            actor: $request->user(),
            reason: $validated['reason'],
        );

        return back()->with('status', 'Cancelled. '.$outcome->explanation);
    }

    public function addExtra(Request $request, Reservation $reservation): RedirectResponse
    {
        $this->authorize('addExtra', $reservation);

        $validated = $request->validate([
            'extra_id' => ['required', 'exists:extras,id'],
            'quantity' => ['required', 'integer', 'between:1,20'],
        ]);

        $this->reservations->addExtra(
            $reservation,
            Extra::findOrFail($validated['extra_id']),
            $validated['quantity'],
            $request->user(),
        );

        return back()->with('status', 'Extra added to the booking.');
    }
}
