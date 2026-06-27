<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TicketFactory extends Factory
{
    public function definition(): array
    {
        $org = Organization::factory()->create();

        return [
            'organization_id' => $org->id,
            'created_by' => User::factory()->for($org),
            'assigned_to' => null,
            'ticket_number' => 'TICK-' . fake()->unique()->numberBetween(1000, 9999),
            'title' => fake()->sentence(),
            'description' => fake()->paragraph(),
            'status' => fake()->randomElement([
                Ticket::STATUS_OPEN,
                Ticket::STATUS_CLAIMED,
                Ticket::STATUS_PENDING,
                Ticket::STATUS_RESOLVED,
                Ticket::STATUS_CLOSED,
            ]),
            'priority' => fake()->randomElement([
                Ticket::PRIORITY_LOW,
                Ticket::PRIORITY_NORMAL,
                Ticket::PRIORITY_HIGH,
                Ticket::PRIORITY_URGENT,
            ]),
            'first_response_at' => null,
            'resolved_at' => null,
        ];
    }

    public function for($factory, $relationship = null): static
    {
        if ($factory instanceof Organization) {
            return $this->state(fn (array $attributes) => [
                'organization_id' => $factory->id,
                'created_by' => User::factory()->for($factory),
            ]);
        }

        return parent::for($factory, $relationship);
    }
}
