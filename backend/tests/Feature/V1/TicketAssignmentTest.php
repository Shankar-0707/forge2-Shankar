<?php

use App\Models\Organization;
use App\Models\Ticket;
use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\postJson;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

describe('Ticket Assignment', function () {

    beforeEach(function () {
        $this->org = Organization::factory()->create();
        $this->otherOrg = Organization::factory()->create();

        $this->admin = User::factory()->admin()->create([
            'organization_id' => $this->org->id,
        ]);

        $this->agent = User::factory()->agent()->create([
            'organization_id' => $this->org->id,
        ]);

        $this->agent2 = User::factory()->agent()->create([
            'organization_id' => $this->org->id,
        ]);

        $this->regularUser = User::factory()->create([
            'organization_id' => $this->org->id,
        ]);
    });

    /*
    |----------------------------------------------------------------------
    | POST /api/v1/tickets/{ticket}/assign
    |----------------------------------------------------------------------
    */
    describe('POST tickets/{id}/assign', function () {

        it('allows admin to assign ticket to agent', function () {
            $ticket = Ticket::factory()->unassigned()->create([
                'organization_id' => $this->org->id,
            ]);

            $response = actingAs($this->admin)
                ->postJson("/api/v1/tickets/{$ticket->id}/assign", [
                    'agent_id' => $this->agent->id,
                ]);

            $response->assertOk()
                ->assertJsonPath('data.agent.id', $this->agent->id)
                ->assertJsonPath('data.is_assigned', true);

            expect($ticket->fresh()->agent_id)->toBe($this->agent->id);
        });

        it('allows agent to assign ticket to another agent', function () {
            $ticket = Ticket::factory()->unassigned()->create([
                'organization_id' => $this->org->id,
            ]);

            $response = actingAs($this->agent)
                ->postJson("/api/v1/tickets/{$ticket->id}/assign", [
                    'agent_id' => $this->agent2->id,
                ]);

            $response->assertOk();
            expect($ticket->fresh()->agent_id)->toBe($this->agent2->id);
        });

        it('allows reassigning an already-assigned ticket', function () {
            $ticket = Ticket::factory()->assignedTo($this->agent)->create([
                'organization_id' => $this->org->id,
            ]);

            $response = actingAs($this->admin)
                ->postJson("/api/v1/tickets/{$ticket->id}/assign", [
                    'agent_id' => $this->agent2->id,
                ]);

            $response->assertOk();
            expect($ticket->fresh()->agent_id)->toBe($this->agent2->id);
        });

        it('prevents regular user from assigning tickets', function () {
            $ticket = Ticket::factory()->unassigned()->create([
                'organization_id' => $this->org->id,
            ]);

            $response = actingAs($this->regularUser)
                ->postJson("/api/v1/tickets/{$ticket->id}/assign", [
                    'agent_id' => $this->agent->id,
                ]);

            $response->assertForbidden();
        });

        it('prevents assigning to a user outside the organization', function () {
            $ticket = Ticket::factory()->unassigned()->create([
                'organization_id' => $this->org->id,
            ]);

            $otherOrgAgent = User::factory()->agent()->create([
                'organization_id' => $this->otherOrg->id,
            ]);

            $response = actingAs($this->admin)
                ->postJson("/api/v1/tickets/{$ticket->id}/assign", [
                    'agent_id' => $otherOrgAgent->id,
                ]);

            $response->assertUnprocessable();
        });

        it('prevents assigning to a user without agent or admin role', function () {
            $ticket = Ticket::factory()->unassigned()->create([
                'organization_id' => $this->org->id,
            ]);

            $response = actingAs($this->admin)
                ->postJson("/api/v1/tickets/{$ticket->id}/assign", [
                    'agent_id' => $this->regularUser->id,
                ]);

            $response->assertUnprocessable();
        });

        it('returns 404 for ticket outside organization', function () {
            $otherOrgTicket = Ticket::factory()->unassigned()->create([
                'organization_id' => $this->otherOrg->id,
            ]);

            $response = actingAs($this->admin)
                ->postJson("/api/v1/tickets/{$otherOrgTicket->id}/assign", [
                    'agent_id' => $this->agent->id,
                ]);

            $response->assertNotFound();
        });

        it('requires agent_id field', function () {
            $ticket = Ticket::factory()->unassigned()->create([
                'organization_id' => $this->org->id,
            ]);

            $response = actingAs($this->admin)
                ->postJson("/api/v1/tickets/{$ticket->id}/assign", []);

            $response->assertUnprocessable()
                ->assertJsonValidationErrors(['agent_id']);
        });

        it('rejects non-existent agent_id', function () {
            $ticket = Ticket::factory()->unassigned()->create([
                'organization_id' => $this->org->id,
            ]);

            $response = actingAs($this->admin)
                ->postJson("/api/v1/tickets/{$ticket->id}/assign", [
                    'agent_id' => 999999,
                ]);

            $response->assertUnprocessable()
                ->assertJsonValidationErrors(['agent_id']);
        });

        it('requires authentication', function () {
            $ticket = Ticket::factory()->unassigned()->create([
                'organization_id' => $this->org->id,
            ]);

            $response = postJson("/api/v1/tickets/{$ticket->id}/assign", [
                'agent_id' => $this->agent->id,
            ]);

            $response->assertUnauthorized();
        });
    });

    /*
    |----------------------------------------------------------------------
    | POST /api/v1/tickets/{ticket}/claim
    |----------------------------------------------------------------------
    */
    describe('POST tickets/{id}/claim', function () {

        it('allows agent to claim unassigned ticket', function () {
            $ticket = Ticket::factory()->unassigned()->create([
                'organization_id' => $this->org->id,
            ]);

            $response = actingAs($this->agent)
                ->postJson("/api/v1/tickets/{$ticket->id}/claim");

            $response->assertOk()
                ->assertJsonPath('data.agent.id', $this->agent->id);

            expect($ticket->fresh()->agent_id)->toBe($this->agent->id);
        });

        it('allows admin to claim ticket', function () {
            $ticket = Ticket::factory()->unassigned()->create([
                'organization_id' => $this->org->id,
            ]);

            $response = actingAs($this->admin)
                ->postJson("/api/v1/tickets/{$ticket->id}/claim");

            $response->assertOk();
            expect($ticket->fresh()->agent_id)->toBe($this->admin->id);
        });

        it('prevents regular user from claiming tickets', function () {
            $ticket = Ticket::factory()->unassigned()->create([
                'organization_id' => $this->org->id,
            ]);

            $response = actingAs($this->regularUser)
                ->postJson("/api/v1/tickets/{$ticket->id}/claim");

            $response->assertForbidden();
        });

        it('prevents claiming ticket assigned to another agent', function () {
            $ticket = Ticket::factory()->assignedTo($this->agent)->create([
                'organization_id' => $this->org->id,
            ]);

            $response = actingAs($this->agent2)
                ->postJson("/api/v1/tickets/{$ticket->id}/claim");

            $response->assertStatus(422)
                ->assertJsonPath('message', 'This ticket is already assigned to another agent. Use the assign endpoint to reassign.');
        });

        it('allows claiming ticket already assigned to self (idempotent)', function () {
            $ticket = Ticket::factory()->assignedTo($this->agent)->create([
                'organization_id' => $this->org->id,
            ]);

            $response = actingAs($this->agent)
                ->postJson("/api/v1/tickets/{$ticket->id}/claim");

            $response->assertOk()
                ->assertJsonPath('data.agent.id', $this->agent->id);
        });

        it('returns 404 for ticket outside organization', function () {
            $otherOrgTicket = Ticket::factory()->unassigned()->create([
                'organization_id' => $this->otherOrg->id,
            ]);

            $response = actingAs($this->agent)
                ->postJson("/api/v1/tickets/{$otherOrgTicket->id}/claim");

            $response->assertNotFound();
        });

        it('requires authentication', function () {
            $ticket = Ticket::factory()->unassigned()->create([
                'organization_id' => $this->org->id,
            ]);

            $response = postJson("/api/v1/tickets/{$ticket->id}/claim");

            $response->assertUnauthorized();
        });
    });
});

/*
|----------------------------------------------------------------------
| Ticket Index Filtering
|----------------------------------------------------------------------
*/
describe('GET /api/v1/tickets filters', function () {

    uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

    beforeEach(function () {
        $this->org = Organization::factory()->create();
        $this->otherOrg = Organization::factory()->create();

        $this->admin = User::factory()->admin()->create([
            'organization_id' => $this->org->id,
        ]);

        $this->agent = User::factory()->agent()->create([
            'organization_id' => $this->org->id,
        ]);

        $this->agent2 = User::factory()->agent()->create([
            'organization_id' => $this->org->id,
        ]);
    });

    it('filters my_tickets correctly', function () {
        $myTicket = Ticket::factory()->assignedTo($this->agent)->create([
            'organization_id' => $this->org->id,
        ]);

        Ticket::factory()->assignedTo($this->agent2)->create([
            'organization_id' => $this->org->id,
        ]);

        Ticket::factory()->unassigned()->create([
            'organization_id' => $this->org->id,
        ]);

        $response = actingAs($this->agent)
            ->getJson('/api/v1/tickets?filter=my_tickets');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $myTicket->id);
    });

    it('filters unassigned correctly', function () {
        Ticket::factory()->assignedTo($this->agent)->create([
            'organization_id' => $this->org->id,
        ]);

        $unassignedTicket = Ticket::factory()->unassigned()->create([
            'organization_id' => $this->org->id,
        ]);

        $response = actingAs($this->admin)
            ->getJson('/api/v1/tickets?filter=unassigned');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $unassignedTicket->id)
            ->assertJsonPath('data.0.is_assigned', false);
    });

    it('filters assigned correctly', function () {
        $assignedTicket = Ticket::factory()->assignedTo($this->agent)->create([
            'organization_id' => $this->org->id,
        ]);

        Ticket::factory()->unassigned()->create([
            'organization_id' => $this->org->id,
        ]);

        $response = actingAs($this->admin)
            ->getJson('/api/v1/tickets?filter=assigned');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $assignedTicket->id);
    });

    it('returns all tickets when no filter is provided', function () {
        Ticket::factory()->assignedTo($this->agent)->create([
            'organization_id' => $this->org->id,
        ]);

        Ticket::factory()->unassigned()->create([
            'organization_id' => $this->org->id,
        ]);

        $response = actingAs($this->admin)
            ->getJson('/api/v1/tickets');

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    });

    it('rejects invalid filter value', function () {
        $response = actingAs($this->admin)
            ->getJson('/api/v1/tickets?filter=invalid_filter');

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['filter']);
    });

    it('only returns tickets from the users organization', function () {
        $ownTicket = Ticket::factory()->unassigned()->create([
            'organization_id' => $this->org->id,
        ]);

        Ticket::factory()->unassigned()->create([
            'organization_id' => $this->otherOrg->id,
        ]);

        $response = actingAs($this->admin)
            ->getJson('/api/v1/tickets?filter=unassigned');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $ownTicket->id);
    });

    it('returns empty data for my_tickets when agent has no tickets', function () {
        Ticket::factory()->assignedTo($this->agent2)->create([
            'organization_id' => $this->org->id,
        ]);

        Ticket::factory()->unassigned()->create([
            'organization_id' => $this->org->id,
        ]);

        $response = actingAs($this->agent)
            ->getJson('/api/v1/tickets?filter=my_tickets');

        $response->assertOk()
            ->assertJsonCount(0, 'data');
    });

    it('respects per_page parameter', function () {
        Ticket::factory()->count(25)->create([
            'organization_id' => $this->org->id,
        ]);

        $response = actingAs($this->admin)
            ->getJson('/api/v1/tickets?per_page=10');

        $response->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('meta.per_page', 10)
            ->assertJsonPath('meta.total', 25);
    });

    it('includes agent relationship in response', function () {
        $ticket = Ticket::factory()->assignedTo($this->agent)->create([
            'organization_id' => $this->org->id,
        ]);

        $response = actingAs($this->admin)
            ->getJson('/api/v1/tickets');

        $response->assertOk()
            ->assertJsonPath('data.0.agent.id', $this->agent->id)
            ->assertJsonPath('data.0.agent.name', $this->agent->name);
    });

    it('requires authentication', function () {
        $response = \Illuminate\Support\Facades\Http::withHeaders([])
            ->get('/api/v1/tickets');

        $response = \Pest\Laravel\getJson('/api/v1/tickets');

        $response->assertUnauthorized();
    });
});
