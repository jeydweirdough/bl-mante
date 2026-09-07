<?php

namespace App\Exceptions;

use App\Enums\ReservationStatus;
use App\Enums\RoomStatus;
use RuntimeException;

/**
 * An attempt to move a reservation or a room along an edge its state machine
 * does not have.
 *
 * This is a programming error rather than something a guest can trigger, so
 * it is not given a friendly message; the state machines are the guard and
 * the UI is expected not to offer impossible actions.
 */
class InvalidTransitionException extends RuntimeException
{
    public static function forReservation(ReservationStatus $from, ReservationStatus $to): self
    {
        return new self(sprintf(
            'A reservation cannot move from %s to %s.',
            $from->value,
            $to->value,
        ));
    }

    public static function forRoom(RoomStatus $from, RoomStatus $to): self
    {
        return new self(sprintf(
            'A room cannot move from %s to %s.',
            $from->value,
            $to->value,
        ));
    }
}
