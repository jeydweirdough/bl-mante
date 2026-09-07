<?php

namespace App\Policies;

use App\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Models\User;

/**
 * Who may do what to a reservation.
 *
 * Whether an action makes sense for a reservation in a given state is a
 * separate question, answered by the model (isCancellable, isExtendable, ...).
 * This class only answers whether *this user* is allowed. Keeping the two
 * apart is what stops "can they?" checks leaking into controllers.
 */
class ReservationPolicy
{
    public function viewAny(User $user): bool
    {
        // Customers see their own list; personnel see everything.
        return true;
    }

    public function view(User $user, Reservation $reservation): bool
    {
        return $user->isPersonnel() || $this->owns($user, $reservation);
    }

    public function create(User $user): bool
    {
        return true;
    }

    /** Walk-in and phone bookings, which attach a guest with no account. */
    public function createForGuest(User $user): bool
    {
        return $user->isPersonnel();
    }

    public function cancel(User $user, Reservation $reservation): bool
    {
        if (! $reservation->isCancellable()) {
            return false;
        }

        return $user->isPersonnel() || $this->owns($user, $reservation);
    }

    public function reschedule(User $user, Reservation $reservation): bool
    {
        if (! $reservation->isReschedulable()) {
            return false;
        }

        return $user->isPersonnel() || $this->owns($user, $reservation);
    }

    /** A guest asks; staff decide. */
    public function requestExtension(User $user, Reservation $reservation): bool
    {
        if (! $reservation->isExtendable() || $reservation->hasPendingExtension()) {
            return false;
        }

        return $user->isPersonnel() || $this->owns($user, $reservation);
    }

    public function decideExtension(User $user, Reservation $reservation): bool
    {
        return $user->isPersonnel();
    }

    public function addExtra(User $user, Reservation $reservation): bool
    {
        if (! $reservation->canAcceptExtras()) {
            return false;
        }

        return $user->isPersonnel() || $this->owns($user, $reservation);
    }

    public function pay(User $user, Reservation $reservation): bool
    {
        return $reservation->balance_due_cents > 0
            && ($user->isPersonnel() || $this->owns($user, $reservation));
    }

    // -----------------------------------------------------------------------
    // Desk-only actions
    // -----------------------------------------------------------------------

    public function checkIn(User $user, Reservation $reservation): bool
    {
        return $user->isPersonnel()
            && $reservation->status === ReservationStatus::Confirmed;
    }

    public function checkOut(User $user, Reservation $reservation): bool
    {
        return $user->isPersonnel() && $reservation->isInHouse();
    }

    public function markNoShow(User $user, Reservation $reservation): bool
    {
        return $user->isPersonnel()
            && $reservation->status === ReservationStatus::Confirmed
            && $reservation->hasStarted();
    }

    public function assignRoom(User $user, Reservation $reservation): bool
    {
        return $user->isPersonnel() && in_array($reservation->status, [
            ReservationStatus::Pending,
            ReservationStatus::Confirmed,
        ], true);
    }

    public function recordPayment(User $user, Reservation $reservation): bool
    {
        return $user->isPersonnel();
    }

    /** Only an admin may hand money back outside the cancellation tiers. */
    public function refundManually(User $user, Reservation $reservation): bool
    {
        return $user->isAdmin();
    }

    public function viewInternalNotes(User $user): bool
    {
        return $user->isPersonnel();
    }

    /**
     * Walk-in reservations have no account attached, so ownership can only be
     * established for bookings a customer actually holds.
     */
    private function owns(User $user, Reservation $reservation): bool
    {
        return $reservation->user_id !== null && $reservation->user_id === $user->id;
    }
}
