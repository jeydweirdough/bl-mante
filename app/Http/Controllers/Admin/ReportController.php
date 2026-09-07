<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Enums\ReservationStatus;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Reservation;
use App\Models\Room;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Occupancy, revenue and payment reporting.
 *
 * Occupancy is measured in room-hours rather than room-nights, because rooms
 * here are sold by the hour. The denominator is every bookable room for every
 * hour in the period; the numerator is the hours actually sold. The turnover
 * buffer is deliberately excluded from the numerator -- it is unsellable time,
 * not revenue, and counting it would flatter the figure.
 */
class ReportController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('view-reports');

        [$from, $to] = $this->period($request);

        $reservations = Reservation::query()
            ->whereBetween('starts_at', [$from, $to])
            ->get();

        $sold = $reservations
            ->whereIn('status', [
                ReservationStatus::Confirmed,
                ReservationStatus::CheckedIn,
                ReservationStatus::CheckedOut,
                ReservationStatus::NoShow,
            ]);

        $bookableRooms = Room::where('is_bookable', true)->count();
        $hoursInPeriod = max(1, (int) $from->diffInHours($to));
        $capacityHours = $bookableRooms * $hoursInPeriod;

        $soldHours = (int) $sold->sum(fn (Reservation $r) => $r->package_hours);
        $bufferHours = (int) round($sold->sum(fn (Reservation $r) => $r->buffer_minutes) / 60);

        $payments = Payment::query()
            ->where('status', PaymentStatus::Succeeded)
            ->whereBetween('paid_at', [$from, $to])
            ->get();

        $refunds = Refund::query()
            ->where('status', RefundStatus::Succeeded)
            ->whereBetween('processed_at', [$from, $to])
            ->get();

        return view('admin.reports.index', [
            'from' => $from,
            'to' => $to,

            'occupancy' => [
                'capacity_hours' => $capacityHours,
                'sold_hours' => $soldHours,
                'buffer_hours' => $bufferHours,
                'rate' => $capacityHours > 0 ? round($soldHours / $capacityHours * 100, 1) : 0.0,
                'bookable_rooms' => $bookableRooms,
            ],

            'revenue' => [
                'gross_cents' => (int) $payments->sum('amount_cents'),
                'refunded_cents' => (int) $refunds->sum('amount_cents'),
                'net_cents' => (int) $payments->sum('amount_cents') - (int) $refunds->sum('amount_cents'),
                'outstanding_cents' => (int) $reservations
                    ->whereIn('status', [ReservationStatus::Confirmed, ReservationStatus::CheckedIn])
                    ->sum('balance_due_cents'),
            ],

            'byChannel' => $payments->groupBy(fn (Payment $p) => $p->channel->label())
                ->map(fn ($group) => [
                    'count' => $group->count(),
                    'total_cents' => (int) $group->sum('amount_cents'),
                ]),

            'byMethod' => $payments->groupBy(fn (Payment $p) => $p->method->label())
                ->map(fn ($group) => [
                    'count' => $group->count(),
                    'total_cents' => (int) $group->sum('amount_cents'),
                ]),

            'byStatus' => $reservations->groupBy(fn (Reservation $r) => $r->status->label())
                ->map->count()
                ->sortDesc(),

            'byRoomType' => $sold->groupBy('room_type_id')
                ->map(fn ($group) => [
                    'name' => $group->first()->roomType->name,
                    'count' => $group->count(),
                    'hours' => (int) $group->sum('package_hours'),
                    'revenue_cents' => (int) $group->sum('total_cents'),
                ])
                ->sortByDesc('revenue_cents')
                ->values(),

            // Face-to-face money, by the person who took it. This is the
            // report the attribution requirement exists for.
            'byStaff' => $payments
                ->filter(fn (Payment $p) => $p->recorded_by_user_id !== null)
                ->groupBy('recorded_by_user_id')
                ->map(fn ($group) => [
                    'name' => $group->first()->recordedBy?->name ?? 'Unknown',
                    'count' => $group->count(),
                    'total_cents' => (int) $group->sum('amount_cents'),
                ])
                ->sortByDesc('total_cents')
                ->values(),

            'recentAudit' => AuditLog::with('actor')->latest('created_at')->limit(25)->get(),
        ]);
    }

    private function period(Request $request): array
    {
        $from = $request->filled('from')
            ? CarbonImmutable::parse($request->string('from')->toString())->startOfDay()
            : CarbonImmutable::now()->startOfMonth();

        $to = $request->filled('to')
            ? CarbonImmutable::parse($request->string('to')->toString())->endOfDay()
            : CarbonImmutable::now()->endOfDay();

        return $to->lt($from) ? [$to->startOfDay(), $from->endOfDay()] : [$from, $to];
    }
}
