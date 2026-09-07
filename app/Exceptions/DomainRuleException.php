<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A refusal a person can understand and act on.
 *
 * These are not faults. A room going while someone was booking, an extension
 * that will not fit, a free reschedule that has already been used -- all are
 * normal outcomes of the rules, and all must reach the guest or the desk as a
 * sentence on the page they were already on rather than as an error page.
 *
 * The render handler in bootstrap/app.php turns any of these into a flash
 * message. Anything that is genuinely a fault must not extend this class, so
 * that it keeps Laravel's normal handling and shows up in the logs.
 */
class DomainRuleException extends RuntimeException {}
