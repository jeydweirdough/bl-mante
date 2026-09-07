<?php

namespace App\Services;

use App\Enums\ReservationStatus;
use App\Enums\RoomStatus;
use App\Models\DurationPackage;
use App\Models\PackagePrice;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use App\Support\Availability;
use App\Support\BookingWindow;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Answers "is this room free for this window", and nothing else.
 *
 * It does not create reservations. ReservationService owns the transaction
 * that performs the check and the insert together; this class supplies the
 * predicates that transaction uses, so the same comparison serves the public
 * search, the confirmation re-check, extensions and reschedules.
 *
 * ---------------------------------------------------------------------------
 * The overlap rule
 * ---------------------------------------------------------------------------
 *
 * A reservation blocks its room over [starts_at, blocked_until), where
 * blocked_until is the end of the stay plus the trailing turnover buffer that
 * was in force when the booking was made.
 *
 * A requested window [S, B) collides with an existing reservation [s, b) when
 *
 *     s < B  AND  b > S
 *
 * Both halves are needed and each catches a different case:
 *
 *   s < B   the existing reservation starts before the new one's buffer has
 *           finished -- the new stay would run into an existing booking.
 *   b > S   the existing reservation's buffer has not finished by the time the
 *           new stay starts -- the room is still in turnover.
 *
 * Because each row carries its own blocked_until, the buffer is handled in
 * both directions by this one predicate, and an admin changing the buffer
 * later cannot retroactively move an existing reservation's block.
 *
 * The comparison is half-open on purpose. A reservation whose buffer ends at
 * 14:00 does not collide with one starting at 14:00 -- the room is free at the
 * instant the buffer expires. This same predicate appears in the SQLite
 * trigger and the PostgreSQL exclusion constraint; if it changes here it must
 * change in migration 2026_01_01_001200 too.
 */
class AvailabilityService
{
    public function __construct(private readonly PolicyService $policies) {}

    // -----------------------------------------------------------------------
    // Public search
    // -----------------------------------------------------------------------

    /**
     * Which room types have a free room for this window, and how many.
     *
     * Used by the public availability grid, which needs the per-type summary
     * rather than the individual rooms; the rooms come along so staff can pick
     * one explicitly.
     *
     * @return Collection<int, array{room_type: RoomType, rooms: Collection<int, Room>, price_cents: ?int}>
     */
    public function search(
        CarbonInterface $startsAt,
        DurationPackage $package,
        ?int $roomTypeId = null,
        int $partySize = 1,
    ): Collection {
        $window = BookingWindow::forPackage($startsAt, $package, $this->policies->current());

        $rooms = $this->freeRooms($window, $roomTypeId, $partySize)
            ->load('roomType.packagePrices');

        return $rooms
            ->groupBy('room_type_id')
            ->map(fn (Collection $group) => [
                'room_type' => $group->first()->roomType,
                'rooms' => $group->sortBy('number')->values(),
                'price_cents' => $group->first()->roomType->priceFor($package),
            ])
            ->sortBy(fn (array $row) => $row['room_type']->sort_order)
            ->values();
    }

    /**
     * Every bookable room that is free for the whole of $window.
     *
     * @return Collection<int, Room>
     */
    public function freeRooms(BookingWindow $window, ?int $roomTypeId = null, int $partySize = 1): Collection
    {
        return Room::query()
            ->where('is_bookable', true)

            // A room the admin has pulled from service is never offered,
            // whatever its calendar says.
            ->where('status', '!=', RoomStatus::OutOfService->value)

            // A room being cleaned right now will be clean again long before a
            // future window; excluding it outright would hide real inventory.
            // It is only withheld from windows starting imminently, where the
            // housekeeping state genuinely decides the answer.
            ->when(
                $window->startsAt->lte(now()->addMinutes($window->bufferMinutes)),
                fn (Builder $q) => $q->where('status', '!=', RoomStatus::Cleaning->value),
            )

            ->when($roomTypeId, fn (Builder $q) => $q->where('room_type_id', $roomTypeId))
            ->whereHas('roomType', fn (Builder $q) => $q
                ->where('is_active', true)
                ->where('max_occupancy', '>=', max(1, $partySize)))

            ->whereDoesntHave('reservations', fn (Builder $q) => $this->applyOverlap($q, $window))

            ->with('roomType')
            ->orderBy('number')
            ->get();
    }

    /**
     * Is one specific room free for this window?
     *
     * $exceptReservationId lets a reservation ignore itself, which is what
     * extensions and reschedules need -- a reservation must not be found to
     * conflict with the very row it is about to update.
     */
    public function isRoomFree(int $roomId, BookingWindow $window, ?int $exceptReservationId = null): bool
    {
        return $this->conflictFor($roomId, $window, $exceptReservationId) === null;
    }

    /**
     * The reservation that stands in the way, if there is one.
     *
     * Returning the row rather than a boolean lets the desk tell a guest what
     * they are colliding with instead of an unexplained refusal.
     */
    public function conflictFor(int $roomId, BookingWindow $window, ?int $exceptReservationId = null): ?Reservation
    {
        return $this->overlappingQuery($window, $exceptReservationId)
            ->where('room_id', $roomId)
            ->first();
    }

    /**
     * The same question, asked with the row locked.
     *
     * Only meaningful inside a transaction. On PostgreSQL this takes row locks
     * on any conflicting reservations; on SQLite the lock clause compiles away,
     * and IMMEDIATE transactions serialise writers instead. Neither of those
     * alone closes the phantom-insert window -- see lockRoom below, which is
     * what actually does.
     */
    public function conflictForLocked(int $roomId, BookingWindow $window, ?int $exceptReservationId = null): ?Reservation
    {
        return $this->overlappingQuery($window, $exceptReservationId)
            ->where('room_id', $roomId)
            ->lockForUpdate()
            ->first();
    }

    // -----------------------------------------------------------------------
    // The customer duplicate-booking rule
    // -----------------------------------------------------------------------

    /**
     * A customer may not hold two reservations whose intervals overlap.
     *
     * Note this compares *stay* intervals, not blocked intervals. The turnover
     * buffer is a property of the room, not of the guest: a customer booking
     * 09:00-12:00 in one room and 13:00-16:00 in another is perfectly legal
     * even though the first room stays blocked until 13:00. Using the blocked
     * interval here would wrongly refuse that, and it is the same-calendar-day
     * case the requirements explicitly allow.
     *
     * Walk-in and phone guests have no account, so there is no identity to
     * check against and this returns null for them. Staff creating such a
     * booking are shown a name and phone match warning instead.
     */
    public function customerConflict(?int $userId, BookingWindow $window, ?int $exceptReservationId = null): ?Reservation
    {
        if ($userId === null) {
            return null;
        }

        return Reservation::query()
            ->where('user_id', $userId)
            ->whereIn('status', ReservationStatus::occupyingValues())
            ->when($exceptReservationId, fn (Builder $q, $id) => $q->whereKeyNot($id))

            // Stay against stay, deliberately -- see the note above.
            ->where('starts_at', '<', $window->endsAt)
            ->where('ends_at', '>', $window->startsAt)

            ->with(['roomType', 'room'])
            ->orderBy('starts_at')
            ->first();
    }

    /**
     * Existing guests with the same name or phone in an overlapping window.
     *
     * Advisory only. It is what the desk sees instead of the hard duplicate
     * block when creating a walk-in, because an anonymous guest cannot be
     * matched with any certainty and refusing a real person at the counter on
     * a name collision would be worse than the duplicate.
     *
     * @return Collection<int, Reservation>
     */
    public function similarGuestReservations(?string $name, ?string $phone, BookingWindow $window): Collection
    {
        if (blank($name) && blank($phone)) {
            return collect();
        }

        return Reservation::query()
            ->whereIn('status', ReservationStatus::occupyingValues())
            ->where('starts_at', '<', $window->endsAt)
            ->where('ends_at', '>', $window->startsAt)
            ->where(function (Builder $q) use ($name, $phone) {
                if (filled($name)) {
                    $q->orWhere('guest_name', 'like', $name);
                }
                if (filled($phone)) {
                    $q->orWhere('guest_phone', $phone);
                }
            })
            ->with(['room', 'roomType'])
            ->get();
    }

    // -----------------------------------------------------------------------
    // Concurrency primitive
    // -----------------------------------------------------------------------

    /**
     * Serialise everyone trying to book the same physical room.
     *
     * This is the piece that makes the check-then-insert safe, and it is worth
     * being precise about why a plain `SELECT ... FOR UPDATE` on reservations
     * is not enough: when the room is free there are no conflicting rows, so
     * there is nothing for that lock to hold, and two transactions can both
     * find the room empty and both insert. That is a phantom read.
     *
     * Taking a row lock on the rooms table instead gives a real mutex. The row
     * always exists, so the second transaction blocks at this line until the
     * first has committed, and only then runs its availability check -- by
     * which time it can see the row the first one inserted.
     *
     * On SQLite the lock clause compiles to nothing, but the connection uses
     * IMMEDIATE transactions (see config/database.php), which take the write
     * lock at BEGIN and serialise writers for the same reason.
     *
     * The exclusion constraint remains the backstop for anything that reaches
     * the table without going through here.
     */
    public function lockRoom(int $roomId): void
    {
        Room::query()->whereKey($roomId)->lockForUpdate()->first();
    }

    // -----------------------------------------------------------------------
    // Forward search, for the public rooms page
    // -----------------------------------------------------------------------

    /**
     * For each room type: is it free for this window, and if not, when next?
     *
     * Telling a visitor "fully booked" and stopping is a dead end. Telling them
     * the next free start hour gives them something to click.
     *
     * Cost: two queries in total, not two per room type per hour. The whole
     * horizon of reservations is pulled once and the scan happens in memory --
     * probing the database hour by hour across a fortnight would be thousands
     * of round trips to answer one page.
     *
     * @return Collection<int, Availability>
     */
    public function overview(
        CarbonInterface $startsAt,
        DurationPackage $package,
        int $partySize = 1,
        ?int $lookaheadDays = null,
    ): Collection {
        $policy = $this->policies->current();
        $requested = BookingWindow::forPackage($startsAt, $package, $policy);
        $horizonEnd = $requested->startsAt->addDays($lookaheadDays ?? config('hotel.rooms_lookahead_days'));

        // Every room that could ever take this party, with its type and price.
        $rooms = Room::query()
            ->where('is_bookable', true)
            ->where('status', '!=', RoomStatus::OutOfService->value)
            ->whereHas('roomType', fn (Builder $q) => $q
                ->where('is_active', true)
                ->where('max_occupancy', '>=', max(1, $partySize)))
            ->with('roomType')
            ->orderBy('number')
            ->get();

        if ($rooms->isEmpty()) {
            return collect();
        }

        // Everything already holding any of those rooms, anywhere in the
        // horizon. One query, then grouped by room.
        $blocks = Reservation::query()
            ->whereIn('room_id', $rooms->modelKeys())
            ->whereIn('status', ReservationStatus::occupyingValues())
            ->where('starts_at', '<', $horizonEnd)
            ->where('blocked_until', '>', $requested->startsAt)
            ->get(['room_id', 'starts_at', 'blocked_until'])
            ->groupBy('room_id');

        $prices = PackagePrice::query()
            ->where('duration_package_id', $package->id)
            ->where('is_active', true)
            ->pluck('price_cents', 'room_type_id');

        return $rooms
            ->groupBy('room_type_id')
            ->map(function (Collection $group) use ($requested, $blocks, $prices, $horizonEnd) {
                $roomType = $group->first()->roomType;

                $free = $group->filter(
                    fn (Room $room) => $this->roomIsFreeIn($blocks->get($room->id), $requested)
                )->values();

                return new Availability(
                    roomType: $roomType,
                    requested: $requested,
                    freeRooms: $free,
                    priceCents: $prices->get($roomType->id),
                    nextWindow: $free->isNotEmpty()
                        ? null
                        : $this->nextFreeWindow($group, $blocks, $requested, $horizonEnd),
                    searchedToHorizon: $free->isEmpty(),
                );
            })
            ->sortBy(fn (Availability $row) => $row->roomType->sort_order)
            ->values();
    }

    /**
     * The earliest start hour at or after the requested one where some room in
     * the group is free for the whole window.
     *
     * Walks hour by hour because reservations start on the hour and nothing
     * else is bookable -- there is no point testing 14:30. Stops at the first
     * hit, so a type that is free an hour later costs one iteration.
     *
     * @param  Collection<int, Room>  $rooms
     * @param  Collection<int, Collection>  $blocks  reservations grouped by room id
     */
    private function nextFreeWindow(
        Collection $rooms,
        Collection $blocks,
        BookingWindow $requested,
        CarbonImmutable $horizonEnd,
    ): ?BookingWindow {
        $candidate = $requested->startsAt->addHour();

        while ($candidate->lt($horizonEnd)) {
            $window = BookingWindow::make($candidate, $requested->hours, $requested->bufferMinutes);

            foreach ($rooms as $room) {
                if ($this->roomIsFreeIn($blocks->get($room->id), $window)) {
                    return $window;
                }
            }

            $candidate = $candidate->addHour();
        }

        return null;
    }

    /**
     * The overlap rule again, applied in memory to rows already fetched.
     *
     * Identical in meaning to applyOverlap(): half-open, over the stay plus
     * its trailing buffer. Two expressions of one rule is a risk, so both
     * carry the same comment and the same test covers them.
     */
    private function roomIsFreeIn(?Collection $roomBlocks, BookingWindow $window): bool
    {
        if ($roomBlocks === null || $roomBlocks->isEmpty()) {
            return true;
        }

        foreach ($roomBlocks as $block) {
            if ($block->starts_at < $window->blockedUntil && $block->blocked_until > $window->startsAt) {
                return false;
            }
        }

        return true;
    }

    // -----------------------------------------------------------------------
    // Internals
    // -----------------------------------------------------------------------

    private function overlappingQuery(BookingWindow $window, ?int $exceptReservationId = null): Builder
    {
        $query = Reservation::query()
            ->when($exceptReservationId, fn (Builder $q, $id) => $q->whereKeyNot($id))
            ->with(['room', 'roomType']);

        return $this->applyOverlap($query, $window);
    }

    /**
     * The overlap predicate itself, in one place.
     *
     * Every caller -- search, re-check, extension, reschedule -- goes through
     * this method so there is exactly one expression of the rule in PHP.
     */
    private function applyOverlap(Builder $query, BookingWindow $window): Builder
    {
        return $query
            ->whereIn('status', ReservationStatus::occupyingValues())
            ->where('starts_at', '<', $window->blockedUntil)
            ->where('blocked_until', '>', $window->startsAt);
    }
}
