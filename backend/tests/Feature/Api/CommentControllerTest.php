<?php

use App\Models\Comment;
use App\Models\Organization;
use App\Models\Ticket;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

describe('CommentController - index', function () {

    it('returns all comments for staff including internal notes', function () {
        $org     = Organization::factory()->create();
        $agent   = User::factory()->for($org)->create(['role' => 'agent']);
        $ticket  = Ticket::factory()->for($org)->create(['user_id' => $agent->id]);

        Comment::factory()->for($ticket)->for($agent)->for($org)->create([
            'body'        => 'Public reply from agent',
            'is_internal' => false,
        ]);

        Comment::factory()->for($ticket)->for($agent)->for($org)->create([
            'body'        => 'Internal note — do not share',
            'is_internal' => true,
        ]);

        Sanctum::actingAs($agent);

        $this->getJson("/api/tickets/{$ticket->id}/comments")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment(['body' => 'Public reply from agent'])
            ->assertJsonFragment(['body' => 'Internal note — do not share']);
    });

    it('hides internal notes from customers via the visibleTo scope', function () {
        $org      = Organization::factory()->create();
        $customer = User::factory()->for($org)->create(['role' => 'customer']);
        $ticket   = Ticket::factory()->for($org)->create(['user_id' => $customer->id]);

        Comment::factory()->for($ticket)->for($customer)->for($org)->create([
            'body'        => 'Public reply',
            'is_internal' => false,
        ]);

        Comment::factory()->for($ticket)->for($customer)->for($org)->create([
            'body'        => 'Secret internal discussion',
            'is_internal' => true,
        ]);

        Sanctum::actingAs($customer);

        $this->getJson("/api/tickets/{$ticket->id}/comments")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['is_internal' => false])
            ->assertJsonMissing(['is_internal' => true])
            ->assertJsonMissing(['body' => 'Secret internal discussion']);
    });

    it('returns 404 for a ticket in a different organization', function () {
        $orgA    = Organization::factory()->create();
        $orgB    = Organization::factory()->create();
        $agent   = User::factory()->for($orgA)->create(['role' => 'agent']);
        $ticket  = Ticket::factory()->for($orgB)->create();

        Sanctum::actingAs($agent);

        $this->getJson("/api/tickets/{$ticket->id}/comments")->assertNotFound();
    });

    it('returns 404 when a customer accesses another customer’s ticket', function () {
        $org        = Organization::factory()->create();
        $customerA  = User::factory()->for($org)->create(['role' => 'customer']);
        $customerB  = User::factory()->for($org)->create(['role' => 'customer']);
        $ticket     = Ticket::factory()->for($org)->create(['user_id' => $customerA->id]);

        Sanctum::actingAs($customerB);

        $this->getJson("/api/tickets/{$ticket->id}/comments")->assertNotFound();
    });

    it('requires authentication', function () {
        $ticket = Ticket::factory()->create();

        $this->getJson("/api/tickets/{$ticket->id}/comments")->assertUnauthorized();
    });
});

describe('CommentController - store', function () {

    it('creates a public reply as a customer', function () {
        $org      = Organization::factory()->create();
        $customer = User::factory()->for($org)->create(['role' => 'customer']);
        $ticket   = Ticket::factory()->for($org)->create(['user_id' => $customer->id]);

        Sanctum::actingAs($customer);

        $this->postJson("/api/tickets/{$ticket->id}/comments", [
            'body' => 'I need help with this issue.',
        ])
            ->assertCreated()
            ->assertJsonPath('data.body', 'I need help with this issue.')
            ->assertJsonPath('data.is_internal', false);

        $this->assertDatabaseHas('comments', [
            'ticket_id'   => $ticket->id,
            'body'        => 'I need help with this issue.',
            'is_internal' => false,
        ]);
    });

    it('allows an agent to post an internal note', function () {
        $org    = Organization::factory()->create();
        $agent  = User::factory()->for($org)->create(['role' => 'agent']);
        $ticket = Ticket::factory()->for($org)->create(['user_id' => $agent->id]);

        Sanctum::actingAs($agent);

        $this->postJson("/api/tickets/{$ticket->id}/comments", [
            'body'        => 'This is an internal note for the team.',
            'is_internal' => true,
        ])
            ->assertCreated()
            ->assertJsonPath('data.is_internal', true);

        $this->assertDatabaseHas('comments', [
            'ticket_id'   => $ticket->id,
            'is_internal' => true,
        ]);
    });

    it('forces is_internal to false when a customer tries to post an internal note', function () {
        $org      = Organization::factory()->create();
        $customer = User::factory()->for($org)->create(['role' => 'customer']);
        $ticket   = Ticket::factory()->for($org)->create(['user_id' => $customer->id]);

        Sanctum::actingAs($customer);

        $this->postJson("/api/tickets/{$ticket->id}/comments", [
            'body'        => 'Malicious internal attempt',
            'is_internal' => true,
        ])
            ->assertCreated()
            ->assertJsonPath('data.is_internal', false);

        $this->assertDatabaseHas('comments', [
            'ticket_id'   => $ticket->id,
            'body'        => 'Malicious internal attempt',
            'is_internal' => false,
        ]);
    });

    it('validates that body is required', function () {
        $org    = Organization::factory()->create();
        $agent  = User::factory()->for($org)->create(['role' => 'agent']);
        $ticket = Ticket::factory()->for($org)->create(['user_id' => $agent->id]);

        Sanctum::actingAs($agent);

        $this->postJson("/api/tickets/{$ticket->id}/comments", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['body']);
    });

    it('validates max body length of 10000 characters', function () {
        $org    = Organization::factory()->create();
        $agent  = User::factory()->for($org)->create(['role' => 'agent']);
        $ticket = Ticket::factory()->for($org)->create(['user_id' => $agent->id]);

        Sanctum::actingAs($agent);

        $this->postJson("/api/tickets/{$ticket->id}/comments", [
            'body' => str_repeat('a', 10001),
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['body']);
    });

    it('returns 404 when posting to a ticket in another organization', function () {
        $orgA   = Organization::factory()->create();
        $orgB   = Organization::factory()->create();
        $agent  = User::factory()->for($orgA)->create(['role' => 'agent']);
        $ticket = Ticket::factory()->for($orgB)->create();

        Sanctum::actingAs($agent);

        $this->postJson("/api/tickets/{$ticket->id}/comments", [
            'body' => 'Cross-org attempt',
        ])->assertNotFound();
    });

    it('scopes organization_id from the authenticated user, not request input', function () {
        $org    = Organization::factory()->create();
        $orgB   = Organization::factory()->create();
        $agent  = User::factory()->for($org)->create(['role' => 'agent']);
        $ticket = Ticket::factory()->for($org)->create(['user_id' => $agent->id]);

        Sanctum::actingAs($agent);

        $this->postJson("/api/tickets/{$ticket->id}/comments", [
            'body'            => 'Trying to inject org',
            'organization_id' => $orgB->id, // should be ignored
        ])
            ->assertCreated();

        $this->assertDatabaseHas('comments', [
            'ticket_id'        => $ticket->id,
            'organization_id'  => $org->id, // from auth user, not request
        ]);
    });

    it('requires authentication', function () {
        $ticket = Ticket::factory()->create();

        $this->postJson("/api/tickets/{$ticket->id}/comments", [
            'body' => 'Hello',
        ])->assertUnauthorized();
    });
});
