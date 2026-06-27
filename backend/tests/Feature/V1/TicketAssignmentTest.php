<?php

use App\Models\Organization;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// Factory Helpers
// ---------------------------------------------------------------------------

function createOrganization(): Organization
{
    return Organization::factory()->create();
}

function createAgent(Organization $org, array $overrides = []): User
{
    return User::factory()->create(array_merge([
        'organization_id' => $org->id,
        'role' => 'agent',
    ], $overrides));
}

function createAdmin(Organization $org): User
{
    return User::factory()->create([
        'organization_id' => $org->id,
        'role' => 'admin',
    ]);
}

function createCustomer(Organization $org): User
{
    return User::factory()->create([
        'organization_id' => $org->id,
        'role' => 'customer',
    ]);
}

function createTicket(Organization $org, array $overrides = []): Ticket
{
    return Ticket::factory()->create(array_merge([
        'organization_id' => $org->id,
        'assignee_id' => null,
    ], $overrides));
}

// ---------------------------------------------------------------------------
// Ticket Claiming
// ---------------------------------------------------------------------------

describe('ticket claiming', function () {

    test('an agent can claim an unassigned ticket', function () {
        $org = createOrganization();
        $agent = createAgent($org);
        $ticket = createTicket($org);

        Sanctum::actingAs($agent);

        $this->postJson("/api/v1/tickets/{$ticket->id}/claim")
            ->assertOk()
            ->assertJsonPath('data.assignee.id', $agent->id);
    });

    test('claiming persists assignee_id in the database', function () {
        $org = createOrganization();
        $agent = createAgent($org);
        $ticket = createTicket($org);

        Sanctum::actingAs($agent);

        $this->postJson("/api/v1/tickets/{$ticket->id}/claim");

        $this->assertDatabaseHas('tickets', [
            'id' => $ticket->id,
            'assignee_id' => $agent->id,
        ]);
    });

    test('an admin can claim an unassigned ticket', function () {
        $org = createOrganization();
        $admin = createAdmin($org);
        $ticket = createTicket($org);

        Sanctum::actingAs($admin);

        $this->postJson("/api/v1/tickets/{$ticket->id}/claim")
            ->assertOk()
            ->assertJsonPath('data.assignee.id', $admin->id);
    });

    test('an agent can steal-claim a ticket assigned to another agent', function () {
        $org = createOrganization();
        $agentA = createAgent($org);
        $agentB = createAgent($org);
        $ticket = createTicket($org, ['assignee_id' => $agentA->id]);

        Sanctum::actingAs($agentB);

        $this->postJson("/api/v1/tickets/{$ticket->id}/claim")
            ->assertOk()
            ->assertJsonPath('data.assignee.id', $agentB->id);

        $this->assertDatabaseHas('tickets', [
            'id' => $ticket->id,
            'assignee_id' => $agentB->id,
        ]);
    });

    test('a customer cannot claim a ticket', function () {
        $org = createOrganization();
        $customer = createCustomer($org);
        $ticket = createTicket($org);

        Sanctum::actingAs($customer);

        $this->postJson("/api/v1/tickets/{$ticket->id}/claim")
            ->assertForbidden();
    });

    test('an unauthenticated user cannot claim a ticket', function () {
        $org = createOrganization();
        $ticket = createTicket($org);

        $this->postJson("/api/v1/tickets/{$ticket->id}/claim")
            ->assertUnauthorized();
    });

    test('an agent cannot claim a ticket from another organization', function () {
        $orgA = createOrganization();
        $orgB = createOrganization();
        $agent = createAgent($orgA);
        $ticket = createTicket($orgB);

        Sanctum::actingAs($agent);

        $this->postJson("/api/v1/tickets/{$ticket->id}/claim")
            ->assertNotFound();
    });

    test('claiming does not modify organization_id', function () {
        $org = createOrganization();
        $agent = createAgent($org);
        $ticket = createTicket($org);

        Sanctum::actingAs($agent);

        $this->postJson("/api/v1/tickets/{$ticket->id}/claim");

        $this->assertDatabaseHas('tickets', [
            'id' => $ticket->id,
            'organization_id' => $org->id,
        ]);
    });
});

// ---------------------------------------------------------------------------
// Ticket Assignment
// ---------------------------------------------------------------------------

describe('ticket assignment', function () {

    test('an admin can assign a ticket to an agent', function () {
        $org = createOrganization();
        $admin = createAdmin($org);
        $agent = createAgent($org);
        $ticket = createTicket($org);

        Sanctum::actingAs($admin);

        $this->postJson("/api/v1/tickets/{$ticket->id}/assign", [
            'user_id' => $agent->id,
        ])
            ->assertOk()
            ->assertJsonPath('data.assignee.id', $agent->id);

        $this->assertDatabaseHas('tickets', [
            'id' => $ticket->id,
            'assignee_id' => $agent->id,
        ]);
    });

    test('an agent can assign a ticket to another agent', function () {
        $org = createOrganization();
        $agentA = createAgent($org);
        $agentB = createAgent($org);
        $ticket = createTicket($org);

        Sanctum::actingAs($agentA);

        $this->postJson("/api/v1/tickets/{$ticket->id}/assign", [
            'user_id' => $agentB->id,
        ])
            ->assertOk()
            ->assertJsonPath('data.assignee.id', $agentB->id);
    });

    test('assigning overwrites a previous assignee', function () {
        $org = createOrganization();
        $admin = createAdmin($org);
        $agentA = createAgent($org);
        $agentB = createAgent($org);
        $ticket = createTicket($org, ['assignee_id' => $agentA->id]);

        Sanctum::actingAs($admin);

        $this->postJson("/api/v1/tickets/{$ticket->id}/assign", [
            'user_id' => $agentB->id,
        ])
            ->assertOk();

        $this->assertDatabaseHas('tickets', [
            'id' => $ticket->id,
            'assignee_id' => $agentB->id,
        ]);
    });

    test('a customer cannot assign a ticket', function () {
        $org = createOrganization();
        $customer = createCustomer($org);
        $agent = createAgent($org);
        $ticket = createTicket($org);

        Sanctum::actingAs($customer);

        $this->postJson("/api/v1/tickets/{$ticket->id}/assign", [
            'user_id' => $agent->id,
        ])
            ->assertForbidden();
    });

    test('an unauthenticated user cannot assign a ticket', function () {
        $org = createOrganization();
        $agent = createAgent($org);
        $ticket = createTicket($org);

        $this->postJson("/api/v1/tickets/{$ticket->id}/assign", [
            'user_id' => $agent->id,
        ])
            ->assertUnauthorized();
    });

    test('an agent cannot assign a ticket belonging to another organization', function () {
        $orgA = createOrganization();
        $orgB = createOrganization();
        $agent = createAgent($orgA);
        $target = createAgent($orgB);
        $ticket = createTicket($orgB);

        Sanctum::actingAs($agent);

        $this->postJson("/api/v1/tickets/{$ticket->id}/assign", [
            'user_id' => $target->id,
        ])
            ->assertNotFound();
    });

    test('cannot assign a ticket to a user from a different organization', function () {
        $orgA = createOrganization();
        $orgB = createOrganization();
        $agent = createAgent($orgA);
        $foreignAgent = createAgent($orgB);
        $ticket = createTicket($orgA);

        Sanctum::actingAs($agent);

        $this->postJson("/api/v1/tickets/{$ticket->id}/assign", [
            'user_id' => $foreignAgent->id,
        ])
            ->assertStatus(422);
    });

    test('assigning a non-existent user returns validation error', function () {
        $org = createOrganization();
        $admin = createAdmin($org);
        $ticket = createTicket($org);

        Sanctum::actingAs($admin);

        $this->postJson("/api/v1/tickets/{$ticket->id}/assign", [
            'user_id' => 999999,
        ])
            ->assertStatus(422);
    });

    test('user_id is required when assigning', function () {
        $org = createOrganization();
        $admin = createAdmin($org);
        $ticket = createTicket($org);

        Sanctum::actingAs($admin);

        $this->postJson("/api/v1/tickets/{$ticket->id}/assign", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['user_id']);
    });

    test('assignment does not modify organization_id', function () {
        $org = createOrganization();
        $admin = createAdmin($org);
        $agent = createAgent($org);
        $ticket = createTicket($org);

        Sanctum::actingAs($admin);

        $this->postJson("/api/v1/tickets/{$ticket->id}/assign", [
            'user_id' => $agent->id,
        ]);

        $this->assertDatabaseHas('tickets', [
            'id' => $ticket->id,
            'organization_id' => $org->id,
        ]);
    });
});

// ---------------------------------------------------------------------------
// my_tickets Filter
// ---------------------------------------------------------------------------

describe('my_tickets filter', function () {

    test('returns only tickets assigned to the authenticated agent', function () {
        $org = createOrganization();
        $agent = createAgent($org);
        $otherAgent = createAgent($org);

        $myTicket = createTicket($org, ['assignee_id' => $agent->id]);
        $colleagueTicket = createTicket($org, ['assignee_id' => $otherAgent->id]);

        Sanctum::actingAs($agent);

        $response = $this->getJson('/api/v1/tickets?filter=my_tickets')
            ->assertOk();

        $ids = collect($response->json('data'))->pluck('id');

        expect($ids)->toContain($myTicket->id);
        expect($ids)->not->toContain($colleagueTicket->id);
    });

    test('excludes unassigned tickets from my_tickets', function () {
        $org = createOrganization();
        $agent = createAgent($org);

        $assigned = createTicket($org, ['assignee_id' => $agent->id]);
        $unassigned = createTicket($org, ['assignee_id' => null]);

        Sanctum::actingAs($agent);

        $response = $this->getJson('/api/v1/tickets?filter=my_tickets')
            ->assertOk();

        $ids = collect($response->json('data'))->pluck('id');

        expect($ids)->toContain($assigned->id);
        expect($ids)->not->toContain($unassigned->id);
    });

    test('does not leak tickets from another organization even if same assignee', function () {
        $orgA = createOrganization();
        $orgB = createOrganization();

        $agentA = createAgent($orgA);
        $agentB = createAgent($orgB);

        $myTicket = createTicket($orgA, ['assignee_id' => $agentA->id]);
        $foreignTicket = createTicket($orgB, ['assignee_id' => $agentB->id]);

        Sanctum::actingAs($agentA);

        $response = $this->getJson('/api/v1/tickets?filter=my_tickets')
            ->assertOk();

        $ids = collect($response->json('data'))->pluck('id');

        expect($ids)->toContain($myTicket->id);
        expect($ids)->not->toContain($foreignTicket->id);
    });

    test('returns empty data set when agent has no assigned tickets', function () {
        $org = createOrganization();
        $agent = createAgent($org);

        createTicket($org, ['assignee_id' => null]);

        Sanctum::actingAs($agent);

        $this->getJson('/api/v1/tickets?filter=my_tickets')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    });

    test('an unauthenticated user cannot access my_tickets', function () {
        $this->getJson('/api/v1/tickets?filter=my_tickets')
            ->assertUnauthorized();
    });
});

// ---------------------------------------------------------------------------
// unassigned Filter
// ---------------------------------------------------------------------------

describe('unassigned filter', function () {

    test('returns only tickets without an assignee', function () {
        $org = createOrganization();
        $agent = createAgent($org);

        $unassignedA = createTicket($org, ['assignee_id' => null]);
        $unassignedB = createTicket($org, ['assignee_id' => null]);
        $assigned = createTicket($org, ['assignee_id' => $agent->id]);

        Sanctum::actingAs($agent);

        $response = $this->getJson('/api/v1/tickets?filter=unassigned')
            ->assertOk();

        $ids = collect($response->json('data'))->pluck('id');

        expect($ids)->toContain($unassignedA->id);
        expect($ids)->toContain($unassignedB->id);
        expect($ids)->not->toContain($assigned->id);
    });

    test('does not leak unassigned tickets from another organization', function () {
        $orgA = createOrganization();
        $orgB = createOrganization();
        $agent = createAgent($orgA);

        $localUnassigned = createTicket($orgA, ['assignee_id' => null]);
        $foreignUnassigned = createTicket($orgB, ['assignee_id' => null]);

        Sanctum::actingAs($agent);

        $response = $this->getJson('/api/v1/tickets?filter=unassigned')
            ->assertOk();

        $ids = collect($response->json('data'))->pluck('id');

        expect($ids)->toContain($localUnassigned->id);
        expect($ids)->not->toContain($foreignUnassigned->id);
    });

    test('returns empty data set when all tickets are assigned', function () {
        $org = createOrganization();
        $agent = createAgent($org);

        createTicket($org, ['assignee_id' => $agent->id]);
        createTicket($org, ['assignee_id' => $agent->id]);

        Sanctum::actingAs($agent);

        $this->getJson('/api/v1/tickets?filter=unassigned')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    });

    test('an unauthenticated user cannot access unassigned filter', function () {
        $this->getJson('/api/v1/tickets?filter=unassigned')
            ->assertUnauthorized();
    });
});
