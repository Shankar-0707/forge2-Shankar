<?php

use App\Models\Organization;
use App\Models\SlaPolicy;
use App\Models\Ticket;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->org = Organization::factory()->create();

    SlaPolicy::factory()->create([
        'organization_id'      => $this->org->id,
        'priority'             => 'high',
        'response_time_limit'  => 120,  // 2h
        'resolution_time_limit'=> 1440, // 24h
        'is_active'            => true,
    ]);
});

it('computes positive remaining time when within SLA', function () {
    $ticket = Ticket::factory()->create([
        'organization_id' => $this->org->id,
        'priority'        => 'high',
        'status'          => 'open',
        'created_at'      => now()->subMinutes(30),
    ]);

    // 120 min limit - 30 min elapsed = 90 min remaining = 5400s
    expect($ticket->response_time_remaining)
        ->toBeGreaterThan(5000)
        ->toBeLessThan(5401)
        ->and($ticket->response_sla_breached)->toBeFalse()
        ->and($ticket->resolution_sla_breached)->toBeFalse();
});

it('detects response SLA breach when exceeded', function () {
    $ticket = Ticket::factory()->create([
        'organization_id'  => $this->org->id,
        'priority'         => 'high',
        'status'           => 'open',
        'created_at'       => now()->subHours(3),
        'first_response_at'=> null,
    ]);

    expect($ticket->response_time_remaining)
        ->toBe(0)
        ->and($ticket->response_sla_breached)->toBeTrue();
});

it('freezes response SLA once ticket is responded to', function () {
    $created = now()->subMinutes(30);
    $ticket  = Ticket::factory()->create([
        'organization_id'  => $this->org->id,
        'priority'         => 'high',
        'status'           => 'open',
        'created_at'       => $created,
        'first_response_at'=> $created->copy()->addMinutes(20),
    ]);

    // 120 - 20 = 100 min remaining, frozen
    expect($ticket->response_time_remaining)
        ->toBeGreaterThan(5800)
        ->toBeLessThan(6001)
        ->and($ticket->response_sla_breached)->toBeFalse();
});

it('detects response breach when first response was slow', function () {
    $ticket = Ticket::factory()->create([
        'organization_id'  => $this->org->id,
        'priority'         => 'high',
        'status'           => 'open',
        'created_at'       => now()->subHours(5),
        'first_response_at'=> now()->subHours(4), // 60 min, over 120 limit
    ]);

    expect($ticket->response_sla_breached)->toBeTrue();
});

it('detects resolution SLA breach', function () {
    $ticket = Ticket::factory()->create([
        'organization_id' => $this->org->id,
        'priority'        => 'high',
        'status'          => 'open',
        'created_at'      => now()->subHours(25), // > 24h
    ]);

    expect($ticket->resolution_time_remaining)
        ->toBe(0)
        ->and($ticket->resolution_sla_breached)->toBeTrue();
});

it('freezes SLA clock once ticket is resolved', function () {
    $resolvedAt = now()->subMinutes(60);
    $ticket     = Ticket::factory()->create([
        'organization_id' => $this->org->id,
        'priority'        => 'high',
        'status'          => 'resolved',
        'created_at'      => now()->subMinutes(120),
        'resolved_at'     => $resolvedAt,
    ]);

    // Elapsed should be 60 min (resolved_at - created_at), not now - created_at
    expect($ticket->resolution_time_remaining)
        ->toBeGreaterThan(1380 * 60 - 5)   // ~1380 min remaining
        ->toBeLessThan((1440 - 60) * 60 + 5);
});

it('formats SLA duration correctly', function () {
    $ticket = Ticket::factory()->create([
        'organization_id' => $this->org->id,
        'priority'        => 'high',
        'status'          => 'open',
        'created_at'      => now()->subMinutes(30),
    ]);

    $formatted = $ticket->response_time_remaining_formatted;
    expect($formatted)->toContain('m');
});

it('returns safe defaults when no policy applies', function () {
    $ticket = Ticket::factory()->create([
        'organization_id' => $this->org->id,
        'priority'        => 'low', // no policy seeded for low in this org
        'status'          => 'open',
    ]);

    expect($ticket->slaPolicy())->toBeNull()
        ->and($ticket->response_time_remaining)->toBe(0)
        ->and($ticket->response_sla_breached)->toBeFalse();
});
