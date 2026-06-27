<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\SlaPolicy;
use Illuminate\Database\Eloquent\Factories\Factory;

class SlaPolicyFactory extends Factory
{
    protected $model = SlaPolicy::class;

    public function definition(): array
    {
        $priority = $this->faker->randomElement(SlaPolicy::PRIORITIES);

        return [
            'organization_id'      => Organization::factory(),
            'priority'             => $priority,
            'response_time_limit'  => SlaPolicy::DEFAULTS[$priority]['response_time_limit'],
            'resolution_time_limit'=> SlaPolicy::DEFAULTS[$priority]['resolution_time_limit'],
            'is_active'            => true,
            'business_hours_only'  => false,
        ];
    }

    public function global(): static
    {
        return $this->state(fn() => ['organization_id' => null]);
    }
}
