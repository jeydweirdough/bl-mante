<?php

namespace Database\Factories;

use App\Models\RoomType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<RoomType>
 */
class RoomTypeFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->randomElement(['Standard', 'Deluxe', 'Suite', 'Loft', 'Studio']).' '.fake()->unique()->numberBetween(1, 999);

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'short_description' => fake()->sentence(),
            'description' => fake()->paragraph(),
            'base_occupancy' => 2,
            'max_occupancy' => 2,
            'bed_configuration' => 'One queen bed',
            'size_sqm' => fake()->numberBetween(18, 60),
            'extension_hourly_rate_cents' => 30000,
            'sort_order' => 0,
            'is_active' => true,
        ];
    }

    public function sleeping(int $max): static
    {
        return $this->state(fn () => ['max_occupancy' => $max, 'base_occupancy' => min(2, $max)]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
