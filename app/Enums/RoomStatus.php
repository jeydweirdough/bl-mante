<?php

namespace App\Enums;

enum RoomStatus: string
{
    case Available = 'available';
    case Reserved = 'reserved';
    case Occupied = 'occupied';
    case Cleaning = 'cleaning';
    case OutOfService = 'out_of_service';

    /**
     * Whether the room can take a guest *right now*.
     *
     * This is deliberately not the same question as "is the room free for a
     * future window" — that is answered from the reservations table by
     * AvailabilityService. A room that is Occupied today is still bookable
     * for next Tuesday.
     */
    public function acceptsGuestNow(): bool
    {
        return $this === self::Available || $this === self::Reserved;
    }

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Available => [self::Reserved, self::Occupied, self::Cleaning, self::OutOfService],
            self::Reserved => [self::Occupied, self::Available, self::Cleaning, self::OutOfService],
            self::Occupied => [self::Cleaning, self::OutOfService],
            self::Cleaning => [self::Available, self::OutOfService],
            self::OutOfService => [self::Available, self::Cleaning],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Available => 'Available',
            self::Reserved => 'Reserved',
            self::Occupied => 'Occupied',
            self::Cleaning => 'Cleaning',
            self::OutOfService => 'Out of service',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Available => 'bg-emerald-100 text-emerald-800 ring-emerald-600/20',
            self::Reserved => 'bg-sky-100 text-sky-800 ring-sky-600/20',
            self::Occupied => 'bg-indigo-100 text-indigo-800 ring-indigo-600/20',
            self::Cleaning => 'bg-amber-100 text-amber-800 ring-amber-600/20',
            self::OutOfService => 'bg-neutral-200 text-neutral-700 ring-neutral-500/20',
        };
    }
}
