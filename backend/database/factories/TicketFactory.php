<?php

namespace Database\Factories;

use App\Models\Ticket;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Ticket>
 */
class TicketFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * Note: organization_id, customer_id, and assignee_id are intentionally
     * omitted from the definition. They must always be supplied at create()
     * time to guarantee that every ticket is scoped to an organization.
     */
    public function definition(): array
    {
        return [
            'subject'     => fake()->sentence(fake()->numberBetween(4, 8), true),
            'description' => fake()->paragraph(fake()->numberBetween(2, 5), true),
            'status'      => fake()->randomElement(['open', 'pending', 'resolved', 'closed']),
            'priority'    => fake()->randomElement(['low', 'medium', 'high', 'urgent']),
        ];
    }

    public function status(string $status): static
    {
        return $this->state(fn (array $attributes) => ['status' => $status]);
    }

    public function priority(string $priority): static
    {
        return $this->state(fn (array $attributes) => ['priority' => $priority]);
    }
}
