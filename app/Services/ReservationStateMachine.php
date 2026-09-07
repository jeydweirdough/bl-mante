<?php

namespace App\Services;

use App\Enums\ReservationStatus;
use App\Exceptions\InvalidTransitionException;
use App\Models\Reservation;
use App\Models\ReservationStatusTransition;
use App\Models\User;

/**
 * The reservation state machine.
 *
 *     pending -> confirmed -> checked_in -> checked_out
 *
 * with cancelled, no_show and expired as terminals. The edges themselves are
 * declared on ReservationStatus; this class is what enforces them, stamps the
 * matching timestamp, writes the audit row and pushes the consequence onto the
 * physical room.
 *
 * Every state change in the system goes through here. That is what makes
 * "every transition records actor and timestamp" true by construction rather
 * than by everyone remembering.
 */
class ReservationStateMachine
{
    public function __construct(private readonly RoomStateService $rooms) {}

    /**
     * @param  User|null  $actor  null means the scheduler did it -- hold expiry
     *                            and the no-show sweep have no human behind them.
     */
    public function transition(
        Reservation $reservation,
        ReservationStatus $to,
        ?User $actor = null,
        ?string $reason = null,
        array $context = [],
    ): Reservation {
        $from = $reservation->status;

        if (! $from->canTransitionTo($to)) {
            throw InvalidTransitionException::forReservation($from, $to);
        }

        $reservation->status = $to;
        $this->stampTimestamp($reservation, $to);

        // A reservation that has left the pending state no longer holds an
        // unpaid window, and leaving the value behind would keep it in the
        // scheduler's expiry query forever.
        if ($to !== ReservationStatus::Pending) {
            $reservation->hold_expires_at = null;
        }

        $reservation->save();

        ReservationStatusTransition::create([
            'reservation_id' => $reservation->id,
            'from_status' => $from->value,
            'to_status' => $to->value,
            'actor_user_id' => $actor?->id,
            'actor_role' => $actor?->role?->value,
            'reason' => $reason,
            'context' => $context ?: null,
            'created_at' => now(),
        ]);

        $this->applyToRoom($reservation, $to, $actor);

        return $reservation;
    }

    /** The opening entry in a reservation's timeline. */
    public function recordCreation(Reservation $reservation, ?User $actor = null, ?string $reason = null): void
    {
        ReservationStatusTransition::create([
            'reservation_id' => $reservation->id,
            'from_status' => null,
            'to_status' => $reservation->status->value,
            'actor_user_id' => $actor?->id,
            'actor_role' => $actor?->role?->value,
            'reason' => $reason,
            'context' => null,
            'created_at' => now(),
        ]);

        $this->applyToRoom($reservation, $reservation->status, $actor);
    }

    private function stampTimestamp(Reservation $reservation, ReservationStatus $to): void
    {
        match ($to) {
            ReservationStatus::Confirmed => $reservation->confirmed_at = now(),
            ReservationStatus::CheckedIn => $reservation->checked_in_at = now(),
            ReservationStatus::CheckedOut => $reservation->checked_out_at = now(),
            ReservationStatus::Cancelled => $reservation->cancelled_at = now(),
            ReservationStatus::NoShow => $reservation->no_show_at = now(),

            // Expiry has no dedicated column; the transition log carries the
            // time, and adding one would duplicate it.
            ReservationStatus::Pending, ReservationStatus::Expired => null,
        };
    }

    /**
     * Push the consequence onto the physical room.
     *
     * Check-in and check-out are the only two that assert a room state
     * directly. Everything else recomputes it, so a release can never leave a
     * room stranded in Reserved with nothing behind it.
     */
    private function applyToRoom(Reservation $reservation, ReservationStatus $to, ?User $actor): void
    {
        $room = $reservation->room()->first();

        if ($room === null) {
            return;
        }

        match ($to) {
            ReservationStatus::CheckedIn => $this->rooms->markOccupied($room, $reservation, $actor),
            ReservationStatus::CheckedOut => $this->rooms->markCleaning($room, $reservation, $actor),
            default => $this->rooms->syncPresentState($room, $actor),
        };
    }
}
