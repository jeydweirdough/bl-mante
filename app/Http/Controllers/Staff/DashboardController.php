<?php

namespace App\Http\Controllers\Staff;

use App\Enums\ExtensionStatus;
use App\Enums\ReservationStatus;
use App\Enums\RoomStatus;
use App\Http\Controllers\Controller;
use App\Models\Reservation;
use App\Models\ReservationExtension;
use App\Models\Room;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * The front desk's daily board: who is arriving, who is in, who is leaving,
 * laid out by hour.
 *
 * Because rooms are booked as intervals rather than nights, the useful unit
 * here is the hour, not the day -- a room can turn over three times before
 * lunch.
 */
class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $day = $request->filled('date')
            ? CarbonImmutable::parse($request->string('date')->toString())->startOfDay()
            : CarbonImmutable::today();

        $dayEnd = $day->endOfDay();

        // One query for everything touching the day, then partitioned in
        // memory. Three separate queries over the same index would cost more
        // than filtering a few dozen rows.
        $touching = Reservation::query()
            ->with(['room', 'roomType', 'customer'])
            ->where('starts_at', '<=', $dayEnd)
            ->where('blocked_until', '>=', $day)
            ->whereIn('status', [
                ReservationStatus::Pending,
                ReservationStatus::Confirmed,
                ReservationStatus::CheckedIn,
                ReservationStatus::CheckedOut,
                ReservationStatus::NoShow,
            ])
            ->orderBy('starts_at')
            ->get();

        $arrivals = $touching->filter(
            fn (Reservation $r) => $r->starts_at->between($day, $dayEnd)
                && in_array($r->status, [ReservationStatus::Pending, ReservationStatus::Confirmed], true),
        )->values();

        $inHouse = $touching->where('status', ReservationStatus::CheckedIn)->values();

        $departures = $touching->filter(
            fn (Reservation $r) => $r->ends_at->between($day, $dayEnd)
                && in_array($r->status, [ReservationStatus::CheckedIn, ReservationStatus::CheckedOut], true),
        )->values();

        return view('staff.dashboard', [
            'day' => $day,
            'arrivals' => $arrivals,
            'inHouse' => $inHouse,
            'departures' => $departures,
            'byHour' => $this->groupByHour($touching, $day),
            'rooms' => Room::with('roomType')->orderBy('number')->get(),
            'awaitingCleaning' => Room::where('status', RoomStatus::Cleaning)->orderBy('number')->get(),
            'pendingExtensions' => ReservationExtension::query()
                ->where('status', ExtensionStatus::Requested)
                ->with(['reservation.room', 'reservation.roomType'])
                ->orderBy('requested_at')
                ->get(),
            'stats' => [
                'arrivals' => $arrivals->count(),
                'in_house' => $inHouse->count(),
                'departures' => $departures->count(),
                'unpaid' => $touching->where('balance_due_cents', '>', 0)->count(),
                'cleaning' => Room::where('status', RoomStatus::Cleaning)->count(),
            ],
        ]);
    }

    /**
     * Bucket the day into 24 hours, with each reservation appearing in every
     * hour its blocked window covers.
     *
     * The buffer is included on purpose: a room unavailable at 14:00 because
     * the previous guest left at 13:30 is exactly what the desk needs to see,
     * and hiding the turnover would make the board lie.
     *
     * @return Collection<int, array{hour:int, reservations:Collection<int, Reservation>}>
     */
    private function groupByHour(Collection $reservations, CarbonImmutable $day): Collection
    {
        return collect(range(0, 23))->map(function (int $hour) use ($reservations, $day) {
            $slotStart = $day->addHours($hour);
            $slotEnd = $slotStart->addHour();

            return [
                'hour' => $hour,
                'reservations' => $reservations->filter(
                    fn (Reservation $r) => $r->starts_at->lt($slotEnd) && $r->blocked_until->gt($slotStart),
                )->values(),
            ];
        });
    }
}
