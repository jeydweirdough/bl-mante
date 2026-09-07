<?php

namespace App\Services;

use App\Enums\ReservationStatus;
use App\Enums\RoomStatus;
use App\Exceptions\InvalidTransitionException;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomStatusTransition;
use App\Models\User;

/**
 * The room state machine.
 *
 *     available -> reserved -> occupied -> cleaning -> available
 *
 * Two things this class is careful about:
 *
 * 1. Room status is the present-tense state of a physical room. It is never
 *    consulted to decide whether a future window is free -- that is derived
 *    from the reservations table by AvailabilityService. Conflating the two is
 *    how systems like this grow overbooking bugs.
 *
 * 2. Because it describes *now*, it can be recomputed from facts rather than
 *    carefully maintained by every caller. syncPresentState does exactly that,
 *    which means a cancellation, an expiry or a missed event cannot leave a
 *    room stuck in Reserved with nothing behind it.
 */
class RoomStateService
{
    /** How far ahead a confirmed arrival makes a room read as Reserved. */
    private const IMMINENT_HORIZON_HOURS = 12;

    /**
     * Move a room, refusing edges the state machine does not have, and record
     * who did it.
     */
    public function transition(
        Room $room,
        RoomStatus $to,
        ?User $actor = null,
        ?Reservation $reservation = null,
    ): Room {
        $from = $room->status;

        if ($from === $to) {
            return $room;
        }

        if (! $from->canTransitionTo($to)) {
            throw InvalidTransitionException::forRoom($from, $to);
        }

        $room->status = $to;
        $room->save();

        RoomStatusTransition::create([
            'room_id' => $room->id,
            'from_status' => $from->value,
            'to_status' => $to->value,
            'reservation_id' => $reservation?->id,
            'actor_user_id' => $actor?->id,
            'created_at' => now(),
        ]);

        return $room;
    }

    public function markOccupied(Room $room, Reservation $reservation, ?User $actor = null): Room
    {
        return $this->transition($room, RoomStatus::Occupied, $actor, $reservation);
    }

    /** A departure puts the room into turnover; it is not bookable again until cleared. */
    public function markCleaning(Room $room, ?Reservation $reservation = null, ?User $actor = null): Room
    {
        return $this->transition($room, RoomStatus::Cleaning, $actor, $reservation);
    }

    /** Housekeeping has finished. */
    public function markCleaningComplete(Room $room, ?User $actor = null): Room
    {
        $this->transition($room, RoomStatus::Available, $actor);

        // A room cleared for service may already have someone due shortly.
        return $this->syncPresentState($room, $actor);
    }

    public function markOutOfService(Room $room, ?User $actor = null): Room
    {
        return $this->transition($room, RoomStatus::OutOfService, $actor);
    }

    public function returnToService(Room $room, ?User $actor = null): Room
    {
        $this->transition($room, RoomStatus::Available, $actor);

        return $this->syncPresentState($room, $actor);
    }

    /**
     * Recompute a room's present state from what is actually true right now.
     *
     * Called after anything that releases or claims a room -- confirmation,
     * cancellation, expiry, no-show, reassignment -- and by the scheduler, so
     * the board is self-correcting rather than dependent on every caller
     * remembering to tidy up.
     *
     * Cleaning and out-of-service are left alone: both are statements by a
     * person about the physical room that no amount of calendar reasoning
     * should override.
     */
    public function syncPresentState(Room $room, ?User $actor = null): Room
    {
        if (in_array($room->status, [RoomStatus::Cleaning, RoomStatus::OutOfService], true)) {
            return $room;
        }

        $inHouse = $room->reservations()
            ->where('status', ReservationStatus::CheckedIn)
            ->exists();

        if ($inHouse) {
            return $room->status === RoomStatus::Occupied
                ? $room
                : $this->transition($room, RoomStatus::Occupied, $actor);
        }

        // Occupied with nobody in house means the guest has gone. The room
        // does not go straight back on sale -- the only ways out of Occupied
        // are cleaning and out-of-service, which is the point of the state
        // machine.
        if ($room->status === RoomStatus::Occupied) {
            return $this->transition($room, RoomStatus::Cleaning, $actor);
        }

        $imminent = $room->reservations()
            ->whereIn('status', [ReservationStatus::Pending, ReservationStatus::Confirmed])
            ->where('starts_at', '<=', now()->addHours(self::IMMINENT_HORIZON_HOURS))
            ->where('blocked_until', '>', now())
            ->exists();

        $target = $imminent ? RoomStatus::Reserved : RoomStatus::Available;

        return $room->status === $target
            ? $room
            : $this->transition($room, $target, $actor);
    }
}
