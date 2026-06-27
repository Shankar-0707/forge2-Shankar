<?php

declare(strict_types=1);

use App\Models\Organization;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

describe('Dashboard Metrics API', function () {
    beforeEach(function () {
        Cache::flush();
    });

    it('returns 401 when not authenticated', function () {
        $this->getJson('/api/v1/dashboard/metrics')
            ->assertUnauthorized();
    });

    it('returns correct JSON structure', function () {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/dashboard/metrics')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'tickets_by_status',
                    'tickets_by_priority',
                    'average_first_response_time_seconds',
                    'sla_breach_rate',
                    'daily_ticket_volume' => [
                        '*' => ['date', 'count'],
                    ],
                ],
            ]);
    });

    it('groups ticket counts by status correctly', function () {
        $user = User::factory()->create();

        Ticket::factory()->forOrganization($user->organization_id)->count(3)->create(['status' => 'open']);
        Ticket::factory()->forOrganization($user->organization_id)->count(2)->create(['status' => 'resolved']);
        Ticket::factory()->forOrganization($user->organization_id)->count(1)->create(['status' => 'closed']);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/dashboard/metrics')
            ->assertOk()
            ->assertJsonPath('data.tickets_by_status.open', 3)
            ->assertJsonPath('data.tickets_by_status.resolved', 2)
            ->assertJsonPath('data.tickets_by_status.closed', 1);
    });

    it('groups ticket counts by priority correctly', function () {
        $user = User::factory()->create();

        Ticket::factory()->forOrganization($user->organization_id)->count(5)->create(['priority' => 'high']);
        Ticket::factory()->forOrganization($user->organization_id)->count(10)->create(['priority' => 'low']);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/dashboard/metrics')
            ->assertOk()
            ->assertJsonPath('data.tickets_by_priority.high', 5)
            ->assertJsonPath('data.tickets_by_priority.low', 10);
    });

    it('calculates average first response time', function () {
        $user = User::factory()->create();

        Ticket::factory()->forOrganization($user->organization_id)->create([
            'created_at'        => now()->subHours(3),
            'first_response_at' => now()->subHours(2), // 1 hour = 3600 seconds
        ]);

        Ticket::factory()->forOrganization($user->organization_id)->create([
            'created_at'        => now()->subHours(3),
            'first_response_at' => now()->subHours(1), // 2 hours = 7200 seconds
        ]);

        // Average = (3600 + 7200) / 2 = 5400 seconds

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/dashboard/metrics')
            ->assertOk()
            ->assertJsonPath('data.average_first_response_time_seconds', 5400.0);
    });

    it('returns null for average response time when no tickets have been responded to', function () {
        $user = User::factory()->create();

        Ticket::factory()->forOrganization($user->organization_id)->create([
            'first_response_at' => null,
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/dashboard/metrics')
            ->assertOk()
            ->assertJsonPath('data.average_first_response_time_seconds', null);
    });

    it('calculates SLA breach rate correctly', function () {
        $user = User::factory()->create();

        // 2 tickets breached (past SLA deadline, still open)
        Ticket::factory()->forOrganization($user->organization_id)->create([
            'status'     => 'open',
            'sla_due_at' => now()->subDay(),
        ]);
        Ticket::factory()->forOrganization($user->organization_id)->create([
            'status'     => 'pending',
            'sla_due_at' => now()->subHour(),
        ]);

        // 2 tickets within SLA
        Ticket::factory()->forOrganization($user->organization_id)->create([
            'status'     => 'open',
            'sla_due_at' => now()->addDay(),
        ]);
        Ticket::factory()->forOrganization($user->organization_id)->create([
            'status'     => 'resolved',
            'sla_due_at' => now()->subDay(), // resolved, so not counted as breached
        ]);

        // 4 total with SLA, 2 breached = 50%
        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/dashboard/metrics')
            ->assertOk()
            ->assertJsonPath('data.sla_breach_rate', 50.0);
    });

    it('returns 0 SLA breach rate when no tickets have SLA deadlines', function () {
        $user = User::factory()->create();

        Ticket::factory()->forOrganization($user->organization_id)->create([
            'sla_due_at' => null,
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/dashboard/metrics')
            ->assertOk()
            ->assertJsonPath('data.sla_breach_rate', 0.0);
    });

    it('returns 30 days of daily ticket volume', function () {
        $user = User::factory()->create();

        Ticket::factory()->forOrganization($user->organization_id)->count(3)->create([
            'created_at' => today(),
        ]);
        Ticket::factory()->forOrganization($user->organization_id)->count(2)->create([
            'created_at' => today()->subDays(1),
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/dashboard/metrics')
            ->assertOk();

        $volume = $response->json('data.daily_ticket_volume');

        expect($volume)->toHaveCount(30);

        // Last entry should be today
        expect($volume[29]['date'])->toBe(today()->format('Y-m-d'));
        expect($volume[29]['count'])->toBe(3);

        // Second to last should be yesterday
        expect($volume[28]['date'])->toBe(today()->subDay()->format('Y-m-d'));
        expect($volume[28]['count'])->toBe(2);
    });

    it('includes days with zero tickets in the volume array', function () {
        $user = User::factory()->create();

        // Create a single ticket today, leave all other days empty
        Ticket::factory()->forOrganization($user->organization_id)->create([
            'created_at' => today(),
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/dashboard/metrics')
            ->assertOk();

        $volume = collect($response->json('data.daily_ticket_volume'));

        // Most days should be 0
        $zeroDays = $volume->where('count', 0);
        expect($zeroDays)->toHaveCount(29);

        // Today should be 1
        $today = $volume->where('date', today()->format('Y-m-d'))->first();
        expect($today['count'])->toBe(1);
    });

    it('scopes metrics to the authenticated users organization only', function () {
        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();

        $userA = User::factory()->create(['organization_id' => $orgA->id]);

        Ticket::factory()->forOrganization($orgA->id)->count(5)->create(['status' => 'open']);
        Ticket::factory()->forOrganization($orgB->id)->count(20)->create(['status' => 'open']);

        $this->actingAs($userA, 'sanctum')
            ->getJson('/api/v1/dashboard/metrics')
            ->assertOk()
            ->assertJsonPath('data.tickets_by_status.open', 5); // Only org A's tickets
    });

    it('caches the metrics result', function () {
        $user = User::factory()->create();

        // First request populates the cache
        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/dashboard/metrics')
            ->assertOk();

        // Add a new ticket after the cache is populated
        Ticket::factory()->forOrganization($user->organization_id)->create([
            'status' => 'open',
        ]);

        // Cached response should still show 0 open tickets
        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/dashboard/metrics')
            ->assertOk();

        expect($response->json('data.tickets_by_status.open'))->toBe(0);
    });

    it('handles empty organization gracefully', function () {
        $user = User::factory()->create();

        // No tickets at all
        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/dashboard/metrics')
            ->assertOk()
            ->assertJsonPath('data.tickets_by_status', [])
            ->assertJsonPath('data.tickets_by_priority', [])
            ->assertJsonPath('data.average_first_response_time_seconds', null)
            ->assertJsonPath('data.sla_breach_rate', 0.0);
    });
});
