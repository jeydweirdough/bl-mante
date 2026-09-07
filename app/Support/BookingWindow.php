<?php

namespace App\Support;

use App\Models\DurationPackage;
use App\Models\PolicyVersion;
use App\Models\Reservation;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use InvalidArgumentException;

/**
 * The three datetimes a booking is made of, derived in one place.
 *
 *   startsAt      when the guest may enter
 *   endsAt        startsAt + the package's hours
 *   blockedUntil  endsAt + the turnover buffer
 *
 * Every overlap test in the system -- the search query, the transactional
 * re-check, the SQLite trigger and the PostgreSQL exclusion constraint --
 * ranges over [startsAt, blockedUntil). Half-open: a window whose buffer ends
 * at 14:00 does not collide with one starting at 14:00.
 *
 * The distinction between the stay interval and the blocked interval matters
 * and is easy to get wrong, so the two are named separately rather than being
 * a pair of loose Carbon variables passed around.
 */
final class BookingWindow
{
    public function __construct(
        public readonly CarbonImmutable $startsAt,
        public readonly CarbonImmutable $endsAt,
        public readonly CarbonImmutable $blockedUntil,
        public readonly int $hours,
        public readonly int $bufferMinutes,
    ) {}

    public static function make(CarbonInterface $startsAt, int $hours, int $bufferMinutes): self
    {
        $start = CarbonImmutable::instance($startsAt)->seconds(0)->microseconds(0);

        if ($start->minute !== 0) {
            throw new InvalidArgumentException('Reservations start on the hour.');
        }

        $end = $start->addHours($hours);

        return new self(
            startsAt: $start,
            endsAt: $end,
            blockedUntil: $end->addMinutes($bufferMinutes),
            hours: $hours,
            bufferMinutes: $bufferMinutes,
        );
    }

    public static function forPackage(CarbonInterface $startsAt, DurationPackage $package, PolicyVersion $policy): self
    {
        return self::make($startsAt, $package->hours, $policy->turnover_buffer_minutes);
    }

    public static function fromReservation(Reservation $reservation): self
    {
        return new self(
            startsAt: CarbonImmutable::instance($reservation->starts_at),
            endsAt: CarbonImmutable::instance($reservation->ends_at),
            blockedUntil: CarbonImmutable::instance($reservation->blocked_until),
            hours: $reservation->package_hours,
            bufferMinutes: $reservation->buffer_minutes,
        );
    }

    /** The same window with the stay lengthened by $hours, buffer trailing it. */
    public function extendedBy(int $hours): self
    {
        $end = $this->endsAt->addHours($hours);

        return new self(
            startsAt: $this->startsAt,
            endsAt: $end,
            blockedUntil: $end->addMinutes($this->bufferMinutes),
            hours: $this->hours + $hours,
            bufferMinutes: $this->bufferMinutes,
        );
    }

    /** The same shape of stay, moved to a new start time. */
    public function movedTo(CarbonInterface $startsAt): self
    {
        return self::make($startsAt, $this->hours, $this->bufferMinutes);
    }

    /**
     * The gap between the stay ending and the room becoming bookable again --
     * i.e. just the buffer, as its own interval.
     */
    public function bufferWindow(): array
    {
        return [$this->endsAt, $this->blockedUntil];
    }

    public function overlaps(self $other): bool
    {
        return $this->startsAt->lt($other->blockedUntil)
            && $this->blockedUntil->gt($other->startsAt);
    }

    public function isInPast(): bool
    {
        return $this->startsAt->isPast();
    }

    public function describeStay(): string
    {
        return $this->startsAt->format('D j M Y, H:i').' to '.$this->endsAt->format('H:i');
    }

    public function describeBlock(): string
    {
        return $this->describeStay().' (room held until '.$this->blockedUntil->format('H:i').')';
    }
}
