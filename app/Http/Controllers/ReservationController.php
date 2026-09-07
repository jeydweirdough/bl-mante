<?php

namespace App\Http\Controllers;

use App\Enums\CancellationInitiator;
use App\Models\Extra;
use App\Models\Reservation;
use App\Services\AvailabilityService;
use App\Services\RefundCalculator;
use App\Services\ReservationService;
use App\Support\BookingWindow;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * A customer's own bookings.
 *
 * Every action is authorised through ReservationPolicy. The policy answers
 * "may this user", the model answers "does this make sense for this booking";
 * neither question is answered inline here.
 */
class ReservationController extends Controller
{
    public function __construct(
        private readonly ReservationService $reservations,
        private readonly RefundCalculator $refunds,
        private readonly AvailabilityService $availability,
    ) {}

    public function index(Request $request): View
    {
        $reservations = Reservation::query()
            ->where('user_id', $request->user()->id)
            ->with(['roomType', 'room', 'policyVersion'])
            ->orderByDesc('starts_at')
            ->paginate(15);

        [$upcoming, $past] = $reservations->getCollection()->partition(
            fn (Reservation $r) => $r->status->occupiesRoom(),
        );

        return view('reservations.index', compact('reservations', 'upcoming', 'past'));
    }

    public function show(Reservation $reservation): View
    {
        $this->authorize('view', $reservation);

        $reservation->load([
            'roomType', 'room', 'policyVersion', 'extras', 'extensions.decidedBy',
            'payments.recordedBy', 'refunds', 'statusTransitions.actor',
        ]);

        return view('reservations.show', [
            'reservation' => $reservation,
            'refundOutcome' => $reservation->isCancellable()
                ? $this->refunds->forCancellation($reservation)
                : null,
            'freeRescheduleAvailable' => $reservation->isReschedulable()
                && $this->refunds->freeRescheduleAvailable($reservation),
            'availableExtras' => $reservation->canAcceptExtras()
                ? Extra::active()->ordered()->get()
                : collect(),
        ]);
    }

    /**
     * The cancellation confirmation screen.
     *
     * The refund figure and the reason are shown before anything is committed,
     * so a guest cancelling four hours out is not surprised by the tier.
     */
    public function confirmCancel(Reservation $reservation): View
    {
        $this->authorize('cancel', $reservation);

        return view('reservations.cancel', [
            'reservation' => $reservation,
            'outcome' => $this->refunds->forCancellation($reservation),
        ]);
    }

    public function cancel(Request $request, Reservation $reservation): RedirectResponse
    {
        $this->authorize('cancel', $reservation);

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        // A customer cancelling their own booking is always a customer-initiated
        // cancellation, even when a staff member clicks the button on their
        // behalf. Hotel-initiated cancellations, which refund in full, are a
        // separate action on the staff screen.
        $outcome = $this->reservations->cancel(
            reservation: $reservation,
            initiator: CancellationInitiator::Customer,
            actor: $request->user(),
            reason: $validated['reason'] ?? null,
        );

        return redirect()
            ->route('reservations.show', $reservation)
            ->with('status', 'Booking cancelled. '.$outcome->explanation);
    }

    public function editReschedule(Reservation $reservation): View
    {
        $this->authorize('reschedule', $reservation);

        return view('reservations.reschedule', [
            'reservation' => $reservation,
            'freeAvailable' => $this->refunds->freeRescheduleAvailable($reservation),
            'outcomeIfCancelled' => $this->refunds->forCancellation($reservation),
        ]);
    }

    public function reschedule(Request $request, Reservation $reservation): RedirectResponse
    {
        $this->authorize('reschedule', $reservation);

        $validated = $request->validate([
            'date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'hour' => ['required', 'integer', 'between:0,23'],
        ]);

        $newStart = CarbonImmutable::createFromFormat(
            'Y-m-d H',
            $validated['date'].' '.str_pad((string) $validated['hour'], 2, '0', STR_PAD_LEFT),
        )->startOfHour();

        $this->reservations->reschedule($reservation, $newStart, $request->user());

        return redirect()
            ->route('reservations.show', $reservation)
            ->with('status', 'Your booking has been moved. This was your one free reschedule.');
    }

    /**
     * Check a proposed new time without committing to it, so the reschedule
     * form can say yes or no before the guest presses the button.
     */
    public function checkReschedule(Request $request, Reservation $reservation)
    {
        $this->authorize('reschedule', $reservation);

        $validated = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'hour' => ['required', 'integer', 'between:0,23'],
        ]);

        $newStart = CarbonImmutable::createFromFormat(
            'Y-m-d H',
            $validated['date'].' '.str_pad((string) $validated['hour'], 2, '0', STR_PAD_LEFT),
        )->startOfHour();

        $window = BookingWindow::fromReservation($reservation)->movedTo($newStart);

        $free = $newStart->isFuture() && $this->availability
            ->freeRooms($window, $reservation->room_type_id, $reservation->partySize())
            ->isNotEmpty();

        return response()->json([
            'available' => $free,
            'window' => $window->describeStay(),
            'message' => $free
                ? 'That slot is free.'
                : 'Nothing of that room type is free at that time.',
        ]);
    }

    public function requestExtension(Request $request, Reservation $reservation): RedirectResponse
    {
        $this->authorize('requestExtension', $reservation);

        $validated = $request->validate([
            'additional_hours' => ['required', 'integer', 'between:1,12'],
        ]);

        $this->reservations->requestExtension(
            $reservation,
            $validated['additional_hours'],
            $request->user(),
        );

        return redirect()
            ->route('reservations.show', $reservation)
            ->with('status', 'Extension requested. The front desk will confirm shortly.');
    }

    public function addExtra(Request $request, Reservation $reservation): RedirectResponse
    {
        $this->authorize('addExtra', $reservation);

        $validated = $request->validate([
            'extra_id' => ['required', Rule::exists('extras', 'id')->where('is_active', true)],
            'quantity' => ['required', 'integer', 'between:1,20'],
        ]);

        $this->reservations->addExtra(
            $reservation,
            Extra::findOrFail($validated['extra_id']),
            $validated['quantity'],
            $request->user(),
        );

        return redirect()
            ->route('reservations.show', $reservation)
            ->with('status', 'Added. The balance on your booking has been updated.');
    }
}
