<?php

namespace App\Services;

use App\Enums\CancellationInitiator;
use App\Enums\ExtensionStatus;
use App\Enums\PricingBasis;
use App\Enums\RefundReason;
use App\Enums\ReservationStatus;
use App\Exceptions\DomainRuleException;
use App\Exceptions\DuplicateReservationException;
use App\Exceptions\ExtensionNotAvailableException;
use App\Exceptions\RoomNoLongerAvailableException;
use App\Models\Extra;
use App\Models\Reservation;
use App\Models\ReservationExtension;
use App\Models\ReservationExtra;
use App\Models\ReservationReschedule;
use App\Models\Room;
use App\Models\RoomAssignment;
use App\Models\User;
use App\Support\BookingRequest;
use App\Support\BookingWindow;
use App\Support\Money;
use App\Support\RefundOutcome;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * The reservation lifecycle.
 *
 * The one thing worth reading closely is book(): it is where the concurrency
 * requirement is met. Everything else is bookkeeping around the same idea --
 * check inside the transaction that writes.
 */
class ReservationService
{
    public function __construct(
        private readonly AvailabilityService $availability,
        private readonly PricingService $pricing,
        private readonly PolicyService $policies,
        private readonly ReservationStateMachine $stateMachine,
        private readonly RefundCalculator $refunds,
        private readonly PaymentService $payments,
        private readonly RoomStateService $rooms,
        private readonly AuditLogger $audit,
    ) {}

    // =======================================================================
    // Booking
    // =======================================================================

    /**
     * Create a reservation, or fail with a message a guest can act on.
     *
     * ---------------------------------------------------------------------
     * How the overlap rule survives two people booking at once
     * ---------------------------------------------------------------------
     *
     * The availability check and the insert happen inside one transaction.
     * The check is never run outside it -- the search results a guest was
     * looking at are treated as a hint, nothing more, and are re-verified
     * here at confirmation.
     *
     * Three layers, in order:
     *
     * 1. A row lock on the physical room (AvailabilityService::lockRoom).
     *    This is the actual mutex. Locking conflicting *reservations* would
     *    not work: when a room is free there are no rows to lock, so two
     *    transactions could both find it empty and both insert -- a phantom
     *    read. The room row always exists, so the second transaction blocks
     *    here until the first commits.
     *
     * 2. The overlap re-check, run after the lock is held. By now the second
     *    transaction can see whatever the first inserted, so exactly one of
     *    them finds the room free.
     *
     * 3. The database's own exclusion constraint, which catches anything that
     *    reaches the table by some other path. Its violation is translated
     *    back into the same friendly message rather than an error page.
     *
     * When no specific room was asked for, the candidate rooms are tried in
     * turn: if two guests race for the same window and there are two free
     * rooms, both succeed on different rooms. Only when the last one goes does
     * anybody see a refusal. Candidates are locked in ascending id order so
     * two concurrent bookings can never take locks in opposite orders and
     * deadlock.
     */
    public function book(BookingRequest $request): Reservation
    {
        $policy = $this->policies->current();
        $window = BookingWindow::forPackage($request->startsAt, $request->package, $policy);

        if ($window->isInPast()) {
            throw new DomainRuleException('That start time has already passed.');
        }

        try {
            return DB::transaction(function () use ($request, $window, $policy) {
                $roomId = $this->claimRoom($request, $window);

                // A customer may hold several bookings on one day, but not
                // two that overlap. Checked inside the transaction for the
                // same reason as the room: two tabs, one guest.
                if ($conflict = $this->availability->customerConflict($request->customer?->id, $window)) {
                    throw new DuplicateReservationException($conflict);
                }

                $quote = $this->pricing->quote(
                    roomType: $request->roomType,
                    package: $request->package,
                    window: $window,
                    extraSelections: $request->extraSelections,
                    partySize: $request->partySize(),
                    discountCents: $request->discountCents,
                    policy: $policy,
                );

                // Only the online path takes an unpaid hold. An at-property
                // booking has no payment by definition, so holding it to the
                // unpaid window would expire every one of them; the no-show
                // grace period is what protects the room in that case.
                $takesHold = $request->paymentMode->takesUnpaidHold();

                $reservation = Reservation::create(array_merge($quote->toReservationAttributes(), [
                    'user_id' => $request->customer?->id,
                    'guest_name' => $request->customer?->name ?? $request->guestName,
                    'guest_email' => $request->customer?->email ?? $request->guestEmail,
                    'guest_phone' => $request->customer?->phone ?? $request->guestPhone,
                    'created_by_user_id' => $request->createdBy?->id,
                    'channel' => $request->channel->value,

                    'room_type_id' => $request->roomType->id,
                    'room_id' => $roomId,
                    'duration_package_id' => $request->package->id,
                    'package_hours' => $window->hours,

                    'starts_at' => $window->startsAt,
                    'ends_at' => $window->endsAt,
                    'buffer_minutes' => $window->bufferMinutes,
                    'blocked_until' => $window->blockedUntil,

                    'adults' => $request->adults,
                    'children' => $request->children,

                    'status' => ($takesHold ? ReservationStatus::Pending : ReservationStatus::Confirmed)->value,
                    'payment_mode' => $request->paymentMode->value,
                    'policy_version_id' => $policy->id,
                    'currency' => config('hotel.currency'),

                    'hold_expires_at' => $takesHold ? now()->addMinutes($policy->unpaid_hold_minutes) : null,
                    'confirmed_at' => $takesHold ? null : now(),

                    'customer_notes' => $request->customerNotes,
                    'rescheduled_from_id' => $request->rescheduledFromId,
                ]));

                foreach ($quote->extraLines as $line) {
                    ReservationExtra::create(array_merge($line->toSnapshot(), [
                        'reservation_id' => $reservation->id,
                        'added_by_user_id' => $request->createdBy?->id,
                        'added_at' => now(),
                    ]));
                }

                RoomAssignment::create([
                    'reservation_id' => $reservation->id,
                    'from_room_id' => null,
                    'to_room_id' => $roomId,
                    'reason' => 'Assigned at booking.',
                    'changed_by_user_id' => $request->createdBy?->id,
                    'created_at' => now(),
                ]);

                $reservation->recalculateFinancials();
                $reservation->save();

                $this->stateMachine->recordCreation(
                    $reservation,
                    $request->createdBy,
                    $request->channel->isStaffCreated()
                        ? 'Created at the desk ('.$request->channel->label().').'
                        : null,
                );

                return $reservation->fresh(['room', 'roomType', 'extras', 'policyVersion']);
            });
        } catch (QueryException $e) {
            // The database rejected the insert. If that was the overlap
            // guarantee firing, the guest sees the same message they would
            // have seen from the application check -- not a stack trace.
            throw RoomNoLongerAvailableException::fromQueryException($e) ?? $e;
        }
    }

    /**
     * Pick and lock a room that is genuinely free, or refuse.
     *
     * Must only be called inside a transaction.
     */
    private function claimRoom(BookingRequest $request, BookingWindow $window): int
    {
        $candidates = $request->preferredRoomId !== null
            ? [$request->preferredRoomId]
            : $this->availability
                ->freeRooms($window, $request->roomType->id, $request->partySize())
                ->pluck('id')
                ->sort()      // consistent lock order across transactions
                ->values()
                ->all();

        foreach ($candidates as $roomId) {
            $this->availability->lockRoom($roomId);

            if ($this->availability->conflictFor($roomId, $window) === null) {
                return $roomId;
            }
        }

        throw new RoomNoLongerAvailableException(
            $request->preferredRoomId !== null
                ? 'That room was taken while you were booking. Please choose another.'
                : 'Every room of that type was taken for the time you chose while you were booking.'
        );
    }

    // =======================================================================
    // Lifecycle
    // =======================================================================

    public function confirm(Reservation $reservation, ?User $actor = null, ?string $reason = null): Reservation
    {
        return $this->stateMachine->transition($reservation, ReservationStatus::Confirmed, $actor, $reason);
    }

    /**
     * Release a pending reservation whose unpaid hold ran out.
     *
     * System-initiated: no actor, which is what a null actor on the transition
     * row means.
     */
    public function expireHold(Reservation $reservation): Reservation
    {
        return DB::transaction(fn () => $this->stateMachine->transition(
            reservation: $reservation,
            to: ReservationStatus::Expired,
            actor: null,
            reason: 'The unpaid hold window elapsed with no payment received.',
            context: ['hold_expired_at' => now()->toIso8601String()],
        ));
    }

    /**
     * The guest never arrived. All payment is forfeited and the room released.
     */
    public function markNoShow(Reservation $reservation, ?User $actor = null): Reservation
    {
        return DB::transaction(function () use ($reservation, $actor) {
            $outcome = $this->refunds->forNoShow($reservation);

            return $this->stateMachine->transition(
                reservation: $reservation,
                to: ReservationStatus::NoShow,
                actor: $actor,
                reason: 'Guest did not arrive within the grace period.',
                context: ['forfeited_cents' => $outcome->forfeitedCents],
            );
        });
    }

    public function checkIn(Reservation $reservation, User $actor): Reservation
    {
        return DB::transaction(function () use ($reservation, $actor) {
            $room = $reservation->room;

            // The calendar says this room is theirs; the housekeeping state
            // says whether it is physically ready. Both have to agree before
            // a guest walks in.
            if (! $room->acceptsGuestNow()) {
                throw new DomainRuleException(sprintf(
                    'Room %s is %s and cannot take a guest yet. Reassign the booking or clear the room first.',
                    $room->number,
                    strtolower($room->status->label()),
                ));
            }

            return $this->stateMachine->transition($reservation, ReservationStatus::CheckedIn, $actor);
        });
    }

    public function checkOut(Reservation $reservation, User $actor): Reservation
    {
        return DB::transaction(function () use ($reservation, $actor) {
            $reservation = $this->stateMachine->transition($reservation, ReservationStatus::CheckedOut, $actor);

            // Departure is when an unpaid balance becomes the desk's problem,
            // so it is surfaced rather than silently carried.
            if ($reservation->balance_due_cents > 0) {
                $this->audit->record(
                    action: 'reservation.checked_out_with_balance',
                    subject: $reservation,
                    actor: $actor,
                    after: ['balance_due' => Money::format($reservation->balance_due_cents)],
                );
            }

            return $reservation;
        });
    }

    /**
     * Cancel, apply the tier in force when the booking was made, and refund.
     *
     * The outcome is computed once, at a single instant, and both the refund
     * and the audit record use that same computation -- so what the guest was
     * shown on the confirm screen is what actually happens.
     */
    public function cancel(
        Reservation $reservation,
        CancellationInitiator $initiator = CancellationInitiator::Customer,
        ?User $actor = null,
        ?string $reason = null,
    ): RefundOutcome {
        return DB::transaction(function () use ($reservation, $initiator, $actor, $reason) {
            $at = CarbonImmutable::now();
            $outcome = $this->refunds->forCancellation($reservation, $initiator, $at);

            $reservation->cancellation_initiator = $initiator->value;
            $reservation->cancellation_reason = $reason;
            $reservation->cancelled_by_user_id = $actor?->id;
            $reservation->save();

            $this->stateMachine->transition(
                reservation: $reservation,
                to: ReservationStatus::Cancelled,
                actor: $actor,
                reason: $reason ?? $outcome->explanation,
                context: [
                    'initiator' => $initiator->value,
                    'tier' => $outcome->tier->value,
                    'refundable_cents' => $outcome->refundableCents,
                    'forfeited_cents' => $outcome->forfeitedCents,
                ],
            );

            if ($outcome->isRefundable()) {
                $this->payments->refund(
                    reservation: $reservation,
                    amountCents: $outcome->refundableCents,
                    reason: $initiator === CancellationInitiator::Hotel
                        ? RefundReason::HotelCancellation
                        : RefundReason::CustomerCancellation,
                    tier: $outcome->tier,
                    actor: $actor,
                    notes: $outcome->explanation,
                );
            }

            return $outcome;
        });
    }

    // =======================================================================
    // Room reassignment
    // =======================================================================

    /**
     * Move a booking to a different physical room before check-in.
     *
     * The target is locked and re-checked exactly as at booking, because a
     * reassignment is a booking on the new room in every way that matters.
     */
    public function reassignRoom(Reservation $reservation, Room $target, User $actor, ?string $reason = null): Reservation
    {
        if ($reservation->room_id === $target->id) {
            return $reservation;
        }

        if (! in_array($reservation->status, [ReservationStatus::Pending, ReservationStatus::Confirmed], true)) {
            throw new DomainRuleException('A room can only be reassigned before the guest checks in.');
        }

        try {
            return DB::transaction(function () use ($reservation, $target, $actor, $reason) {
                $window = BookingWindow::fromReservation($reservation);

                $this->availability->lockRoom($target->id);

                if ($conflict = $this->availability->conflictFor($target->id, $window, $reservation->id)) {
                    throw new RoomNoLongerAvailableException(sprintf(
                        'Room %s is not free for that window: %s is already on it.',
                        $target->number,
                        $conflict->reference,
                    ));
                }

                $previous = $reservation->room;

                $reservation->room_id = $target->id;

                // A reassignment can cross room types. The reservation keeps
                // the type it was priced against -- the guest paid for that --
                // so this is recorded rather than silently repriced.
                $crossesType = $target->room_type_id !== $reservation->room_type_id;

                $reservation->save();

                RoomAssignment::create([
                    'reservation_id' => $reservation->id,
                    'from_room_id' => $previous->id,
                    'to_room_id' => $target->id,
                    'reason' => $reason,
                    'changed_by_user_id' => $actor->id,
                    'created_at' => now(),
                ]);

                $this->rooms->syncPresentState($previous, $actor);
                $this->rooms->syncPresentState($target->refresh(), $actor);

                $this->audit->record(
                    action: 'reservation.room_reassigned',
                    subject: $reservation,
                    actor: $actor,
                    before: ['room' => $previous->number],
                    after: [
                        'room' => $target->number,
                        'reason' => $reason,
                        'crosses_room_type' => $crossesType,
                    ],
                );

                return $reservation;
            });
        } catch (QueryException $e) {
            throw RoomNoLongerAvailableException::fromQueryException($e) ?? $e;
        }
    }

    // =======================================================================
    // Extensions
    // =======================================================================

    /**
     * Ask to keep the room longer.
     *
     * Availability is checked here so a guest is told immediately when it is
     * impossible, and checked again on approval, because time passes between
     * the two and the room may have been taken meanwhile.
     */
    public function requestExtension(Reservation $reservation, int $additionalHours, ?User $actor = null): ReservationExtension
    {
        if (! $reservation->isExtendable()) {
            throw new DomainRuleException('Only a stay that is under way can be extended.');
        }

        if ($additionalHours < 1) {
            throw new \InvalidArgumentException('An extension needs at least one hour.');
        }

        $window = BookingWindow::fromReservation($reservation)->extendedBy($additionalHours);

        // The room must be free for the extra hours *and* the buffer that now
        // trails them. The system never moves another guest to make this fit.
        if (! $this->availability->isRoomFree($reservation->room_id, $window, $reservation->id)) {
            throw new ExtensionNotAvailableException;
        }

        $charge = $this->pricing->extensionChargeCents($reservation, $additionalHours);

        return ReservationExtension::create([
            'reservation_id' => $reservation->id,
            'requested_by_user_id' => $actor?->id,
            'additional_hours' => $additionalHours,
            'previous_ends_at' => $reservation->ends_at,
            'new_ends_at' => $window->endsAt,
            'hourly_rate_cents_snapshot' => $reservation->roomType->extension_hourly_rate_cents,
            'charge_cents' => $charge,
            'status' => ExtensionStatus::Requested->value,
            'requested_at' => now(),
        ]);
    }

    /**
     * Grant an extension: move the end time, extend the block, add the charge.
     *
     * Re-checks availability under a room lock, for the same reason booking
     * does -- another booking may have landed on the following slot since the
     * guest asked.
     */
    public function approveExtension(ReservationExtension $extension, User $actor): ReservationExtension
    {
        try {
            return DB::transaction(function () use ($extension, $actor) {
                $reservation = $extension->reservation()->lockForUpdate()->first();

                if ($extension->status !== ExtensionStatus::Requested) {
                    throw new DomainRuleException('That extension has already been decided.');
                }

                $window = BookingWindow::fromReservation($reservation)
                    ->extendedBy($extension->additional_hours);

                $this->availability->lockRoom($reservation->room_id);

                if ($conflict = $this->availability->conflictFor($reservation->room_id, $window, $reservation->id)) {
                    throw new ExtensionNotAvailableException(sprintf(
                        'The room is taken from %s (%s), so this stay cannot be extended.',
                        $conflict->starts_at->format('H:i'),
                        $conflict->reference,
                    ));
                }

                $reservation->ends_at = $window->endsAt;
                $reservation->blocked_until = $window->blockedUntil;
                $reservation->package_hours = $window->hours;
                $reservation->save();

                $extension->forceFill([
                    'status' => ExtensionStatus::Approved->value,
                    'decided_by_user_id' => $actor->id,
                    'decided_at' => now(),
                ])->save();

                $quote = $this->pricing->repriceReservation($reservation->refresh());
                $reservation->fill($quote->toReservationAttributes());
                $reservation->recalculateFinancials();
                $reservation->save();

                $this->audit->record(
                    action: 'reservation.extension_approved',
                    subject: $reservation,
                    actor: $actor,
                    after: [
                        'additional_hours' => $extension->additional_hours,
                        'new_ends_at' => $window->endsAt->toIso8601String(),
                        'charge' => Money::format($extension->charge_cents),
                    ],
                );

                return $extension;
            });
        } catch (QueryException $e) {
            throw RoomNoLongerAvailableException::fromQueryException($e) ?? $e;
        }
    }

    public function refuseExtension(ReservationExtension $extension, User $actor, string $reason): ReservationExtension
    {
        if ($extension->status !== ExtensionStatus::Requested) {
            throw new DomainRuleException('That extension has already been decided.');
        }

        $extension->forceFill([
            'status' => ExtensionStatus::Refused->value,
            'decided_by_user_id' => $actor->id,
            'decided_at' => now(),
            'refusal_reason' => $reason,
        ])->save();

        $this->audit->record(
            action: 'reservation.extension_refused',
            subject: $extension->reservation,
            actor: $actor,
            after: ['reason' => $reason],
        );

        return $extension;
    }

    // =======================================================================
    // Rescheduling
    // =======================================================================

    /**
     * Move a booking to a new start time.
     *
     * One free move is allowed while the booking is more than the configured
     * window out and an equivalent slot exists. Outside that, the caller is
     * expected to cancel and rebook instead -- this method refuses rather than
     * quietly applying a fee, because cancel-and-rebook reprices under today's
     * terms and that is a decision the guest should make knowingly.
     */
    public function reschedule(Reservation $reservation, CarbonInterface $newStartsAt, ?User $actor = null, ?int $preferredRoomId = null): Reservation
    {
        if (! $reservation->isReschedulable()) {
            throw new DomainRuleException('Only a confirmed booking that has not started can be moved.');
        }

        if (! $this->refunds->freeRescheduleAvailable($reservation)) {
            throw new DomainRuleException(sprintf(
                'A free move is only possible more than %d hours ahead and once per booking. '
                .'Cancel and rebook instead -- the cancellation terms above will apply.',
                $reservation->policyVersion->free_reschedule_hours_before,
            ));
        }

        try {
            return DB::transaction(function () use ($reservation, $newStartsAt, $actor, $preferredRoomId) {
                $window = BookingWindow::fromReservation($reservation)->movedTo($newStartsAt);

                if ($window->isInPast()) {
                    throw new DomainRuleException('That start time has already passed.');
                }

                if ($conflict = $this->availability->customerConflict($reservation->user_id, $window, $reservation->id)) {
                    throw new DuplicateReservationException($conflict);
                }

                $roomId = $this->claimRoomForMove($reservation, $window, $preferredRoomId);

                $from = [
                    'starts_at' => $reservation->starts_at,
                    'ends_at' => $reservation->ends_at,
                    'room_id' => $reservation->room_id,
                ];

                $previousRoom = $reservation->room;

                $reservation->fill([
                    'starts_at' => $window->startsAt,
                    'ends_at' => $window->endsAt,
                    'blocked_until' => $window->blockedUntil,
                    'room_id' => $roomId,
                    'reschedule_count' => $reservation->reschedule_count + 1,
                ])->save();

                ReservationReschedule::create([
                    'reservation_id' => $reservation->id,
                    'from_starts_at' => $from['starts_at'],
                    'from_ends_at' => $from['ends_at'],
                    'from_room_id' => $from['room_id'],
                    'to_starts_at' => $window->startsAt,
                    'to_ends_at' => $window->endsAt,
                    'to_room_id' => $roomId,
                    'was_free' => true,
                    'performed_by_user_id' => $actor?->id,
                ]);

                if ($from['room_id'] !== $roomId) {
                    RoomAssignment::create([
                        'reservation_id' => $reservation->id,
                        'from_room_id' => $from['room_id'],
                        'to_room_id' => $roomId,
                        'reason' => 'Reschedule.',
                        'changed_by_user_id' => $actor?->id,
                        'created_at' => now(),
                    ]);

                    $this->rooms->syncPresentState($previousRoom, $actor);
                }

                $this->rooms->syncPresentState($reservation->room()->first(), $actor);

                $this->audit->record(
                    action: 'reservation.rescheduled',
                    subject: $reservation,
                    actor: $actor,
                    before: [
                        'starts_at' => $from['starts_at']->toIso8601String(),
                        'room_id' => $from['room_id'],
                    ],
                    after: [
                        'starts_at' => $window->startsAt->toIso8601String(),
                        'room_id' => $roomId,
                        'free' => true,
                    ],
                );

                return $reservation;
            });
        } catch (QueryException $e) {
            throw RoomNoLongerAvailableException::fromQueryException($e) ?? $e;
        }
    }

    private function claimRoomForMove(Reservation $reservation, BookingWindow $window, ?int $preferredRoomId): int
    {
        // Keep the guest in the room they already have where possible; it is
        // the least surprising outcome and avoids a needless reassignment.
        $candidates = collect([$preferredRoomId, $reservation->room_id])
            ->filter()
            ->merge(
                $this->availability
                    ->freeRooms($window, $reservation->room_type_id, $reservation->partySize())
                    ->pluck('id')
            )
            ->unique()
            ->values();

        foreach ($candidates as $roomId) {
            $this->availability->lockRoom($roomId);

            if ($this->availability->conflictFor($roomId, $window, $reservation->id) === null) {
                return $roomId;
            }
        }

        throw new RoomNoLongerAvailableException('No equivalent room is free at that time.');
    }

    // =======================================================================
    // Extras
    // =======================================================================

    /**
     * Add an extra, at booking or during the stay.
     *
     * Everything about the extra is snapshotted, so disabling or re-pricing it
     * afterwards leaves this line untouched. A per-hour extra added mid-stay
     * is charged for the hours that remain, not the whole package.
     */
    public function addExtra(Reservation $reservation, Extra $extra, int $quantity = 1, ?User $actor = null): ReservationExtra
    {
        if (! $reservation->canAcceptExtras()) {
            throw new DomainRuleException('Extras cannot be added to a booking in that state.');
        }

        if (! $extra->is_active) {
            throw new DomainRuleException('That extra is no longer offered.');
        }

        return DB::transaction(function () use ($reservation, $extra, $quantity, $actor) {
            $remainingHours = $reservation->status === ReservationStatus::CheckedIn
                ? max(1, (int) ceil(now()->diffInMinutes($reservation->ends_at, false) / 60))
                : $reservation->package_hours;

            $line = ReservationExtra::create([
                'reservation_id' => $reservation->id,
                'extra_id' => $extra->id,
                'name_snapshot' => $extra->name,
                'unit_price_cents_snapshot' => $extra->price_cents,
                'pricing_basis_snapshot' => $extra->pricing_basis->value,
                'quantity' => max(1, $quantity),
                'hours' => $extra->pricing_basis === PricingBasis::PerHour ? $remainingHours : null,
                'persons' => $extra->pricing_basis === PricingBasis::PerPerson ? $reservation->partySize() : null,
                'line_total_cents' => $extra->lineTotalCents($quantity, $remainingHours, $reservation->partySize()),
                'added_by_user_id' => $actor?->id,
                'added_at' => now(),
            ]);

            $quote = $this->pricing->repriceReservation($reservation->refresh());
            $reservation->fill($quote->toReservationAttributes());
            $reservation->recalculateFinancials();
            $reservation->save();

            return $line;
        });
    }

    // =======================================================================
    // Scheduled sweeps
    // =======================================================================

    /**
     * Reservations whose unpaid hold has run out.
     *
     * Deliberately a query rather than a list of ids, so the command can chunk
     * it -- this runs every minute and the table only grows.
     */
    public function expiredHoldsQuery()
    {
        return Reservation::query()
            ->where('status', ReservationStatus::Pending)
            ->whereNotNull('hold_expires_at')
            ->where('hold_expires_at', '<=', now())
            ->where('amount_paid_cents', 0);
    }

    /**
     * Confirmed bookings whose guest has not arrived within the grace period.
     *
     * The grace period comes from each reservation's own policy version, so
     * this joins rather than reading the current one -- a booking made under a
     * two-hour grace keeps it after an admin cuts the setting to thirty
     * minutes.
     */
    public function overdueArrivalsQuery()
    {
        return Reservation::query()
            ->where('reservations.status', ReservationStatus::Confirmed)
            ->join('policy_versions', 'policy_versions.id', '=', 'reservations.policy_version_id')
            ->whereRaw(
                $this->graceExpression(),
                [now()]
            )
            ->select('reservations.*');
    }

    /**
     * "starts_at + grace minutes is in the past", written for both engines.
     *
     * SQLite has no INTERVAL arithmetic and PostgreSQL has no datetime(); the
     * two forms below are the same expression in each dialect.
     */
    private function graceExpression(): string
    {
        return DB::connection()->getDriverName() === 'pgsql'
            ? "reservations.starts_at + (policy_versions.no_show_grace_minutes * interval '1 minute') <= ?"
            : "datetime(reservations.starts_at, '+' || policy_versions.no_show_grace_minutes || ' minutes') <= ?";
    }
}
