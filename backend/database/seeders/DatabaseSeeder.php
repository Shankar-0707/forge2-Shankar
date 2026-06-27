<?php

namespace Database\Seeders;

use App\Models\Comment;
use App\Models\Organization;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database with a self-contained PulseDesk demo:
     * 1 organization, 1 admin, 2 agents, 2 customers, 12 tickets, and a
     * threaded mix of public + internal comments on every ticket.
     */
    public function run(): void
    {
        // --- Organization -------------------------------------------------
        $org = Organization::factory()->create([
            'name' => 'PulseDesk Demo',
        ]);

        // --- People -------------------------------------------------------
        $admin = User::factory()->create([
            'organization_id' => $org->id,
            'name'            => 'Ada Admin',
            'email'           => 'admin@pulsedesk.test',
            'role'            => 'admin',
        ]);

        $agents = User::factory()->count(2)->create([
            'organization_id' => $org->id,
            'role'            => 'agent',
        ]);

        $customers = User::factory()->count(2)->create([
            'organization_id' => $org->id,
            'role'            => 'customer',
        ]);

        // Admin can also take tickets, so pool them with agents for assignment.
        $assignees = $agents->push($admin);

        // --- Tickets ------------------------------------------------------
        // Cycle the wheels so we get a deterministic, even mix across the
        // 12 tickets: 3 of each status, 3 of each priority, no synchronous
        // status/priority pairing.
        $statuses   = ['open', 'pending', 'resolved', 'closed'];
        $priorities = ['low', 'medium', 'high', 'urgent'];

        for ($i = 0; $i < 12; $i++) {
            $status   = $statuses[$i % count($statuses)];
            $priority = $priorities[($i + 2) % count($priorities)];

            $ticket = Ticket::factory()->create([
                'organization_id' => $org->id,
                'customer_id'     => $customers->random()->id,
                'assignee_id'     => $assignees->random()->id,
                'status'          => $status,
                'priority'        => $priority,
            ]);

            $this->seedThread($ticket, $customers, $assignees);
        }
    }

    /**
     * Build a small threaded conversation on a ticket:
     *   - 1 public reply from the customer
     *   - 1 public reply from an agent/admin
     *   - 1 internal note from an agent/admin
     *   - occasionally a second public follow-up to vary thread length
     */
    private function seedThread(Ticket $ticket, Collection $customers, Collection $assignees): void
    {
        Comment::factory()->public()->create([
            'ticket_id' => $ticket->id,
            'user_id'   => $ticket->customer_id,
        ]);

        Comment::factory()->public()->create([
            'ticket_id' => $ticket->id,
            'user_id'   => $assignees->random()->id,
        ]);

        Comment::factory()->internal()->create([
            'ticket_id' => $ticket->id,
            'user_id'   => $assignees->random()->id,
        ]);

        if (fake()->boolean(40)) {
            Comment::factory()->public()->create([
                'ticket_id' => $ticket->id,
                'user_id'   => $customers->random()->id,
            ]);
        }
    }
}
