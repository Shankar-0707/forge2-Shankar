<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class OrganizationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'sla_response_minutes' => fake()->randomElement([60, 120, 240, 480]),
        ];
    }
}
