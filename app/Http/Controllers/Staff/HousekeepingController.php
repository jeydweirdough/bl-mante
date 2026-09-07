<?php

namespace App\Http\Controllers\Staff;

use App\Enums\ReservationStatus;
use App\Enums\RoomStatus;
use App\Http\Controllers\Controller;
use App\Models\Room;
use App\Services\RoomStateService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The housekeeping board.
 *
 * Everything here concerns the physical state of a room right now. None of it
 * decides whether a future window is bookable -- that comes from the
 * reservations table.
 */
class HousekeepingController extends Controller
{
    public function __construct(private readonly RoomStateService $rooms) {}

    public function index(): View
    {
        $rooms = Room::query()
            ->with([
                'roomType',
                'reservations' => fn ($q) => $q
                    ->whereIn('status', ReservationStatus::occupyingValues())
                    ->where('blocked_until', '>', now())
                    ->orderBy('starts_at')
                    ->limit(1),
            ])
            ->orderBy('number')
            ->get();

        return view('staff.housekeeping', [
            'rooms' => $rooms,
            'grouped' => $rooms->groupBy(fn (Room $room) => $room->status->value),
            'statuses' => RoomStatus::cases(),
        ]);
    }

    public function markCleaningComplete(Request $request, Room $room): RedirectResponse
    {
        $this->authorize('updateHousekeeping', $room);

        $this->rooms->markCleaningComplete($room, $request->user());

        return back()->with('status', "Room {$room->number} is back in service.");
    }

    /** Housekeeping can pull a room for cleaning outside the normal turnover. */
    public function markCleaning(Request $request, Room $room): RedirectResponse
    {
        $this->authorize('updateHousekeeping', $room);

        $this->rooms->markCleaning($room, null, $request->user());

        return back()->with('status', "Room {$room->number} marked for cleaning.");
    }

    public function takeOutOfService(Request $request, Room $room): RedirectResponse
    {
        $this->authorize('takeOutOfService', $room);

        $this->rooms->markOutOfService($room, $request->user());

        return back()->with('status', "Room {$room->number} is out of service and will not be offered.");
    }

    public function returnToService(Request $request, Room $room): RedirectResponse
    {
        $this->authorize('takeOutOfService', $room);

        $this->rooms->returnToService($room, $request->user());

        return back()->with('status', "Room {$room->number} is back in service.");
    }
}
