<?php

namespace App\Support;

use App\Models\Room;
use App\Models\RoomType;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * What the public rooms page needs to know about one room type: whether it is
 * free for the window the visitor asked about, and if not, when it next is.
 *
 * "Fully booked" on its own is a dead end. A visitor told the next free start
 * time has something to click; a visitor told nothing leaves.
 */
final class Availability
{
    /**
     * @param  Collection<int, Room>  $freeRooms  rooms free for the requested window
     * @param  BookingWindow|null  $nextWindow  earliest window with a free room, when the requested one is full
     * @param  bool  $searchedToHorizon  true if the forward search ran out of horizon rather than finding nothing
     */
    public function __construct(
        public readonly RoomType $roomType,
        public readonly BookingWindow $requested,
        public readonly Collection $freeRooms,
        public readonly ?int $priceCents,
        public readonly ?BookingWindow $nextWindow = null,
        public readonly bool $searchedToHorizon = false,
    ) {}

    public function isAvailable(): bool
    {
        return $this->freeRooms->isNotEmpty();
    }

    public function roomsFree(): int
    {
        return $this->freeRooms->count();
    }

    /** Fewer than this and the page says so, because scarcity is true here. */
    public function isNearlyGone(): bool
    {
        return $this->isAvailable() && $this->roomsFree() <= 2;
    }

    public function isSellable(): bool
    {
        return $this->priceCents !== null;
    }

    /**
     * The message shown when the requested window is full.
     *
     * Three distinct cases, because they call for different things from the
     * visitor: wait a few hours, come another day, or ring the desk.
     */
    public function unavailableMessage(): string
    {
        if ($this->nextWindow === null) {
            return $this->searchedToHorizon
                ? 'No free slot in the next '.config('hotel.rooms_lookahead_days').' days. Call us and we will find you something.'
                : 'Not available for this time.';
        }

        $next = $this->nextWindow->startsAt;

        if ($next->isToday()) {
            return 'Next free today at '.$next->format('H:i').'.';
        }

        if ($next->isTomorrow()) {
            return 'Next free tomorrow at '.$next->format('H:i').'.';
        }

        return 'Next free '.$next->format('D j M').' at '.$next->format('H:i').'.';
    }

    /** How long the guest would have to wait, in words. */
    public function waitDescription(): ?string
    {
        return $this->nextWindow?->startsAt->diffForHumans(
            $this->requested->startsAt,
            syntax: CarbonInterface::DIFF_ABSOLUTE,
        );
    }
}
