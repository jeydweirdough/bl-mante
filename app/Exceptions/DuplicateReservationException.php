<?php

namespace App\Exceptions;

use App\Models\Reservation;

/**
 * A customer already holds a reservation whose stay interval overlaps the one
 * they are trying to book.
 *
 * The requirement is that the message names the conflicting reservation, so
 * the conflicting model is carried on the exception rather than flattened
 * into a string at the throw site.
 */
class DuplicateReservationException extends DomainRuleException
{
    public function __construct(public readonly Reservation $conflictingReservation)
    {
        parent::__construct(sprintf(
            'You already have a reservation that overlaps this time: %s. '
            .'You can hold more than one booking on the same day, but the times cannot overlap.',
            $conflictingReservation->shortDescription(),
        ));
    }
}
