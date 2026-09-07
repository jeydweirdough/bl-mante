<?php

namespace App\Support;

use App\Enums\PaymentMode;
use App\Enums\ReservationChannel;
use App\Models\DurationPackage;
use App\Models\RoomType;
use App\Models\User;
use Carbon\CarbonInterface;

/**
 * Everything needed to make a booking, from any of the three doors it can come
 * through: a customer online, a walk-in at the desk, or a phone call.
 *
 * Grouping it here keeps ReservationService::book to one argument and means
 * the walk-in path cannot quietly forget a field the online path sets.
 */
final class BookingRequest
{
    /**
     * @param  User|null  $customer  null for a walk-in or phone guest with no account
     * @param  int|null  $preferredRoomId  staff may pick a specific room; customers do not
     * @param  array<int, array{quantity:int, hours?:int}>  $extraSelections  keyed by extra id
     */
    public function __construct(
        public readonly RoomType $roomType,
        public readonly DurationPackage $package,
        public readonly CarbonInterface $startsAt,
        public readonly PaymentMode $paymentMode,
        public readonly int $adults = 1,
        public readonly int $children = 0,
        public readonly ?User $customer = null,
        public readonly ?string $guestName = null,
        public readonly ?string $guestEmail = null,
        public readonly ?string $guestPhone = null,
        public readonly ReservationChannel $channel = ReservationChannel::Online,
        public readonly ?User $createdBy = null,
        public readonly ?int $preferredRoomId = null,
        public readonly array $extraSelections = [],
        public readonly int $discountCents = 0,
        public readonly ?string $customerNotes = null,
        public readonly ?int $rescheduledFromId = null,
    ) {}

    public function partySize(): int
    {
        return max(1, $this->adults + $this->children);
    }

    /** The person the booking is for, however they were identified. */
    public function guestLabel(): string
    {
        return $this->customer?->name ?? $this->guestName ?? 'Guest';
    }
}
