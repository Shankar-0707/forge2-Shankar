<?php

use App\Models\Organization;
use App\Models\Ticket;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertDatabaseMissing;
use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\putJson;

describe('Authentication', function () {
    beforeEach(function () {
        $this->org = Organization::factory()->create();
    });

    it('requires authentication to list tickets', function () {
        getJson('/api/tickets')
            ->assertUnauthorized();
    });

    it('requires authentication to create a ticket', function () {
        postJson('/api/tickets', [
            'subject' => 'Broken login',
            'description' => 'I cannot log in.',
        ])
            ->assertUnauthorized();
    });

    it('requires authentication to view a ticket', function () {
        $ticket = Ticket::factory()->for($this->org)->create();

        getJson("/api/tickets/{$ticket->id}")
            ->assertUnauthorized();
    });

    it('requires authentication to update a ticket', function () {
        $ticket = Ticket::factory()->for($this->org)->create();

        putJson("/api/tickets/{$ticket->id}", ['status' => 'resolved'])
            ->assertUnauthorized();
    });

    it('requires authentication to delete a ticket', function () {
        $ticket = Ticket::factory()->for($this->org)->create();

        deleteJson("/api/tickets/{$ticket->id}")
            ->assertUnauthorized();
    });
});

describe('Create Ticket', function () {
    beforeEach(function () {
        $this->org = Organization::factory()->create();
        $this->customer = User::factory()->for($this->org)->customer()->create();
        Sanctum::actingAs($this->customer);
    });

    it('can create a ticket with valid data', function () {
        $response = postJson('/api/tickets', [
            'subject' => 'Cannot reset password',
            'description' => 'The reset email never arrives.',
            'priority' => 'high',
        ])
            ->assertCreated()
            ->assertJsonPath('data.subject', 'Cannot reset password')
            ->assertJsonPath('data.status', 'open')
            ->assertJsonPath('data.priority', 'high');

        assertDatabaseHas('tickets', [
            'id' => $response->json('data.id'),
            'subject' => 'Cannot reset password',
            'organization_id' => $this->org->id,
            'customer_id' => $this->customer->id,
            'status' => 'open',
        ]);
    });

    it('automatically sets organization_id from the authenticated user', function () {
        $otherOrg = Organization::factory()->create();

        postJson('/api/tickets', [
            'subject' => 'Attempt hijack',
            'description' => 'Trying another org.',
            'organization_id' => $otherOrg->id,
        ])
            ->assertCreated();

        assertDatabaseHas('tickets', [
            'subject' => 'Attempt hijack',
            'organization_id' => $this->org->id,
        ]);

        assertDatabaseMissing('tickets', [
            'subject' => 'Attempt hijack',
            'organization_id' => $otherOrg->id,
        ]);
    });

    it('sets the customer_id to the authenticated user on creation', function () {
        postJson('/api/tickets', [
            'subject' => 'My ticket',
            'description' => 'I own this.',
        ])
            ->assertCreated()
            ->assertJsonPath('data.customer_id', $this->customer->id);
    });

    it('defaults status to open and priority to medium', function () {
        postJson('/api/tickets', [
            'subject' => 'New issue',
            'description' => 'Something happened.',
        ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'open')
            ->assertJsonPath('data.priority', 'medium');
    });

    it('validates subject is required', function () {
        postJson('/api/tickets', [
            'description' => 'Missing subject.',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['subject']);
    });

    it('validates description is required', function () {
        postJson('/api/tickets', [
            'subject' => 'No description',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['description']);
    });

    it('validates priority is within allowed values', function () {
        postJson('/api/tickets', [
            'subject' => 'Bad priority',
            'description' => 'Invalid value.',
            'priority' => 'critical',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['priority']);
    });

    it('validates status is within allowed values', function () {
        postJson('/api/tickets', [
            'subject' => 'Bad status',
            'description' => 'Invalid value.',
            'status' => 'rejected',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    });

    it('allows an agent to create a ticket and assign it', function () {
        $agent = User::factory()->for($this->org)->agent()->create();
        Sanctum::actingAs($agent);

        $customer = User::factory()->for($this->org)->customer()->create();

        postJson('/api/tickets', [
            'subject' => 'Agent-created ticket',
            'description' => 'On behalf of customer.',
            'customer_id' => $customer->id,
            'assignee_id' => $agent->id,
            'priority' => 'urgent',
        ])
            ->assertCreated()
            ->assertJsonPath('data.customer_id', $customer->id)
            ->assertJsonPath('data.assignee_id', $agent->id);
    });
});

describe('List Tickets', function () {
    beforeEach(function () {
        $this->org = Organization::factory()->create();
        $this->agent = User::factory()->for($this->org)->agent()->create();
        Sanctum::actingAs($this->agent);
    });

    it('returns a paginated list of tickets', function () {
        Ticket::factory()->for($this->org)->count(5)->create();

        getJson('/api/tickets')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'subject', 'description', 'status', 'priority', 'customer_id', 'assignee_id'],
                ],
                'meta' => ['current_page', 'total', 'per_page'],
            ])
            ->assertJsonCount(5, 'data');
    });

    it('only returns tickets belonging to the user organization', function () {
        $ownTickets = Ticket::factory()->for($this->org)->count(3)->create();
        $otherOrg = Organization::factory()->create();
        $otherTickets = Ticket::factory()->for($otherOrg)->count(2)->create();

        getJson('/api/tickets')
            ->assertOk()
            ->assertJsonCount(3, 'data');

        $returnedIds = collect(getJson('/api/tickets')->json('data'))->pluck('id');

        foreach ($ownTickets as $ticket) {
            expect($returnedIds)->toContain($ticket->id);
        }
        foreach ($otherTickets as $ticket) {
            expect($returnedIds)->not->toContain($ticket->id);
        }
    });

    it('can filter tickets by status', function () {
        Ticket::factory()->for($this->org)->open()->create();
        Ticket::factory()->for($this->org)->open()->create();
        Ticket::factory()->for($this->org)->pending()->create();
        Ticket::factory()->for($this->org)->resolved()->create();

        getJson('/api/tickets?status=open')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        getJson('/api/tickets?status=pending')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        getJson('/api/tickets?status=resolved')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    });

    it('can filter tickets by priority', function () {
        Ticket::factory()->for($this->org)->lowPriority()->create();
        Ticket::factory()->for($this->org)->highPriority()->create();
        Ticket::factory()->for($this->org)->urgentPriority()->create();
        Ticket::factory()->for($this->org)->urgentPriority()->create();

        getJson('/api/tickets?priority=urgent')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        getJson('/api/tickets?priority=high')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        getJson('/api/tickets?priority=low')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    });

    it('can search tickets by subject', function () {
        Ticket::factory()->for($this->org)->create(['subject' => 'Login page not working']);
        Ticket::factory()->for($this->org)->create(['subject' => 'Billing error on invoice']);
        Ticket::factory()->for($this->org)->create(['subject' => 'Cannot access login portal']);

        getJson('/api/tickets?search=login')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    });

    it('can search tickets by description', function () {
        Ticket::factory()->for($this->org)->create([
            'subject' => 'General issue',
            'description' => 'The payment gateway is down.',
        ]);
        Ticket::factory()->for($this->org)->create([
            'subject' => 'Other problem',
            'description' => 'Nothing works.',
        ]);

        getJson('/api/tickets?search=payment')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    });

    it('can combine status and priority filters', function () {
        Ticket::factory()->for($this->org)->create([
            'status' => 'open',
            'priority' => 'urgent',
        ]);
        Ticket::factory()->for($this->org)->create([
            'status' => 'open',
            'priority' => 'low',
        ]);
        Ticket::factory()->for($this->org)->create([
            'status' => 'closed',
            'priority' => 'urgent',
        ]);

        getJson('/api/tickets?status=open&priority=urgent')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    });

    it('can combine search with status filter', function () {
        Ticket::factory()->for($this->org)->create([
            'subject' => 'Server outage',
            'status' => 'open',
        ]);
        Ticket::factory()->for($this->org)->create([
            'subject' => 'Server maintenance',
            'status' => 'resolved',
        ]);

        getJson('/api/tickets?search=server&status=open')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.subject', 'Server outage');
    });

    it('returns empty data set when no tickets match filters', function () {
        Ticket::factory()->for($this->org)->count(3)->create();

        getJson('/api/tickets?status=closed')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    });

    it('can filter tickets by assignee', function () {
        $agent1 = User::factory()->for($this->org)->agent()->create();
        $agent2 = User::factory()->for($this->org)->agent()->create();

        Ticket::factory()->for($this->org)->create(['assignee_id' => $agent1->id]);
        Ticket::factory()->for($this->org)->create(['assignee_id' => $agent1->id]);
        Ticket::factory()->for($this->org)->create(['assignee_id' => $agent2->id]);

        getJson("/api/tickets?assignee_id={$agent1->id}")
            ->assertOk()
            ->assertJsonCount(2, 'data');
    });

    it('returns customers only their own tickets', function () {
        $customer = User::factory()->for($this->org)->customer()->create();
        $otherCustomer = User::factory()->for($this->org)->customer()->create();

        Ticket::factory()->for($this->org)->create(['customer_id' => $customer->id]);
        Ticket::factory()->for($this->org)->create(['customer_id' => $customer->id]);
        Ticket::factory()->for($this->org)->create(['customer_id' => $otherCustomer->id]);

        Sanctum::actingAs($customer);

        getJson('/api/tickets')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $customerIds = collect(getJson('/api/tickets')->json('data'))->pluck('customer_id')->unique();
        expect($customerIds)->toContain($customer->id);
        expect($customerIds)->not->toContain($otherCustomer->id);
    });
});

describe('Show Ticket', function () {
    beforeEach(function () {
        $this->org = Organization::factory()->create();
        $this->agent = User::factory()->for($this->org)->agent()->create();
        Sanctum::actingAs($this->agent);
    });

    it('can view a single ticket', function () {
        $ticket = Ticket::factory()->for($this->org)->create([
            'subject' => 'Detailed view test',
            'description' => 'Full description here.',
        ]);

        getJson("/api/tickets/{$ticket->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $ticket->id)
            ->assertJsonPath('data.subject', 'Detailed view test')
            ->assertJsonPath('data.description', 'Full description here.');
    });

    it('returns 404 for a non-existent ticket', function () {
        getJson('/api/tickets/999999')
            ->assertNotFound();
    });

    it('includes related comments in the response', function () {
        $ticket = Ticket::factory()->for($this->org)->create();
        $comment1 = \App\Models\Comment::factory()->for($ticket)->public()->create();
        $comment2 = \App\Models\Comment::factory()->for($ticket)->public()->create();

        getJson("/api/tickets/{$ticket->id}")
            ->assertOk()
            ->assertJsonPath('data.comments.0.id', $comment1->id)
            ->assertJsonPath('data.comments.1.id', $comment2->id);
    });
});

describe('Update Ticket', function () {
    beforeEach(function () {
        $this->org = Organization::factory()->create();
        $this->agent = User::factory()->for($this->org)->agent()->create();
        Sanctum::actingAs($this->agent);
    });

    it('can update ticket status', function () {
        $ticket = Ticket::factory()->for($this->org)->open()->create();

        putJson("/api/tickets/{$ticket->id}", [
            'status' => 'resolved',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'resolved');

        assertDatabaseHas('tickets', [
            'id' => $ticket->id,
            'status' => 'resolved',
        ]);
    });

    it('can update ticket priority', function () {
        $ticket = Ticket::factory()->for($this->org)->lowPriority()->create();

        putJson("/api/tickets/{$ticket->id}", [
            'priority' => 'urgent',
        ])
            ->assertOk()
            ->assertJsonPath('data.priority', 'urgent');

        assertDatabaseHas('tickets', [
            'id' => $ticket->id,
            'priority' => 'urgent',
        ]);
    });

    it('can assign a ticket to an agent', function () {
        $ticket = Ticket::factory()->for($this->org)->create(['assignee_id' => null]);
        $agent = User::factory()->for($this->org)->agent()->create();

        putJson("/api/tickets/{$ticket->id}", [
            'assignee_id' => $agent->id,
        ])
            ->assertOk()
            ->assertJsonPath('data.assignee_id', $agent->id);

        assertDatabaseHas('tickets', [
            'id' => $ticket->id,
            'assignee_id' => $agent->id,
        ]);
    });

    it('can reassign a ticket to a different agent', function () {
        $agent1 = User::factory()->for($this->org)->agent()->create();
        $agent2 = User::factory()->for($this->org)->agent()->create();
        $ticket = Ticket::factory()->for($this->org)->create(['assignee_id' => $agent1->id]);

        putJson("/api/tickets/{$ticket->id}", [
            'assignee_id' => $agent2->id,
        ])
            ->assertOk()
            ->assertJsonPath('data.assignee_id', $agent2->id);
    });

    it('can update subject and description', function () {
        $ticket = Ticket::factory()->for($this->org)->create();

        putJson("/api/tickets/{$ticket->id}", [
            'subject' => 'Updated subject',
            'description' => 'Updated description text.',
        ])
            ->assertOk()
            ->assertJsonPath('data.subject', 'Updated subject')
            ->assertJsonPath('data.description', 'Updated description text.');
    });

    it('can update multiple fields at once', function () {
        $ticket = Ticket::factory()->for($this->org)->open()->lowPriority()->create();

        putJson("/api/tickets/{$ticket->id}", [
            'status' => 'pending',
            'priority' => 'high',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.priority', 'high');
    });

    it('validates status on update', function () {
        $ticket = Ticket::factory()->for($this->org)->create();

        putJson("/api/tickets/{$ticket->id}", [
            'status' => 'invalid',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    });

    it('does not allow organization_id to be changed via update', function () {
        $ticket = Ticket::factory()->for($this->org)->create();
        $otherOrg = Organization::factory()->create();

        putJson("/api/tickets/{$ticket->id}", [
            'organization_id' => $otherOrg->id,
            'subject' => 'Attempt move',
        ])
            ->assertOk();

        assertDatabaseHas('tickets', [
            'id' => $ticket->id,
            'organization_id' => $this->org->id,
        ]);

        assertDatabaseMissing('tickets', [
            'id' => $ticket->id,
            'organization_id' => $otherOrg->id,
        ]);
    });

    it('prevents customers from updating ticket properties', function () {
        $customer = User::factory()->for($this->org)->customer()->create();
        $ticket = Ticket::factory()->for($this->org)->create(['customer_id' => $customer->id]);

        Sanctum::actingAs($customer);

        putJson("/api/tickets/{$ticket->id}", [
            'status' => 'resolved',
        ])
            ->assertForbidden();
    });
});

describe('Delete Ticket', function () {
    beforeEach(function () {
        $this->org = Organization::factory()->create();
        $this->admin = User::factory()->for($this->org)->admin()->create();
        Sanctum::actingAs($this->admin);
    });

    it('can delete a ticket as admin', function () {
        $ticket = Ticket::factory()->for($this->org)->create();

        deleteJson("/api/tickets/{$ticket->id}")
            ->assertNoContent();

        assertDatabaseMissing('tickets', [
            'id' => $ticket->id,
        ]);
    });

    it('returns 404 when deleting a non-existent ticket', function () {
        deleteJson('/api/tickets/999999')
            ->assertNotFound();
    });

    it('prevents agents from deleting tickets', function () {
        $agent = User::factory()->for($this->org)->agent()->create();
        Sanctum::actingAs($agent);

        $ticket = Ticket::factory()->for($this->org)->create();

        deleteJson("/api/tickets/{$ticket->id}")
            ->assertForbidden();
    });

    it('prevents customers from deleting tickets', function () {
        $customer = User::factory()->for($this->org)->customer()->create();
        $ticket = Ticket::factory()->for($this->org)->create(['customer_id' => $customer->id]);

        Sanctum::actingAs($customer);

        deleteJson("/api/tickets/{$ticket->id}")
            ->assertForbidden();
    });
});
