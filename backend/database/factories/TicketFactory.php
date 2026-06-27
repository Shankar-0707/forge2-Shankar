<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TicketFactory extends Factory
{
    protected $model = Ticket::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'agent_id' => null,
            'subject' => fake()->sentence(),
            'description' => fake()->paragraph(),
            'status' => fake()->randomElement(['open', 'in_progress', 'resolved', 'closed']),
            'priority' => fake()->randomElement(['low', 'medium', 'high', 'urgent']),
        ];
    }

    /**
     * Assign the ticket to a specific agent.
     */
    public function assignedTo(User $agent): static
    {
        return $this->state(fn (array $attributes) => [
            'agent_id' => $agent->id,
            'organization_id' => $agent->organization_id,
        ]);
    }

    /**
     * Create an unassigned ticket.
     */
    public function unassigned(): static
    {
        return $this->state(fn (array $attributes) => [
            'agent_id' => null,
        ]);
    }
}
