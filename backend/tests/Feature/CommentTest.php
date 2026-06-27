<?php

use App\Models\Comment;
use App\Models\Organization;
use App\Models\Ticket;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

describe('Create Comment', function () {
    beforeEach(function () {
        $this->org = Organization::factory()->create();
        $this->agent = User::factory()->for($this->org)->agent()->create();
        $this->customer = User::factory()->for($this->org)->customer()->create();
        $this->ticket = Ticket::factory()->for($this->org)->create([
            'customer_id' => $this->customer->id,
        ]);
    });

    it('allows an agent to create a public comment', function () {
        Sanctum::actingAs($this->agent);

        postJson("/api/tickets/{$this->ticket->id}/comments", [
            'body' => 'Looking into this issue now.',
            'is_internal' => false,
        ])
            ->assertCreated()
            ->assertJsonPath('data.body', 'Looking into this issue now.')
            ->assertJsonPath('data.is_internal', false)
            ->assertJsonPath('data.user_id', $this->agent->id);

        assertDatabaseHas('comments', [
            'ticket_id' => $this->ticket->id,
            'body' => 'Looking into this issue now.',
            'is_internal' => false,
            'user_id' => $this->agent->id,
        ]);
    });

    it('allows an agent to create an internal comment', function () {
        Sanctum::actingAs($this->agent);

        postJson("/api/tickets/{$this->ticket->id}/comments", [
            'body' => 'Customer seems frustrated, handle with care.',
            'is_internal' => true,
        ])
            ->assertCreated()
            ->assertJsonPath('data.is_internal', true);

        assertDatabaseHas('comments', [
            'ticket_id' => $this->ticket->id,
            'body' => 'Customer seems frustrated, handle with care.',
            'is_internal' => true,
            'user_id' => $this->agent->id,
        ]);
    });

    it('allows an admin to create an internal comment', function () {
        $admin = User::factory()->for($this->org)->admin()->create();
        Sanctum::actingAs($admin);

        postJson("/api/tickets/{$this->ticket->id}/comments", [
            'body' => 'Escalating to engineering.',
            'is_internal' => true,
        ])
            ->assertCreated()
            ->assertJsonPath('data.is_internal', true);
    });

    it('allows a customer to create a public comment on their ticket', function () {
        Sanctum::actingAs($this->customer);

        postJson("/api/tickets/{$this->ticket->id}/comments", [
            'body' => 'Any update on this?',
        ])
            ->assertCreated()
            ->assertJsonPath('data.is_internal', false)
            ->assertJsonPath('data.user_id', $this->customer->id);
    });

    it('forces customer comments to be public (ignores is_internal flag)', function () {
        Sanctum::actingAs($this->customer);

        postJson("/api/tickets/{$this->ticket->id}/comments", [
            'body' => 'Trying to sneak an internal note.',
            'is_internal' => true,
        ])
            ->assertCreated()
            ->assertJsonPath('data.is_internal', false);

        assertDatabaseHas('comments', [
            'ticket_id' => $this->ticket->id,
            'body' => 'Trying to sneak an internal note.',
            'is_internal' => false,
            'user_id' => $this->customer->id,
        ]);
    });

    it('defaults is_internal to false when not provided', function () {
        Sanctum::actingAs($this->agent);

        postJson("/api/tickets/{$this->ticket->id}/comments", [
            'body' => 'Default visibility comment.',
        ])
            ->assertCreated()
            ->assertJsonPath('data.is_internal', false);
    });

    it('validates body is required', function () {
        Sanctum::actingAs($this->agent);

        postJson("/api/tickets/{$this->ticket->id}/comments", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['body']);
    });

    it('validates body cannot be empty string', function () {
        Sanctum::actingAs($this->agent);

        postJson("/api/tickets/{$this->ticket->id}/comments", [
            'body' => '',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['body']);
    });

    it('associates the comment with the authenticated user', function () {
        Sanctum::actingAs($this->agent);

        $response = postJson("/api/tickets/{$this->ticket->id}/comments", [
            'body' => 'My comment.',
        ])
            ->assertCreated();

        expect($response->json('data.user_id'))->toBe($this->agent->id);
    });
});

describe('List Comments', function () {
    beforeEach(function () {
        $this->org = Organization::factory()->create();
        $this->agent = User::factory()->for($this->org)->agent()->create();
        $this->customer = User::factory()->for($this->org)->customer()->create();
        $this->ticket = Ticket::factory()->for($this->org)->create([
            'customer_id' => $this->customer->id,
        ]);
    });

    it('allows an agent to see all comments including internal notes', function () {
        Sanctum::actingAs($this->agent);

        $publicComment = Comment::factory()->for($this->ticket)->public()->create([
            'body' => 'Public reply.',
        ]);
        $internalComment = Comment::factory()->for($this->ticket)->internal()->create([
            'body' => 'Internal discussion.',
        ]);

        getJson("/api/tickets/{$this->ticket->id}/comments")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.body', 'Public reply.')
            ->assertJsonPath('data.1.body', 'Internal discussion.');
    });

    it('allows an admin to see all comments including internal notes', function () {
        $admin = User::factory()->for($this->org)->admin()->create();
        Sanctum::actingAs($admin);

        Comment::factory()->for($this->ticket)->public()->create();
        Comment::factory()->for($this->ticket)->internal()->create();

        getJson("/api/tickets/{$this->ticket->id}/comments")
            ->assertOk()
            ->assertJsonCount(2, 'data');
    });

    it('hides internal comments from customers', function () {
        Sanctum::actingAs($this->customer);

        Comment::factory()->for($this->ticket)->public()->create([
            'body' => 'Visible to customer.',
        ]);
        Comment::factory()->for($this->ticket)->internal()->create([
            'body' => 'Secret agent note.',
        ]);
        Comment::factory()->for($this->ticket)->internal()->create([
            'body' => 'Another secret note.',
        ]);

        getJson("/api/tickets/{$this->ticket->id}/comments")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.body', 'Visible to customer.');
    });

    it('returns only public comments when customer has a mix', function () {
        Sanctum::actingAs($this->customer);

        Comment::factory()->for($this->ticket)->public()->create();
        Comment::factory()->for($this->ticket)->public()->create();
        Comment::factory()->for($this->ticket)->public()->create();
        Comment::factory()->for($this->ticket)->internal()->create();

        getJson("/api/tickets/{$this->ticket->id}/comments")
            ->assertOk()
            ->assertJsonCount(3, 'data');

        $internalFlags = collect(getJson("/api/tickets/{$this->ticket->id}/comments")->json('data'))
            ->pluck('is_internal')
            ->unique();

        expect($internalFlags)->toContain(false);
        expect($internalFlags)->not->toContain(true);
    });

    it('returns comments in chronological order', function () {
        Sanctum::actingAs($this->agent);

        $first = Comment::factory()->for($this->ticket)->public()->create([
            'created_at' => now()->subHours(3),
        ]);
        $second = Comment::factory()->for($this->ticket)->public()->create([
            'created_at' => now()->subHours(2),
        ]);
        $third = Comment::factory()->for($this->ticket)->public()->create([
            'created_at' => now()->subHour(),
        ]);

        getJson("/api/tickets/{$this->ticket->id}/comments")
            ->assertOk()
            ->assertJsonPath('data.0.id', $first->id)
            ->assertJsonPath('data.1.id', $second->id)
            ->assertJsonPath('data.2.id', $third->id);
    });

    it('returns empty list when ticket has no comments', function () {
        Sanctum::actingAs($this->agent);

        getJson("/api/tickets/{$this->ticket->id}/comments")
            ->assertOk()
            ->assertJsonCount(0, 'data');
    });
});

describe('Comment Visibility Enforcement', function () {
    beforeEach(function () {
        $this->org = Organization::factory()->create();
        $this->agent = User::factory()->for($this->org)->agent()->create();
        $this->customer = User::factory()->for($this->org)->customer()->create();
        $this->ticket = Ticket::factory()->for($this->org)->create([
            'customer_id' => $this->customer->id,
        ]);
    });

    it('does not expose is_internal field to customers on public comments', function () {
        Sanctum::actingAs($this->customer);

        Comment::factory()->for($this->ticket)->public()->create();

        $response = getJson("/api/tickets/{$this->ticket->id}/comments")
            ->assertOk();

        foreach ($response->json('data') as $comment) {
            expect($comment['is_internal'])->toBeFalse();
        }
    });

    it('does not include internal comment author info for customers', function () {
        Sanctum::actingAs($this->customer);

        $internalComment = Comment::factory()->for($this->ticket)->internal()->create([
            'body' => 'VIP customer — offer discount.',
        ]);

        $response = getJson("/api/tickets/{$this->ticket->id}/comments")
            ->assertOk();

        $bodies = collect($response->json('data'))->pluck('body');

        expect($bodies)->not->toContain('VIP customer — offer discount.');
    });

    it('prevents customer from commenting on a ticket they do not own', function () {
        $otherCustomer = User::factory()->for($this->org)->customer()->create();
        $otherTicket = Ticket::factory()->for($this->org)->create([
            'customer_id' => $otherCustomer->id,
        ]);

        Sanctum::actingAs($this->customer);

        postJson("/api/tickets/{$otherTicket->id}/comments", [
            'body' => 'Intruding on someone else ticket.',
        ])
            ->assertForbidden();
    });
});
