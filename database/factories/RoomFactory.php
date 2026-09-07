<?php

namespace Database\Factories;

use App\Enums\RoomStatus;
use App\Models\Room;
use App\Models\RoomType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Room>
 */
class RoomFactory extends Factory
{
    public function definition(): array
    {
        return [
            'room_type_id' => RoomType::factory(),
            'number' => (string) fake()->unique()->numberBetween(100, 9999),
            'floor' => fake()->numberBetween(1, 8),
            'status' => RoomStatus::Available,
            'is_bookable' => true,
        ];
    }

    public function status(RoomStatus $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }

    public function notBookable(): static
    {
        return $this->state(fn () => ['is_bookable' => false]);
    }
}
