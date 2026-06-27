<?php

use App\Models\Organization;
use App\Models\Ticket;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

describe('Ticket Reassign', function () {

    it('allows an admin to reassign to a teammate in the same org', function () {
        $org = Organization::factory()->create();
        $admin = User::factory()->for($org)->create(['role' => User::ROLE_ADMIN]);
        $agent2 = User::factory()->for($org)->create(['role' => User::ROLE_AGENT]);
        $currentAssignee = User::factory()->for($org)->create(['role' => User::ROLE_AGENT]);

        $ticket = Ticket::factory()->for($org)->create([
            'assigned_to' => $currentAssignee->id,
            'status' => Ticket::STATUS_CLAIMED,
            'first_response_at' => now()->subMinutes(30),
        ]);

        Sanctum::actingAs($admin);

        $response = $this->postJson("/api/tickets/{$ticket->id}/reassign", [
            'user_id' => $agent2->id,
        ]);

        $response->assertOk()
            ->assertJsonPath('message', "Ticket reassigned to {$agent2->name}.")
            ->assertJsonPath('ticket.assigned_to', $agent2->id)
            ->assertJsonPath('first_response_recorded', false);

        expect($ticket->fresh()->assigned_to)->toBe($agent2->id);
    });

    it('prevents reassigning to a user in a different organization', function () {
        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();
        $admin = User::factory()->for($orgA)->create(['role' => User::ROLE_ADMIN]);
        $crossOrgUser = User::factory()->for($orgB)->create();

        $ticket = Ticket::factory()->for($orgA)->create([
            'assigned_to' => $admin->id,
        ]);

        Sanctum::actingAs($admin);

        $response = $this->postJson("/api/tickets/{$ticket->id}/reassign", [
            'user_id' => $crossOrgUser->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'The selected user is not available in your organization.');

        // Ticket should not change
        expect($ticket->fresh()->assigned_to)->toBe($admin->id);
    });

    it('prevents viewers from reassigning', function () {
        $org = Organization::factory()->create();
        $viewer = User::factory()->for($org)->create(['role' => User::ROLE_VIEWER]);
        $agent = User::factory()->for($org)->create(['role' => User::ROLE_AGENT]);
        $ticket = Ticket::factory()->for($org)->create();

        Sanctum::actingAs($viewer);

        $this->postJson("/api/tickets/{$ticket->id}/reassign", [
            'user_id' => $agent->id,
        ])->assertForbidden();
    });

    it('validates user_id is required', function () {
        $org = Organization::factory()->create();
        $admin = User::factory()->for($org)->create(['role' => User::ROLE_ADMIN]);
        $ticket = Ticket::factory()->for($org)->create();

        Sanctum::actingAs($admin);

        $this->postJson("/api/tickets/{$ticket->id}/reassign", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['user_id']);
    });

    it('validates user_id exists', function () {
        $org = Organization::factory()->create();
        $admin = User::factory()->for($org)->create(['role' => User::ROLE_ADMIN]);
        $ticket = Ticket::factory()->for($org)->create();

        Sanctum::actingAs($admin);

        $this->postJson("/api/tickets/{$ticket->id}/reassign", [
            'user_id' => 999999,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['user_id']);
    });

    it('returns 404 for tickets outside the org', function () {
        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();
        $admin = User::factory()->for($orgA)->create(['role' => User::ROLE_ADMIN]);
        $agent = User::factory()->for($orgA)->create();
        $ticket = Ticket::factory()->for($orgB)->create();

        Sanctum::actingAs($admin);

        $this->postJson("/api/tickets/{$ticket->id}/reassign", [
            'user_id' => $agent->id,
        ])->assertNotFound();
    });

    it('records first_response_at on reassign if not yet set', function () {
        $org = Organization::factory()->create();
        $admin = User::factory()->for($org)->create(['role' => User::ROLE_ADMIN]);
        $agent = User::factory()->for($org)->create(['role' => User::ROLE_AGENT]);
        $ticket = Ticket::factory()->for($org)->create([
            'assigned_to' => null,
            'first_response_at' => null,
        ]);

        Sanctum::actingAs($admin);

        $this->postJson("/api/tickets/{$ticket->id}/reassign", [
            'user_id' => $agent->id,
        ])->assertOk()
            ->assertJsonPath('first_response_recorded', true);

        expect($ticket->fresh()->first_response_at)->not()->toBeNull();
    });
});
