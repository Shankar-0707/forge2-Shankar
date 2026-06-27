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
        $status = fake()->randomElement(Ticket::STATUSES);
        $isResolved = in_array($status, [Ticket::STATUS_RESOLVED, Ticket::STATUS_CLOSED]);

        return [
            'organization_id' => Organization::factory(),
            'requester_id' => User::factory(),
            'assignee_id' => null,
            'ticket_number' => 'TKT-' . strtoupper(fake()->unique()->bothify('??-####')),
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'status' => $status,
            'priority' => fake()->randomElement(Ticket::PRIORITIES),
            'category' => fake()->optional()->randomElement(['billing', 'technical', 'general', 'feature']),
            'sla_due_at' => $isResolved ? null : fake()->dateTimeBetween('-2 days', '+3 days'),
            'resolved_at' => $isResolved ? fake()->dateTimeThisMonth() : null,
        ];
    }

    public function assigned(): static
    {
        return $this->state(fn (array $attributes) => [
            'assignee_id' => User::factory()->for(
                Organization::find($attributes['organization_id']) ?? Organization::factory(),
                'organization'
            )->create(),
        ]);
    }

    public function breached(): static
    {
        return $this->state(fn () => [
            'sla_due_at' => now()->subHours(3),
        ]);
    }

    public function atRisk(): static
    {
        return $this->state(fn () => [
            'sla_due_at' => now()->addMinutes(rand(5, 110)),
        ]);
    }
}
