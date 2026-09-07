<?php

namespace Database\Factories;

use App\Enums\PaymentMode;
use App\Enums\ReservationChannel;
use App\Enums\ReservationPaymentStatus;
use App\Enums\ReservationStatus;
use App\Models\DurationPackage;
use App\Models\PolicyVersion;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Reservation>
 *
 * Produces rows that satisfy the database's own validity rules: the start is
 * on the hour, the interval runs forwards, and blocked_until trails the end by
 * the buffer. Building a reservation any other way trips the triggers, which
 * is the intent.
 */
class ReservationFactory extends Factory
{
    public function definition(): array
    {
        $policy = PolicyVersion::where('is_current', true)->first()
            ?? PolicyVersion::factory()->current()->create();

        $package = DurationPackage::first() ?? DurationPackage::factory()->ofHours(6)->create();

        $start = CarbonImmutable::now()->addDay()->startOfHour();
        $end = $start->addHours($package->hours);
        $total = 250000;

        return [
            'reference' => Reservation::generateReference(),
            'user_id' => User::factory(),
            'guest_name' => null,
            'channel' => ReservationChannel::Online,

            'room_id' => Room::factory(),
            'room_type_id' => fn (array $attributes) => Room::find($attributes['room_id'])?->room_type_id
                ?? Room::factory()->create()->room_type_id,
            'duration_package_id' => $package->id,
            'package_hours' => $package->hours,

            'starts_at' => $start,
            'ends_at' => $end,
            'buffer_minutes' => $policy->turnover_buffer_minutes,
            'blocked_until' => $end->addMinutes($policy->turnover_buffer_minutes),

            'adults' => 2,
            'children' => 0,

            'status' => ReservationStatus::Confirmed,
            'payment_mode' => PaymentMode::AtProperty,
            'payment_status' => ReservationPaymentStatus::Unpaid,
            'policy_version_id' => $policy->id,

            'currency' => config('hotel.currency'),
            'package_price_cents' => $total,
            'total_cents' => $total,
            'balance_due_cents' => $total,
            'confirmed_at' => now(),
        ];
    }

    /** Place the reservation on a specific room, window and package. */
    public function forWindow(Room $room, CarbonImmutable $startsAt, int $hours, int $bufferMinutes = 60): static
    {
        $end = $startsAt->startOfHour()->addHours($hours);

        return $this->state(fn () => [
            'room_id' => $room->id,
            'room_type_id' => $room->room_type_id,
            'starts_at' => $startsAt->startOfHour(),
            'ends_at' => $end,
            'package_hours' => $hours,
            'buffer_minutes' => $bufferMinutes,
            'blocked_until' => $end->addMinutes($bufferMinutes),
        ]);
    }

    public function status(ReservationStatus $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }

    public function pendingWithHold(int $minutes = 30): static
    {
        return $this->state(fn () => [
            'status' => ReservationStatus::Pending,
            'payment_mode' => PaymentMode::Online,
            'hold_expires_at' => now()->addMinutes($minutes),
            'confirmed_at' => null,
        ]);
    }

    /** A walk-in guest with no account, as the desk would create. */
    public function walkIn(): static
    {
        return $this->state(fn () => [
            'user_id' => null,
            'guest_name' => fake()->name(),
            'guest_phone' => fake()->numerify('+63 9## ### ####'),
            'channel' => ReservationChannel::WalkIn,
        ]);
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'amount_paid_cents' => $attributes['total_cents'],
            'balance_due_cents' => 0,
            'payment_status' => ReservationPaymentStatus::PaidInFull,
        ]);
    }
}
