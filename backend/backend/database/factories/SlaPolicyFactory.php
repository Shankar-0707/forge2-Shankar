<?php

namespace Database\Factories;

use App\Enums\TicketPriority;
use App\Models\Organization;
use App\Models\SlaPolicy;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SlaPolicy>
 */
class SlaPolicyFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\App\Models\SlaPolicy>
     */
    protected $model = SlaPolicy::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id'     => Organization::factory(),
            'name'                => $this->faker->unique()->words(3, true) . ' SLA',
            'description'         => $this->faker->optional(0.7)->sentence(),
            'priority'            => $this->faker->randomElement(TicketPriority::cases()),
            'first_response_mins' => $this->faker->randomElement([15, 30, 60, 120, 240]),
            'resolution_mins'     => $this->faker->randomElement([120, 240, 480, 1440, 2880]),
            'business_hours_only' => $this->faker->boolean(30),
            'is_active'           => true,
        ];
    }

    /**
     * Mark the SLA policy as inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Set a specific priority level.
     */
    public function priority(TicketPriority $priority): static
    {
        return $this->state(fn (array $attributes) => [
            'priority' => $priority,
        ]);
    }

    /**
     * Only count business hours for this policy.
     */
    public function businessHoursOnly(): static
    {
        return $this->state(fn (array $attributes) => [
            'business_hours_only' => true,
        ]);
    }

    /**
     * Create a policy with 24/7 coverage (no business-hours restriction).
     */
    public function allHours(): static
    {
        return $this->state(fn (array $attributes) => [
            'business_hours_only' => false,
        ]);
    }
}
