<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SlaPolicy> */
class SlaPolicyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organization_id' => \App\Models\Organization::factory(),
            'name' => $this->faker->words(2, true).' SLA',
            'description' => $this->faker->sentence(),
            'low_response_minutes' => 2880,
            'medium_response_minutes' => 720,
            'high_response_minutes' => 240,
            'urgent_response_minutes' => 60,
            'low_resolution_minutes' => 10080,
            'medium_resolution_minutes' => 4320,
            'high_resolution_minutes' => 1440,
            'urgent_resolution_minutes' => 480,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
