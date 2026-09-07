<?php

namespace Database\Factories;

use App\Enums\PricingBasis;
use App\Models\Extra;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Extra>
 */
class ExtraFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->randomElement([
            'Extra pillow set', 'Breakfast tray', 'Late checkout kit', 'Airport transfer', 'Parking',
        ]).' '.fake()->unique()->numberBetween(1, 999);

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => fake()->sentence(),
            'price_cents' => fake()->numberBetween(5000, 60000),
            'pricing_basis' => PricingBasis::PerBooking,
            'is_active' => true,
            'available_during_stay' => true,
            'sort_order' => 0,
        ];
    }

    public function basis(PricingBasis $basis): static
    {
        return $this->state(fn () => ['pricing_basis' => $basis]);
    }

    public function costing(int $cents): static
    {
        return $this->state(fn () => ['price_cents' => $cents]);
    }

    public function disabled(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
