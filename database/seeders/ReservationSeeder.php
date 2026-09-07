<?php

namespace Database\Seeders;

use App\Enums\ExtensionStatus;
use App\Enums\PaymentChannel;
use App\Enums\PaymentKind;
use App\Enums\PaymentMethod;
use App\Enums\PaymentMode;
use App\Enums\PaymentStatus;
use App\Enums\PricingBasis;
use App\Enums\ReservationChannel;
use App\Enums\ReservationStatus;
use App\Enums\RoomStatus;
use App\Models\DurationPackage;
use App\Models\Extra;
use App\Models\Payment;
use App\Models\PolicyVersion;
use App\Models\Reservation;
use App\Models\ReservationExtension;
use App\Models\ReservationExtra;
use App\Models\ReservationStatusTransition;
use App\Models\Room;
use App\Models\RoomAssignment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Bookings spread across past, current and future windows, so the availability
 * logic can be exercised the moment the seeder finishes.
 *
 * Reservations are written directly rather than through ReservationService,
 * because that service refuses start times in the past -- correctly, but it
 * makes it useless for building history. The trade-off is that this seeder has
 * to respect the same overlap rule by construction: it walks each room's
 * calendar forward, leaving the turnover buffer between bookings, so the
 * database's own overlap guarantee is never tripped. If it ever is, that is a
 * bug in this file, not in the guarantee.
 */
class ReservationSeeder extends Seeder
{
    private PolicyVersion $policy;

    private Collection $customers;

    private Collection $staff;

    private Collection $packages;

    private Collection $extras;

    public function run(): void
    {
        $this->policy = PolicyVersion::where('is_current', true)->firstOrFail();
        $this->customers = User::customers()->get();
        $this->staff = User::personnel()->where('is_active', true)->get();
        $this->packages = DurationPackage::active()->get();
        $this->extras = Extra::active()->get();

        foreach (Room::where('is_bookable', true)->get() as $room) {
            $this->fillRoomCalendar($room);
        }

        $this->addPendingHolds();
        $this->addPendingExtension();
        $this->syncRoomStates();
    }

    /**
     * Walk one room's calendar from three weeks ago to three weeks ahead,
     * dropping bookings in that never touch each other.
     */
    private function fillRoomCalendar(Room $room): void
    {
        $day = CarbonImmutable::today()->subDays(21);
        $lastDay = CarbonImmutable::today()->addDays(21);

        // The cursor is carried across days, not reset each morning. A
        // 22-hour package started in the evening runs well into the next day
        // and holds the room for a further hour of turnover after that; a
        // per-day cursor would happily book over the tail of it. Since the
        // cursor is the only thing that decides a start time, and it only ever
        // moves forward past the previous booking's blocked_until, no overlap
        // is possible by construction.
        $cursor = $day->addHours(8);

        while ($day->lte($lastDay)) {
            // Start no earlier than a civilised hour, and never before the
            // previous booking's hold has expired.
            $cursor = $cursor->max($day->addHours(rand(6, 9)));

            for ($i = 0, $planned = $this->bookingsForDay($day); $i < $planned; $i++) {
                // Once the cursor has run into the following day, stop and let
                // the outer loop advance -- otherwise a long stay would eat
                // several days' worth of bookings in one pass.
                if ($cursor->gte($day->addDay())) {
                    break;
                }

                $package = $this->packages->random();
                $start = $cursor->startOfHour();

                $this->createReservation($room, $package, $start);

                // Next possible start: the end of the stay, plus the turnover
                // buffer, rounded up to the hour, plus an idle gap so the
                // calendar is not wall to wall.
                $cursor = $start
                    ->addHours($package->hours)
                    ->addMinutes($this->policy->turnover_buffer_minutes)
                    ->ceilHour()
                    ->addHours(rand(0, 3));
            }

            $day = $day->addDay();
        }
    }

    /** Busier at weekends, and the recent past denser than the far future. */
    private function bookingsForDay(CarbonImmutable $day): int
    {
        if ($day->isWeekend()) {
            return rand(1, 3);
        }

        return $day->isFuture() ? rand(0, 2) : rand(1, 2);
    }

    private function createReservation(Room $room, DurationPackage $package, CarbonImmutable $start): void
    {
        $end = $start->addHours($package->hours);
        $blockedUntil = $end->addMinutes($this->policy->turnover_buffer_minutes);

        $price = $room->roomType->packagePrices
            ->firstWhere('duration_package_id', $package->id)?->price_cents;

        if ($price === null) {
            return;
        }

        $isWalkIn = rand(1, 100) <= 30;
        $customer = $isWalkIn ? null : $this->customers->random();
        $creator = $isWalkIn ? $this->staff->random() : null;

        $adults = min($room->roomType->max_occupancy, rand(1, 2));
        $children = $room->roomType->max_occupancy > 2 && rand(1, 100) <= 25 ? 1 : 0;

        $status = $this->statusFor($start, $end);
        $paymentMode = $isWalkIn || rand(1, 100) <= 35 ? PaymentMode::AtProperty : PaymentMode::Online;

        $extrasTotal = 0;
        $chosenExtras = $this->extras->random(min($this->extras->count(), rand(0, 2)));

        $reservation = DB::transaction(function () use (
            $room, $package, $start, $end, $blockedUntil, $price, $customer, $creator,
            $isWalkIn, $adults, $children, $status, $paymentMode, $chosenExtras, &$extrasTotal
        ) {
            $subtotal = $price;
            $lines = [];

            foreach ($chosenExtras as $extra) {
                $quantity = 1;
                $hours = $extra->pricing_basis === PricingBasis::PerHour ? $package->hours : null;
                $persons = $extra->pricing_basis === PricingBasis::PerPerson ? $adults + $children : null;
                $lineTotal = $extra->lineTotalCents($quantity, $package->hours, $adults + $children);

                $extrasTotal += $lineTotal;
                $subtotal += $lineTotal;

                $lines[] = [
                    'extra_id' => $extra->id,
                    'name_snapshot' => $extra->name,
                    'unit_price_cents_snapshot' => $extra->price_cents,
                    'pricing_basis_snapshot' => $extra->pricing_basis->value,
                    'quantity' => $quantity,
                    'hours' => $hours,
                    'persons' => $persons,
                    'line_total_cents' => $lineTotal,
                ];
            }

            $tax = intdiv($subtotal * $this->policy->tax_percent_bp + 5000, 10000);
            $total = $subtotal + $tax + $this->policy->service_fee_cents;

            $reservation = Reservation::create([
                'user_id' => $customer?->id,
                'guest_name' => $isWalkIn ? fake()->name() : $customer->name,
                'guest_email' => $isWalkIn ? null : $customer->email,
                'guest_phone' => $isWalkIn ? fake()->numerify('+63 9## ### ####') : $customer->phone,
                'created_by_user_id' => $creator?->id,
                'channel' => $isWalkIn
                    ? (rand(1, 100) <= 60 ? ReservationChannel::WalkIn : ReservationChannel::Phone)
                    : ReservationChannel::Online,

                'room_id' => $room->id,
                'room_type_id' => $room->room_type_id,
                'duration_package_id' => $package->id,
                'package_hours' => $package->hours,

                'starts_at' => $start,
                'ends_at' => $end,
                'buffer_minutes' => $this->policy->turnover_buffer_minutes,
                'blocked_until' => $blockedUntil,

                'adults' => $adults,
                'children' => $children,

                'status' => $status,
                'payment_mode' => $paymentMode,
                'policy_version_id' => $this->policy->id,
                'currency' => config('hotel.currency'),

                'package_price_cents' => $price,
                'extras_total_cents' => $extrasTotal,
                'tax_total_cents' => $tax,
                'fees_total_cents' => $this->policy->service_fee_cents,
                'total_cents' => $total,
                'balance_due_cents' => $total,

                'confirmed_at' => $status === ReservationStatus::Pending ? null : $start->subHours(rand(2, 72)),
                'checked_in_at' => in_array($status, [ReservationStatus::CheckedIn, ReservationStatus::CheckedOut], true) ? $start : null,
                'checked_out_at' => $status === ReservationStatus::CheckedOut ? $end : null,
                'cancelled_at' => $status === ReservationStatus::Cancelled ? $start->subHours(rand(1, 60)) : null,
                'no_show_at' => $status === ReservationStatus::NoShow ? $start->addHour() : null,
                'cancellation_initiator' => $status === ReservationStatus::Cancelled ? 'customer' : null,
            ]);

            foreach ($lines as $line) {
                ReservationExtra::create($line + [
                    'reservation_id' => $reservation->id,
                    'added_by_user_id' => $creator?->id,
                    'added_at' => $reservation->created_at,
                ]);
            }

            RoomAssignment::create([
                'reservation_id' => $reservation->id,
                'from_room_id' => null,
                'to_room_id' => $room->id,
                'reason' => 'Assigned at booking.',
                'changed_by_user_id' => $creator?->id,
                'created_at' => $reservation->created_at,
            ]);

            return $reservation;
        });

        $this->recordPayments($reservation, $status, $paymentMode);
        $this->recordTimeline($reservation, $status, $creator);
    }

    /**
     * A booking's status follows from where it sits relative to now, with a
     * minority of the past turned into cancellations and no-shows so the
     * reports have something to show.
     */
    private function statusFor(CarbonImmutable $start, CarbonImmutable $end): ReservationStatus
    {
        $now = CarbonImmutable::now();

        if ($end->isPast()) {
            return match (true) {
                rand(1, 100) <= 8 => ReservationStatus::Cancelled,
                rand(1, 100) <= 5 => ReservationStatus::NoShow,
                default => ReservationStatus::CheckedOut,
            };
        }

        if ($start->lte($now) && $end->gte($now)) {
            return ReservationStatus::CheckedIn;
        }

        return ReservationStatus::Confirmed;
    }

    private function recordPayments(Reservation $reservation, ReservationStatus $status, PaymentMode $mode): void
    {
        // Nothing was ever collected on these.
        if (in_array($status, [ReservationStatus::Pending, ReservationStatus::Expired], true)) {
            return;
        }

        // A no-show forfeits whatever was paid, so the payment stays on record.
        $payFull = $status !== ReservationStatus::Confirmed || rand(1, 100) <= 55;

        $amount = $payFull
            ? $reservation->total_cents
            : $this->policy->downpaymentFor($reservation->total_cents);

        $online = $mode === PaymentMode::Online;
        $staff = $online ? null : $this->staff->random();

        Payment::create([
            'reservation_id' => $reservation->id,
            'kind' => $payFull ? PaymentKind::Full : PaymentKind::Downpayment,
            'channel' => $online ? PaymentChannel::Online : PaymentChannel::AtProperty,
            'method' => $online ? PaymentMethod::MockGateway : collect(PaymentMethod::faceToFace())->random(),
            'amount_cents' => $amount,
            'currency' => $reservation->currency,
            'status' => PaymentStatus::Succeeded,
            'provider' => $online ? 'mock' : null,
            'provider_reference' => $online ? 'mock_'.Str::lower(Str::random(24)) : null,
            'idempotency_key' => (string) Str::uuid(),
            'recorded_by_user_id' => $staff?->id,
            'initiated_at' => $reservation->confirmed_at ?? $reservation->created_at,
            'paid_at' => $reservation->confirmed_at ?? $reservation->created_at,
        ]);

        $reservation->refresh()->recalculateFinancials();
        $reservation->save();
    }

    private function recordTimeline(Reservation $reservation, ReservationStatus $status, ?User $creator): void
    {
        $entries = [[
            'from' => null,
            'to' => $reservation->payment_mode === PaymentMode::Online ? ReservationStatus::Pending : ReservationStatus::Confirmed,
            'at' => $reservation->created_at,
        ]];

        if ($reservation->payment_mode === PaymentMode::Online) {
            $entries[] = ['from' => ReservationStatus::Pending, 'to' => ReservationStatus::Confirmed, 'at' => $reservation->confirmed_at];
        }

        if (in_array($status, [ReservationStatus::CheckedIn, ReservationStatus::CheckedOut], true)) {
            $entries[] = ['from' => ReservationStatus::Confirmed, 'to' => ReservationStatus::CheckedIn, 'at' => $reservation->checked_in_at];
        }

        if ($status === ReservationStatus::CheckedOut) {
            $entries[] = ['from' => ReservationStatus::CheckedIn, 'to' => ReservationStatus::CheckedOut, 'at' => $reservation->checked_out_at];
        }

        if ($status === ReservationStatus::Cancelled) {
            $entries[] = ['from' => ReservationStatus::Confirmed, 'to' => ReservationStatus::Cancelled, 'at' => $reservation->cancelled_at];
        }

        if ($status === ReservationStatus::NoShow) {
            $entries[] = ['from' => ReservationStatus::Confirmed, 'to' => ReservationStatus::NoShow, 'at' => $reservation->no_show_at];
        }

        foreach ($entries as $entry) {
            $actor = in_array($entry['to'], [
                ReservationStatus::CheckedIn,
                ReservationStatus::CheckedOut,
                ReservationStatus::NoShow,
            ], true) ? $this->staff->random() : $creator;

            ReservationStatusTransition::create([
                'reservation_id' => $reservation->id,
                'from_status' => $entry['from']?->value,
                'to_status' => $entry['to']->value,
                'actor_user_id' => $actor?->id,
                'actor_role' => $actor?->role?->value,
                'created_at' => $entry['at'] ?? $reservation->created_at,
            ]);
        }
    }

    /**
     * A couple of unpaid holds, one already past its deadline, so the
     * scheduled expiry command has something to do on the first run.
     */
    private function addPendingHolds(): void
    {
        $candidates = Room::where('is_bookable', true)->inRandomOrder()->limit(4)->get();

        foreach ($candidates as $index => $room) {
            $package = $this->packages->random();

            // Deliberately far enough out that nothing else is on the room.
            $start = CarbonImmutable::today()->addDays(25 + $index)->setHour(14);
            $end = $start->addHours($package->hours);

            $price = $room->roomType->packagePrices->firstWhere('duration_package_id', $package->id)?->price_cents;

            if ($price === null) {
                continue;
            }

            $tax = intdiv($price * $this->policy->tax_percent_bp + 5000, 10000);
            $total = $price + $tax + $this->policy->service_fee_cents;
            $customer = $this->customers->random();

            $reservation = Reservation::create([
                'user_id' => $customer->id,
                'guest_name' => $customer->name,
                'guest_email' => $customer->email,
                'channel' => ReservationChannel::Online,
                'room_id' => $room->id,
                'room_type_id' => $room->room_type_id,
                'duration_package_id' => $package->id,
                'package_hours' => $package->hours,
                'starts_at' => $start,
                'ends_at' => $end,
                'buffer_minutes' => $this->policy->turnover_buffer_minutes,
                'blocked_until' => $end->addMinutes($this->policy->turnover_buffer_minutes),
                'adults' => 2,
                'children' => 0,
                'status' => ReservationStatus::Pending,
                'payment_mode' => PaymentMode::Online,
                'policy_version_id' => $this->policy->id,
                'currency' => config('hotel.currency'),
                'package_price_cents' => $price,
                'tax_total_cents' => $tax,
                'fees_total_cents' => $this->policy->service_fee_cents,
                'total_cents' => $total,
                'balance_due_cents' => $total,

                // Two still running, two already lapsed and waiting for the
                // scheduler to release them.
                'hold_expires_at' => $index < 2
                    ? CarbonImmutable::now()->addMinutes(rand(5, 25))
                    : CarbonImmutable::now()->subMinutes(rand(5, 90)),
            ]);

            RoomAssignment::create([
                'reservation_id' => $reservation->id,
                'from_room_id' => null,
                'to_room_id' => $room->id,
                'reason' => 'Assigned at booking.',
                'created_at' => $reservation->created_at,
            ]);

            ReservationStatusTransition::create([
                'reservation_id' => $reservation->id,
                'from_status' => null,
                'to_status' => ReservationStatus::Pending->value,
                'created_at' => $reservation->created_at,
            ]);
        }
    }

    /** One extension waiting on the desk, so the approve path is visible. */
    private function addPendingExtension(): void
    {
        $reservation = Reservation::where('status', ReservationStatus::CheckedIn)
            ->with('roomType')
            ->inRandomOrder()
            ->first();

        if ($reservation === null) {
            return;
        }

        $hours = 2;

        ReservationExtension::create([
            'reservation_id' => $reservation->id,
            'requested_by_user_id' => $reservation->user_id,
            'additional_hours' => $hours,
            'previous_ends_at' => $reservation->ends_at,
            'new_ends_at' => $reservation->ends_at->copy()->addHours($hours),
            'hourly_rate_cents_snapshot' => $reservation->roomType->extension_hourly_rate_cents,
            'charge_cents' => $reservation->roomType->extension_hourly_rate_cents * $hours,
            'status' => ExtensionStatus::Requested,
            'requested_at' => now()->subMinutes(rand(5, 40)),
        ]);
    }

    /**
     * Bring each room's present-tense state into line with what is actually
     * happening, and leave a few rooms mid-turnover so the housekeeping board
     * is not empty.
     */
    private function syncRoomStates(): void
    {
        foreach (Room::all() as $room) {
            if (! $room->is_bookable) {
                $room->update(['status' => RoomStatus::OutOfService]);

                continue;
            }

            $inHouse = $room->reservations()->where('status', ReservationStatus::CheckedIn)->exists();

            $imminent = $room->reservations()
                ->whereIn('status', [ReservationStatus::Pending, ReservationStatus::Confirmed])
                ->where('starts_at', '<=', now()->addHours(12))
                ->where('blocked_until', '>', now())
                ->exists();

            $room->update([
                'status' => match (true) {
                    $inHouse => RoomStatus::Occupied,
                    $imminent => RoomStatus::Reserved,
                    default => RoomStatus::Available,
                },
            ]);
        }

        // Two rooms that have just been vacated and are waiting on
        // housekeeping, so "mark cleaning complete" has something to act on.
        Room::where('status', RoomStatus::Available)
            ->inRandomOrder()
            ->limit(2)
            ->get()
            ->each(fn (Room $room) => $room->update(['status' => RoomStatus::Cleaning]));
    }
}
