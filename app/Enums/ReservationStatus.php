<?php

namespace App\Enums;

enum ReservationStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case CheckedIn = 'checked_in';
    case CheckedOut = 'checked_out';
    case Cancelled = 'cancelled';
    case NoShow = 'no_show';
    case Expired = 'expired';

    /**
     * The single definition of "this reservation occupies its room".
     *
     * This list is the authority for three things that must never drift apart:
     *  - the availability query's status filter,
     *  - the PostgreSQL partial exclusion constraint's WHERE clause,
     *  - the state machine's notion of a live reservation.
     *
     * Changing it means changing the exclusion constraint migration too.
     */
    public static function occupying(): array
    {
        return [self::Pending, self::Confirmed, self::CheckedIn];
    }

    /** @return list<string> */
    public static function occupyingValues(): array
    {
        return array_map(fn (self $s) => $s->value, self::occupying());
    }

    public function occupiesRoom(): bool
    {
        return in_array($this, self::occupying(), true);
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::CheckedOut, self::Cancelled, self::NoShow, self::Expired], true);
    }

    /**
     * The reservation state machine. Every allowed edge is listed here and
     * nowhere else; ReservationStateMachine is the only caller.
     *
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [self::Confirmed, self::Cancelled, self::Expired],
            self::Confirmed => [self::CheckedIn, self::Cancelled, self::NoShow],
            self::CheckedIn => [self::CheckedOut],
            self::CheckedOut, self::Cancelled, self::NoShow, self::Expired => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending payment',
            self::Confirmed => 'Confirmed',
            self::CheckedIn => 'Checked in',
            self::CheckedOut => 'Checked out',
            self::Cancelled => 'Cancelled',
            self::NoShow => 'No-show',
            self::Expired => 'Expired',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Pending => 'bg-amber-100 text-amber-800 ring-amber-600/20',
            self::Confirmed => 'bg-sky-100 text-sky-800 ring-sky-600/20',
            self::CheckedIn => 'bg-emerald-100 text-emerald-800 ring-emerald-600/20',
            self::CheckedOut => 'bg-slate-100 text-slate-700 ring-slate-500/20',
            self::Cancelled => 'bg-rose-100 text-rose-800 ring-rose-600/20',
            self::NoShow => 'bg-red-100 text-red-800 ring-red-600/20',
            self::Expired => 'bg-neutral-100 text-neutral-600 ring-neutral-500/20',
        };
    }
}
