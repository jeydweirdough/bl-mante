<?php

namespace Database\Factories;

use App\Models\DurationPackage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DurationPackage>
 */
class DurationPackageFactory extends Factory
{
    public function definition(): array
    {
        $hours = fake()->unique()->randomElement([3, 6, 12, 22]);

        return [
            'hours' => $hours,
            'name' => "{$hours}-hour stay",
            'sort_order' => $hours,
            'is_active' => true,
        ];
    }

    public function ofHours(int $hours): static
    {
        return $this->state(fn () => [
            'hours' => $hours,
            'name' => "{$hours}-hour stay",
            'sort_order' => $hours,
        ]);
    }
}
