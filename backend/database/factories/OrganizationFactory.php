<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class OrganizationFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name'           => $name,
            'slug'           => str($name)->slug(),
            'timezone'       => fake()->randomElement(['UTC', 'America/New_York', 'Europe/London']),
            'is_active'      => true,
            'settings'       => null,
            'trial_ends_at'  => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function onTrial(): static
    {
        return $this->state(fn (array $attributes) => [
            'trial_ends_at' => now()->addDays(14),
        ]);
    }
}
