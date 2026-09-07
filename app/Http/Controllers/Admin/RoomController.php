<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ReservationStatus;
use App\Http\Controllers\Controller;
use App\Models\Room;
use App\Models\RoomType;
use App\Services\AuditLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RoomController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(): View
    {
        $this->authorize('viewAny', Room::class);

        return view('admin.rooms.index', [
            'rooms' => Room::with('roomType')
                ->withCount(['reservations as upcoming_count' => fn ($q) => $q
                    ->whereIn('status', ReservationStatus::occupyingValues())
                    ->where('blocked_until', '>', now())])
                ->orderBy('number')
                ->get(),
            'roomTypes' => RoomType::ordered()->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Room::class);

        $validated = $request->validate([
            'room_type_id' => ['required', 'exists:room_types,id'],
            'number' => ['required', 'string', 'max:20', 'unique:rooms,number'],
            'floor' => ['nullable', 'integer', 'between:0,100'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $room = Room::create($validated + ['is_bookable' => true]);

        $this->audit->record('room.created', $room, $request->user(), after: $validated);

        return back()->with('status', "Room {$room->number} added.");
    }

    public function update(Request $request, Room $room): RedirectResponse
    {
        $this->authorize('update', $room);

        $validated = $request->validate([
            'room_type_id' => ['required', 'exists:room_types,id'],
            'number' => ['required', 'string', 'max:20', Rule::unique('rooms', 'number')->ignore($room)],
            'floor' => ['nullable', 'integer', 'between:0,100'],
            'is_bookable' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $before = $room->only(['room_type_id', 'number', 'floor', 'is_bookable']);

        $room->update($validated + ['is_bookable' => $request->boolean('is_bookable')]);

        $this->audit->record('room.updated', $room, $request->user(), $before, $validated);

        // Reclassifying a room does not reprice bookings already on it: each
        // reservation carries the room type it was sold as.
        $message = $before['room_type_id'] !== $room->room_type_id
            ? "Room {$room->number} updated. Existing bookings keep the room type they were sold as."
            : "Room {$room->number} updated.";

        return back()->with('status', $message);
    }
}
