<?php

use App\Models\Organization;
use App\Models\Ticket;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

describe('Ticket Claim', function () {

    it('allows an agent to claim an unassigned ticket', function () {
        $org = Organization::factory()->create();
        $agent = User::factory()->for($org)->create(['role' => User::ROLE_AGENT]);
        $ticket = Ticket::factory()->for($org)->create([
            'assigned_to' => null,
            'status' => Ticket::STATUS_OPEN,
            'first_response_at' => null,
        ]);

        Sanctum::actingAs($agent);

        $response = $this->postJson("/api/tickets/{$ticket->id}/claim");

        $response->assertOk()
            ->assertJsonPath('message', 'Ticket claimed successfully.')
            ->assertJsonPath('ticket.assigned_to', $agent->id)
            ->assertJsonPath('ticket.status', Ticket::STATUS_CLAIMED)
            ->assertJsonPath('first_response_recorded', true);

        expect($ticket->fresh()->assigned_to)->toBe($agent->id)
            ->and($ticket->fresh()->status)->toBe(Ticket::STATUS_CLAIMED)
            ->and($ticket->fresh()->first_response_at)->not()->toBeNull();
    });

    it('records first_response_at only once on subsequent claims', function () {
        $org = Organization::factory()->create();
        $agent = User::factory()->for($org)->create(['role' => User::ROLE_AGENT]);
        $ticket = Ticket::factory()->for($org)->create([
            'assigned_to' => null,
            'first_response_at' => now()->subHour(),
        ]);

        Sanctum::actingAs($agent);

        $response = $this->postJson("/api/tickets/{$ticket->id}/claim");

        $response->assertOk()
            ->assertJsonPath('first_response_recorded', false);

        // first_response_at should not change
        expect($ticket->fresh()->first_response_at)->toEqual($ticket->first_response_at);
    });

    it('prevents viewers from claiming tickets', function () {
        $org = Organization::factory()->create();
        $viewer = User::factory()->for($org)->create(['role' => User::ROLE_VIEWER]);
        $ticket = Ticket::factory()->for($org)->create([
            'assigned_to' => null,
        ]);

        Sanctum::actingAs($viewer);

        $this->postJson("/api/tickets/{$ticket->id}/claim")
            ->assertForbidden();
    });

    it('returns 404 when claiming a ticket from another organization', function () {
        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();
        $agent = User::factory()->for($orgA)->create(['role' => User::ROLE_AGENT]);
        $ticket = Ticket::factory()->for($orgB)->create();

        Sanctum::actingAs($agent);

        $this->postJson("/api/tickets/{$ticket->id}/claim")
            ->assertNotFound();
    });

    it('requires authentication', function () {
        $org = Organization::factory()->create();
        $ticket = Ticket::factory()->for($org)->create();

        $this->postJson("/api/tickets/{$ticket->id}/claim")
            ->assertUnauthorized();
    });

    it('allows an admin to claim a ticket', function () {
        $org = Organization::factory()->create();
        $admin = User::factory()->for($org)->create(['role' => User::ROLE_ADMIN]);
        $ticket = Ticket::factory()->for($org)->create([
            'assigned_to' => null,
            'first_response_at' => null,
        ]);

        Sanctum::actingAs($admin);

        $this->postJson("/api/tickets/{$ticket->id}/claim")
            ->assertOk()
            ->assertJsonPath('ticket.assigned_to', $admin->id);
    });
});
