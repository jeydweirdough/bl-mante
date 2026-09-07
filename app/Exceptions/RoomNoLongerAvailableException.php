<?php

namespace App\Exceptions;

use Illuminate\Database\QueryException;

/**
 * Raised when the room a guest was about to take turned out to be gone.
 *
 * There are two ways to arrive here and they must be indistinguishable to the
 * guest:
 *
 *  1. The re-check inside the booking transaction found a conflicting
 *     reservation that was not there when the search ran.
 *  2. The database itself rejected the insert -- the PostgreSQL exclusion
 *     constraint, or the equivalent SQLite trigger. This is the case where
 *     two confirmations raced past the application check.
 *
 * Both surface as the same friendly "no longer available" message rather than
 * an error page. See Handler / the controllers' catch blocks.
 */
class RoomNoLongerAvailableException extends DomainRuleException
{
    public function __construct(
        string $message = 'That room was taken while you were booking. It is no longer available for the time you chose.',
        public readonly bool $causedByDatabaseConstraint = false,
    ) {
        parent::__construct($message);
    }

    /**
     * Recognise the database's own overlap rejection.
     *
     * PostgreSQL raises SQLSTATE 23P01 (exclusion_violation) when the
     * btree_gist EXCLUDE constraint fires. SQLite has no SQLSTATE for it, so
     * the trigger's RAISE(ABORT) message is matched instead -- both use the
     * constraint name `reservations_no_overlap`.
     *
     * Anything else is a genuine database error and is deliberately left to
     * propagate: swallowing it would turn a real fault into a misleading
     * "room unavailable" message.
     */
    public static function fromQueryException(QueryException $e): ?self
    {
        $sqlState = $e->errorInfo[0] ?? null;

        if ($sqlState === '23P01') {
            return new self(causedByDatabaseConstraint: true);
        }

        if (str_contains($e->getMessage(), 'reservations_no_overlap')) {
            return new self(causedByDatabaseConstraint: true);
        }

        return null;
    }
}
