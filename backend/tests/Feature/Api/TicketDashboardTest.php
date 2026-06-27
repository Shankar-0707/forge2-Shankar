<?php

use App\Models\Organization;
use App\Models\Ticket;
use App\Models\User;

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->user = User::factory()->create(['organization_id' => $this->org->id]);
    $this->otherOrg = Organization::factory()->create();
});

it('returns paginated tickets for authenticated user', function () {
    Ticket::factory()->count(3)->create([
        'organization_id' => $this->org->id,
        'requester_id' => $this->user->id,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/tickets');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'ticket_number', 'title', 'status', 'priority', 'sla_status'],
            ],
            'current_page',
            'total',
        ])
        ->assertJsonPath('total', 3);
});

it('prevents unauthenticated access', function () {
    $this->getJson('/api/tickets')->assertUnauthorized();
});

it('scopes tickets to the user organization', function () {
    Ticket::factory()->create([
        'organization_id' => $this->org->id,
        'requester_id' => $this->user->id,
    ]);

    Ticket::factory()->create([
        'organization_id' => $this->otherOrg->id,
        'requester_id' => User::factory()->create(['organization_id' => $this->otherOrg->id]),
    ]);

    $response = $this->actingAs($this->user)->getJson('/api/tickets');

    $response->assertOk()
        ->assertJsonPath('total', 1);
});

it('filters tickets by status', function () {
    Ticket::factory()->create([
        'organization_id' => $this->org->id,
        'requester_id' => $this->user->id,
        'status' => Ticket::STATUS_OPEN,
    ]);

    Ticket::factory()->create([
        'organization_id' => $this->org->id,
        'requester_id' => $this->user->id,
        'status' => Ticket::STATUS_RESOLVED,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/tickets?status=open');

    $response->assertOk()
        ->assertJsonPath('total', 1)
        ->assertJsonPath('data.0.status', 'open');
});

it('filters tickets by priority', function () {
    Ticket::factory()->create([
        'organization_id' => $this->org->id,
        'requester_id' => $this->user->id,
        'priority' => Ticket::PRIORITY_URGENT,
    ]);

    Ticket::factory()->create([
        'organization_id' => $this->org->id,
        'requester_id' => $this->user->id,
        'priority' => Ticket::PRIORITY_LOW,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/tickets?priority=urgent');

    $response->assertOk()
        ->assertJsonPath('total', 1)
        ->assertJsonPath('data.0.priority', 'urgent');
});

it('filters tickets by assignee_id', function () {
    $agent = User::factory()->create(['organization_id' => $this->org->id]);

    Ticket::factory()->create([
        'organization_id' => $this->org->id,
        'requester_id' => $this->user->id,
        'assignee_id' => $agent->id,
    ]);

    Ticket::factory()->create([
        'organization_id' => $this->org->id,
        'requester_id' => $this->user->id,
        'assignee_id' => null,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson("/api/tickets?assignee_id={$agent->id}");

    $response->assertOk()
        ->assertJsonPath('total', 1);
});

it('filters unassigned tickets', function () {
    Ticket::factory()->create([
        'organization_id' => $this->org->id,
        'requester_id' => $this->user->id,
        'assignee_id' => null,
    ]);

    $agent = User::factory()->create(['organization_id' => $this->org->id]);
    Ticket::factory()->create([
        'organization_id' => $this->org->id,
        'requester_id' => $this->user->id,
        'assignee_id' => $agent->id,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/tickets?assignee_id=unassigned');

    $response->assertOk()
        ->assertJsonPath('total', 1)
        ->assertJsonPath('data.0.assignee', null);
});

it('searches tickets by title and ticket number', function () {
    Ticket::factory()->create([
        'organization_id' => $this->org->id,
        'requester_id' => $this->user->id,
        'title' => 'Email server down',
        'ticket_number' => 'TKT-EM-1234',
    ]);

    Ticket::factory()->create([
        'organization_id' => $this->org->id,
        'requester_id' => $this->user->id,
        'title' => 'Password reset',
        'ticket_number' => 'TKT-PW-5678',
    ]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/tickets?search=Email');

    $response->assertOk()
        ->assertJsonPath('total', 1)
        ->assertJsonPath('data.0.title', 'Email server down');
});

it('computes sla_status correctly for breached ticket', function () {
    $ticket = Ticket::factory()->create([
        'organization_id' => $this->org->id,
        'requester_id' => $this->user->id,
        'status' => Ticket::STATUS_OPEN,
        'sla_due_at' => now()->subHours(2),
    ]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/tickets');

    $response->assertOk()
        ->assertJsonPath('data.0.sla_status', 'breached');
});

it('computes sla_status correctly for at-risk ticket', function () {
    $ticket = Ticket::factory()->create([
        'organization_id' => $this->org->id,
        'requester_id' => $this->user->id,
        'status' => Ticket::STATUS_OPEN,
        'sla_due_at' => now()->addMinutes(45),
    ]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/tickets');

    $response->assertOk()
        ->assertJsonPath('data.0.sla_status', 'at_risk');
});

it('computes sla_status correctly for on-track ticket', function () {
    $ticket = Ticket::factory()->create([
        'organization_id' => $this->org->id,
        'requester_id' => $this->user->id,
        'status' => Ticket::STATUS_OPEN,
        'sla_due_at' => now()->addHours(8),
    ]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/tickets');

    $response->assertOk()
        ->assertJsonPath('data.0.sla_status', 'on_track');
});

it('returns agents for the organization', function () {
    User::factory()->count(3)->create(['organization_id' => $this->org->id]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/agents');

    $response->assertOk()
        ->assertJsonStructure(['data' => [['id', 'name', 'email']]]);
});

it('does not return agents from other organizations', function () {
    $orgAgent = User::factory()->create(['organization_id' => $this->org->id, 'name' => 'Org Agent']);
    $otherAgent = User::factory()->create(['organization_id' => $this->otherOrg->id, 'name' => 'Other Agent']);

    $response = $this->actingAs($this->user)
        ->getJson('/api/agents');

    $response->assertOk();

    $names = collect($response->json('data'))->pluck('name');
    expect($names)->toContain('Org Agent')
        ->and($names)->not->toContain('Other Agent');
});
