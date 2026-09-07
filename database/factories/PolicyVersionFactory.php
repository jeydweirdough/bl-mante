<?php

namespace Database\Factories;

use App\Models\PolicyVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PolicyVersion>
 */
class PolicyVersionFactory extends Factory
{
    public function definition(): array
    {
        return array_merge(config('hotel.policy_defaults'), [
            'version' => (int) PolicyVersion::max('version') + 1,
            'effective_from' => now(),
            'is_current' => false,
        ]);
    }

    public function current(): static
    {
        return $this->state(fn () => ['is_current' => true]);
    }

    public function withBuffer(int $minutes): static
    {
        return $this->state(fn () => ['turnover_buffer_minutes' => $minutes]);
    }

    public function withHold(int $minutes): static
    {
        return $this->state(fn () => ['unpaid_hold_minutes' => $minutes]);
    }
}
