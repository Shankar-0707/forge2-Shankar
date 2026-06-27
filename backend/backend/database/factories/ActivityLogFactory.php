<?php

namespace Database\Factories;

use App\Models\ActivityLog;
use App\Models\Organization;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ActivityLog>
 */
class ActivityLogFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\App\Models\ActivityLog>
     */
    protected $model = ActivityLog::class;

    /**
     * Common activity types in a helpdesk system.
     */
    private const ACTIVITY_TYPES = [
        'ticket.created',
        'ticket.assigned',
        'ticket.reassigned',
        'ticket.status_changed',
        'ticket.priority_changed',
        'ticket.comment_added',
        'ticket.attachment_uploaded',
        'ticket.tagged',
        'ticket.sla_breached',
        'ticket.sla_warning',
        'user.logged_in',
        'user.logged_out',
    ];

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $activityType = $this->faker->randomElement(self::ACTIVITY_TYPES);

        return [
            'organization_id' => Organization::factory(),
            'user_id'         => User::factory(),
            'subject_type'    => Ticket::class,
            'subject_id'      => Ticket::factory(),
            'activity_type'   => $activityType,
            'description'     => $this->generateDescription($activityType),
            'properties'      => $this->generateProperties($activityType),
            'ip_address'      => $this->faker->optional(0.8)->ipv4(),
            'user_agent'      => $this->faker->optional(0.8)->userAgent(),
            'created_at'      => $this->faker->dateTimeBetween('-30 days', 'now'),
        ];
    }

    /**
     * Associate this activity log with a specific organization.
     */
    public function forOrganization(int $organizationId): static
    {
        return $this->state(fn (array $attributes) => [
            'organization_id' => $organizationId,
        ]);
    }

    /**
     * Create a system-generated log (no user).
     */
    public function systemGenerated(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => null,
        ]);
    }

    /**
     * Set a specific activity type.
     */
    public function type(string $activityType): static
    {
        return $this->state(fn (array $attributes) => [
            'activity_type' => $activityType,
            'description'   => $this->generateDescription($activityType),
            'properties'    => $this->generateProperties($activityType),
        ]);
    }

    /**
     * Generate a human-readable description based on activity type.
     */
    private function generateDescription(string $activityType): string
    {
        return match ($activityType) {
            'ticket.created'          => 'Ticket was created',
            'ticket.assigned'         => 'Ticket was assigned',
            'ticket.reassigned'       => 'Ticket was reassigned',
            'ticket.status_changed'   => 'Ticket status was updated',
            'ticket.priority_changed' => 'Ticket priority was changed',
            'ticket.comment_added'    => 'A comment was added to the ticket',
            'ticket.attachment_uploaded' => 'An attachment was uploaded',
            'ticket.tagged'           => 'A tag was applied to the ticket',
            'ticket.sla_breached'     => 'SLA policy was breached',
            'ticket.sla_warning'      => 'SLA policy is approaching breach',
            'user.logged_in'          => 'User logged in',
            'user.logged_out'         => 'User logged out',
            default                   => $this->faker->sentence(),
        };
    }

    /**
     * Generate realistic properties JSON based on activity type.
     *
     * @return array<string, mixed>
     */
    private function generateProperties(string $activityType): array
    {
        return match ($activityType) {
            'ticket.status_changed' => [
                'from' => $this->faker->randomElement(['open', 'pending', 'resolved']),
                'to'   => $this->faker->randomElement(['pending', 'resolved', 'closed']),
            ],
            'ticket.priority_changed' => [
                'from' => $this->faker->randomElement(['low', 'medium', 'high']),
                'to'   => $this->faker->randomElement(['medium', 'high', 'urgent']),
            ],
            'ticket.assigned', 'ticket.reassigned' => [
                'to_user' => $this->faker->name(),
            ],
            'ticket.sla_breached' => [
                'policy'         => $this->faker->word(),
                'breached_at'    => $this->faker->iso8601(),
                'deadline'       => $this->faker->iso8601(),
            ],
            'ticket.sla_warning' => [
                'policy'         => $this->faker->word(),
                'deadline'       => $this->faker->iso8601(),
                'hours_remaining' => $this->faker->numberBetween(1, 8),
            ],
            default => [],
        };
    }
}
