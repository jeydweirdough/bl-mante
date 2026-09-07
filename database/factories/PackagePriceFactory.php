<?php

namespace Database\Factories;

use App\Models\DurationPackage;
use App\Models\PackagePrice;
use App\Models\RoomType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PackagePrice>
 */
class PackagePriceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'room_type_id' => RoomType::factory(),
            'duration_package_id' => DurationPackage::factory(),
            'price_cents' => fake()->numberBetween(80000, 400000),
            'currency' => config('hotel.currency'),
            'is_active' => true,
        ];
    }

    public function costing(int $cents): static
    {
        return $this->state(fn () => ['price_cents' => $cents]);
    }
}
