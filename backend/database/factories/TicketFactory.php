<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Ticket> */
class TicketFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organization_id' => \App\Models\Organization::factory(),
            'requester_id' => User::factory(),
            'assignee_id' => null,
            'subject' => $this->faker->sentence(),
            'description' => $this->faker->paragraph(),
            'status' => 'open',
            'priority' => $this->faker->randomElement(['low', 'medium', 'high', 'urgent']),
            'response_at' => null,
            'resolved_at' => null,
        ];
    }

    public function priority(string $priority): static
    {
        return $this->state(['priority' => $priority]);
    }
}
