<?php

use App\Models\Organization;
use App\Models\Ticket;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

describe('Stats Metrics', function () {

    it('returns metrics for the auth users organization', function () {
        $org = Organization::factory()->create(['sla_response_minutes' => 60]);
        $agent = User::factory()->for($org)->create(['role' => User::ROLE_AGENT]);

        Ticket::factory()->for($org)->count(3)->create([
            'status' => Ticket::STATUS_OPEN,
        ]);
        Ticket::factory()->for($org)->create([
            'status' => Ticket::STATUS_RESOLVED,
        ]);

        Sanctum::actingAs($agent);

        $response = $this->getJson('/api/stats/metrics');

        $response->assertOk()
            ->assertJsonStructure([
                'sla_breach_rate',
                'sla_breach_count',
                'sla_threshold_minutes',
                'avg_first_response_minutes',
                'open_ticket_count',
                'total_tickets',
                'tickets_last_7_days',
                'status_breakdown',
                'priority_breakdown',
            ])
            ->assertJsonPath('open_ticket_count', 3)
            ->assertJsonPath('total_tickets', 4)
            ->assertJsonPath('sla_threshold_minutes', 60);
    });

    it('calculates SLA breach rate correctly', function () {
        $org = Organization::factory()->create(['sla_response_minutes' => 60]);
        $agent = User::factory()->for($org)->create(['role' => User::ROLE_AGENT]);

        // 2 healthy: responded within SLA
        Ticket::factory()->for($org)->count(2)->create([
            'status' => Ticket::STATUS_CLAIMED,
            'created_at' => now()->subMinutes(50),
            'first_response_at' => now()->subMinutes(40),
        ]);

        // 2 breached: responded but late (> 60 min)
        Ticket::factory()->for($org)->count(2)->create([
            'status' => Ticket::STATUS_CLAIMED,
            'created_at' => now()->subMinutes(120),
            'first_response_at' => now()->subMinutes(30),
        ]);

        // Total: 4 tickets, 2 breached → 50%
        Sanctum::actingAs($agent);

        $this->getJson('/api/stats/metrics')
            ->assertOk()
            ->assertJsonPath('sla_breach_rate', 50.0)
            ->assertJsonPath('sla_breach_count', 2);
    });

    it('counts unresponded overdue tickets as breached', function () {
        $org = Organization::factory()->create(['sla_response_minutes' => 60]);
        $agent = User::factory()->for($org)->create(['role' => User::ROLE_AGENT]);

        // 1 unresponded ticket, past SLA window
        Ticket::factory()->for($org)->create([
            'status' => Ticket::STATUS_OPEN,
            'created_at' => now()->subMinutes(90),
            'first_response_at' => null,
        ]);

        // 1 healthy ticket
        Ticket::factory()->for($org)->create([
            'status' => Ticket::STATUS_CLAIMED,
            'created_at' => now()->subMinutes(10),
            'first_response_at' => now()->subMinutes(5),
        ]);

        Sanctum::actingAs($agent);

        $this->getJson('/api/stats/metrics')
            ->assertOk()
            ->assertJsonPath('sla_breach_rate', 50.0)
            ->assertJsonPath('sla_breach_count', 1);
    });

    it('calculates average first-response time', function () {
        $org = Organization::factory()->create();
        $agent = User::factory()->for($org)->create(['role' => User::ROLE_AGENT]);

        Ticket::factory()->for($org)->create([
            'created_at' => now()->subMinutes(30),
            'first_response_at' => now()->subMinutes(20),
        ]);

        Ticket::factory()->for($org)->create([
            'created_at' => now()->subMinutes(40),
            'first_response_at' => now()->subMinutes(10),
        ]);

        // Both took 10 minutes → avg = 10.0
        Sanctum::actingAs($agent);

        $this->getJson('/api/stats/metrics')
            ->assertOk()
            ->assertJsonPath('avg_first_response_minutes', 10.0);
    });

    it('returns null avg response when no tickets have been responded to', function () {
        $org = Organization::factory()->create();
        $agent = User::factory()->for($org)->create(['role' => User::ROLE_AGENT]);

        Ticket::factory()->for($org)->create([
            'first_response_at' => null,
        ]);

        Sanctum::actingAs($agent);

        $this->getJson('/api/stats/metrics')
            ->assertOk()
            ->assertJsonPath('avg_first_response_minutes', null);
    });

    it('isolates metrics by organization', function () {
        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();

        $agentA = User::factory()->for($orgA)->create(['role' => User::ROLE_AGENT]);

        // Org A: 2 open tickets
        Ticket::factory()->for($orgA)->count(2)->create([
            'status' => Ticket::STATUS_OPEN,
        ]);

        // Org B: 5 open tickets (should NOT appear in Org A's metrics)
        Ticket::factory()->for($orgB)->count(5)->create([
            'status' => Ticket::STATUS_OPEN,
        ]);

        Sanctum::actingAs($agentA);

        $this->getJson('/api/stats/metrics')
            ->assertOk()
            ->assertJsonPath('open_ticket_count', 2)
            ->assertJsonPath('total_tickets', 2);
    });

    it('returns zero values for empty organization', function () {
        $org = Organization::factory()->create();
        $agent = User::factory()->for($org)->create(['role' => User::ROLE_AGENT]);

        Sanctum::actingAs($agent);

        $this->getJson('/api/stats/metrics')
            ->assertOk()
            ->assertJsonPath('sla_breach_rate', 0.0)
            ->assertJsonPath('sla_breach_count', 0)
            ->assertJsonPath('open_ticket_count', 0)
            ->assertJsonPath('total_tickets', 0)
            ->assertJsonPath('avg_first_response_minutes', null);
    });

    it('requires authentication', function () {
        $this->getJson('/api/stats/metrics')
            ->assertUnauthorized();
    });
});
