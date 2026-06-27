<?php

use App\Models\Comment;
use App\Models\Organization;
use App\Models\Ticket;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\putJson;

describe('Cross-Tenant Ticket Access Prevention', function () {
    beforeEach(function () {
        $this->orgA = Organization::factory()->create();
        $this->orgB = Organization::factory()->create();

        $this->userA = User::factory()->for($this->orgA)->admin()->create();

        $this->ticketA = Ticket::factory()->for($this->orgA)->create();
        $this->ticketB = Ticket::factory()->for($this->orgB)->create();

        Sanctum::actingAs($this->userA);
    });

    it('prevents user from viewing a ticket belonging to another organization', function () {
        getJson("/api/tickets/{$this->ticketB->id}")
            ->assertNotFound();
    });

    it('prevents user from updating a ticket belonging to another organization', function () {
        putJson("/api/tickets/{$this->ticketB->id}", [
            'status' => 'resolved',
        ])
            ->assertNotFound();

        assertDatabaseHas('tickets', [
            'id' => $this->ticketB->id,
            'status' => $this->ticketB->status,
        ]);
    });

    it('prevents user from deleting a ticket belonging to another organization', function () {
        deleteJson("/api/tickets/{$this->ticketB->id}")
            ->assertNotFound();

        assertDatabaseHas('tickets', [
            'id' => $this->ticketB->id,
        ]);
    });

    it('does not list tickets from another organization in the index', function () {
        Ticket::factory()->for($this->orgA)->count(3)->create();
        Ticket::factory()->for($this->orgB)->count(5)->create();

        $response = getJson('/api/tickets')
            ->assertOk();

        $orgIds = collect($response->json('data'))
            ->pluck('organization_id')
            ->unique();

        expect($orgIds)->toContain($this->orgA->id);
        expect($orgIds)->not->toContain($this->orgB->id);
        expect($response->json('meta.total'))->toBe(4);
    });

    it('excludes cross-org tickets even when searching globally', function () {
        Ticket::factory()->for($this->orgA)->create(['subject' => 'Shared keyword']);
        Ticket::factory()->for($this->orgB)->create(['subject' => 'Shared keyword']);

        $response = getJson('/api/tickets?search=Shared')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        expect($response->json('data.0.organization_id'))->toBe($this->orgA->id);
    });

    it('excludes cross-org tickets when filtering by status', function () {
        Ticket::factory()->for($this->orgA)->open()->create();
        Ticket::factory()->for($this->orgA)->open()->create();
        Ticket::factory()->for($this->orgB)->open()->create();
        Ticket::factory()->for($this->orgB)->open()->create();
        Ticket::factory()->for($this->orgB)->open()->create();

        getJson('/api/tickets?status=open')
            ->assertOk()
            ->assertJsonCount(3, 'data');
    });
});

describe('Cross-Tenant Ticket Creation Prevention', function () {
    beforeEach(function () {
        $this->orgA = Organization::factory()->create();
        $this->orgB = Organization::factory()->create();

        $this->userA = User::factory()->for($this->orgA)->admin()->create();

        Sanctum::actingAs($this->userA);
    });

    it('ignores organization_id in the request and uses the authenticated user org', function () {
        $customer = User::factory()->for($this->orgA)->customer()->create();

        postJson('/api/tickets', [
            'subject' => 'Cross-org attempt',
            'description' => 'Should stay in org A.',
            'organization_id' => $this->orgB->id,
            'customer_id' => $customer->id,
        ])
            ->assertCreated()
            ->assertJsonPath('data.organization_id', $this->orgA->id);

        assertDatabaseHas('tickets', [
            'subject' => 'Cross-org attempt',
            'organization_id' => $this->orgA->id,
        ]);
    });

    it('does not allow assigning a ticket to a customer from another organization', function () {
        $customerB = User::factory()->for($this->orgB)->customer()->create();

        postJson('/api/tickets', [
            'subject' => 'Invalid customer',
            'description' => 'Wrong org customer.',
            'customer_id' => $customerB->id,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['customer_id']);
    });

    it('does not allow assigning a ticket to an agent from another organization', function () {
        $agentB = User::factory()->for($this->orgB)->agent()->create();
        $customerA = User::factory()->for($this->orgA)->customer()->create();

        postJson('/api/tickets', [
            'subject' => 'Invalid assignee',
            'description' => 'Wrong org agent.',
            'customer_id' => $customerA->id,
            'assignee_id' => $agentB->id,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['assignee_id']);
    });
});

describe('Cross-Tenant Comment Prevention', function () {
    beforeEach(function () {
        $this->orgA = Organization::factory()->create();
        $this->orgB = Organization::factory()->create();

        $this->agentA = User::factory()->for($this->orgA)->agent()->create();

        $this->ticketA = Ticket::factory()->for($this->orgA)->create();
        $this->ticketB = Ticket::factory()->for($this->orgB)->create();

        Sanctum::actingAs($this->agentA);
    });

    it('prevents user from commenting on a ticket from another organization', function () {
        postJson("/api/tickets/{$this->ticketB->id}/comments", [
            'body' => 'Sneaking into another org.',
        ])
            ->assertNotFound();

        assertDatabaseCount('comments', 0);
    });

    it('prevents user from listing comments on a ticket from another organization', function () {
        Comment::factory()->for($this->ticketB)->public()->create();

        getJson("/api/tickets/{$this->ticketB->id}/comments")
            ->assertNotFound();
    });

    it('prevents user from viewing comments from a cross-org ticket', function () {
        $comment = Comment::factory()->for($this->ticketB)->public()->create([
            'body' => 'Cross-org secret comment.',
        ]);

        getJson("/api/tickets/{$this->ticketB->id}/comments/{$comment->id}")
            ->assertNotFound();
    });
});

describe('Customer Tenant Isolation', function () {
    beforeEach(function () {
        $this->orgA = Organization::factory()->create();
        $this->orgB = Organization::factory()->create();

        $this->customerA = User::factory()->for($this->orgA)->customer()->create();
        $this->customerB = User::factory()->for($this->orgB)->customer()->create();

        $this->ticketA = Ticket::factory()->for($this->orgA)->create([
            'customer_id' => $this->customerA->id,
        ]);
        $this->ticketB = Ticket::factory()->for($this->orgB)->create([
            'customer_id' => $this->customerB->id,
        ]);
    });

    it('prevents customer from viewing tickets in another organization', function () {
        Sanctum::actingAs($this->customerA);

        getJson("/api/tickets/{$this->ticketB->id}")
            ->assertNotFound();
    });

    it('prevents customer from listing tickets in another organization', function () {
        Sanctum::actingAs($this->customerA);

        Ticket::factory()->for($this->orgA)->count(2)->create([
            'customer_id' => $this->customerA->id,
        ]);
        Ticket::factory()->for($this->orgB)->count(5)->create();

        getJson('/api/tickets')
            ->assertOk()
            ->assertJsonCount(3, 'data');
    });

    it('prevents customer from commenting on cross-org tickets', function () {
        Sanctum::actingAs($this->customerA);

        postJson("/api/tickets/{$this->ticketB->id}/comments", [
            'body' => 'Should not work.',
        ])
            ->assertNotFound();

        assertDatabaseCount('comments', 0);
    });
});

describe('Data Leakage Prevention', function () {
    beforeEach(function () {
        $this->orgA = Organization::factory()->create();
        $this->orgB = Organization::factory()->create();

        $this->adminA = User::factory()->for($this->orgA)->admin()->create();

        Sanctum::actingAs($this->adminA);
    });

    it('does not leak ticket counts across organizations', function () {
        Ticket::factory()->for($this->orgA)->count(7)->create();
        Ticket::factory()->for($this->orgB)->count(15)->create();

        $response = getJson('/api/tickets')
            ->assertOk();

        expect($response->json('meta.total'))->toBe(7);
    });

    it('does not leak ticket data via show endpoint for cross-org tickets', function () {
        $ticketB = Ticket::factory()->for($this->orgB)->create([
            'subject' => 'Confidential Org B Info',
        ]);

        getJson("/api/tickets/{$ticketB->id}")
            ->assertNotFound()
            ->assertJsonMissingPath('data.subject');
    });

    it('does not leak internal comments across organizations', function () {
        $ticketA = Ticket::factory()->for($this->orgA)->create();
        $ticketB = Ticket::factory()->for($this->orgB)->create();

        $agentB = User::factory()->for($this->orgB)->agent()->create();
        Comment::factory()->for($ticketB)->internal()->create([
            'body' => 'Org B internal strategy discussion.',
            'user_id' => $agentB->id,
        ]);

        Comment::factory()->for($ticketA)->internal()->create([
            'body' => 'Org A internal note.',
        ]);

        $response = getJson("/api/tickets/{$ticketA->id}/comments")
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $bodies = collect($response->json('data'))->pluck('body');
        expect($bodies)->toContain('Org A internal note.');
        expect($bodies)->not->toContain('Org B internal strategy discussion.');
    });

    it('prevents organization_id tampering on update to move tickets', function () {
        $ticketA = Ticket::factory()->for($this->orgA)->create();

        putJson("/api/tickets/{$ticketA->id}", [
            'subject' => 'Updated title',
            'organization_id' => $this->orgB->id,
        ])
            ->assertOk();

        assertDatabaseHas('tickets', [
            'id' => $ticketA->id,
            'organization_id' => $this->orgA->id,
            'subject' => 'Updated title',
        ]);
    });

    it('prevents assigning a cross-org agent during ticket update', function () {
        $ticketA = Ticket::factory()->for($this->orgA)->create();
        $agentB = User::factory()->for($this->orgB)->agent()->create();

        putJson("/api/tickets/{$ticketA->id}", [
            'assignee_id' => $agentB->id,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['assignee_id']);
    });
});
