<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use App\Models\ReservationExtension;
use App\Services\ReservationService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Extension decisions.
 *
 * Approval re-checks availability under a room lock, because time passes
 * between a guest asking and the desk answering. If the room is taken, the
 * extension is refused -- the system never relocates the guest who booked the
 * following slot.
 */
class ExtensionController extends Controller
{
    public function __construct(private readonly ReservationService $reservations) {}

    /** Staff may also raise an extension on a guest's behalf at the desk. */
    public function store(Request $request, Reservation $reservation): RedirectResponse
    {
        $this->authorize('requestExtension', $reservation);

        $validated = $request->validate([
            'additional_hours' => ['required', 'integer', 'between:1,12'],
        ]);

        $extension = $this->reservations->requestExtension(
            $reservation,
            $validated['additional_hours'],
            $request->user(),
        );

        return back()->with('status', sprintf(
            'Extension of %d hour%s raised. Charge would be %s.',
            $extension->additional_hours,
            $extension->additional_hours === 1 ? '' : 's',
            Money::format($extension->charge_cents),
        ));
    }

    public function approve(Request $request, ReservationExtension $extension): RedirectResponse
    {
        $this->authorize('decideExtension', $extension->reservation);

        $this->reservations->approveExtension($extension, $request->user());

        return back()->with('status', sprintf(
            'Extended to %s. %s added to the balance.',
            $extension->new_ends_at->format('H:i'),
            Money::format($extension->charge_cents),
        ));
    }

    public function refuse(Request $request, ReservationExtension $extension): RedirectResponse
    {
        $this->authorize('decideExtension', $extension->reservation);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $this->reservations->refuseExtension($extension, $request->user(), $validated['reason']);

        return back()->with('status', 'Extension refused and the guest has been told why.');
    }
}
