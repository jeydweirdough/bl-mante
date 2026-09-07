<?php

namespace App\Exceptions;

/**
 * The room is not free for the requested additional hours plus the trailing
 * buffer.
 *
 * The system never relocates another guest to make an extension fit, so this
 * is a refusal, not a prompt to reshuffle.
 */
class ExtensionNotAvailableException extends DomainRuleException
{
    public function __construct(string $message = 'The room is booked immediately after this stay, so it cannot be extended.')
    {
        parent::__construct($message);
    }
}
