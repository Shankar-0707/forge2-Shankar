<?php

namespace Database\Factories;

use App\Models\ActivityLog;
use App\Models\Organization;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ActivityLog>
 */
class ActivityLogFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'ticket_id'       => Ticket::factory(),
            'actor_id'        => User::factory(),
            'event'           => fake()->randomElement([
                ActivityLog::EVENT_CREATED,
                ActivityLog::EVENT_ASSIGNED,
                ActivityLog::EVENT_STATUS_CHANGED,
                ActivityLog::EVENT_COMMENTED,
            ]),
            'metadata'        => null,
        ];
    }
}
