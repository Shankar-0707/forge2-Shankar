<?php

declare(strict_types=1);

use App\Models\Organization;
use App\Models\Ticket;
use App\Models\User;

use function Pest\Laravel\{actingAs, assertDatabaseHas, assertDatabaseMissing, assertSoftDeleted};

beforeEach(function () {
    $this->organization = Organization::factory()->create();
    $this->user = User::factory()->for($this->organization)->create();
});

function authUser(?Organization $org = null): User
{
    $org ??= Organization::factory()->create();

    return User::factory()->for($org)->create();
}

it('requires authentication', function () {
    $this->getJson('/api/tickets')->assertUnauthorized();
    $this->postJson('/api/tickets', [])->assertUnauthorized();
});

it('lists tickets belonging to the user organization', function () {
    Ticket::factory()->for($this->organization)->count(3)->create();

    actingAs($this->user)
        ->getJson('/api/tickets')
        ->assertOk()
        ->assertJsonCount(3, 'data');
});

it('does not leak tickets from other organizations', function () {
    $otherOrg = Organization::factory()->create();
    Ticket::factory()->for($otherOrg)->count(5)->create();
    Ticket::factory()->for($this->organization)->create();

    actingAs($this->user)
        ->getJson('/api/tickets')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonMissing(['organization_id' => $otherOrg->id]);
});

it('filters tickets by status', function () {
    Ticket::factory()->for($this->organization)->create(['status' => 'open']);
    Ticket::factory()->for($this->organization)->create(['status' => 'resolved']);
    Ticket::factory()->for($this->organization)->create(['status' => 'resolved']);

    $response = actingAs($this->user)
        ->getJson('/api/tickets?status=resolved')
        ->assertOk();

    expect($response->json('data'))->toHaveCount(2);
});

it('filters tickets by priority', function () {
    Ticket::factory()->for($this->organization)->create(['priority' => 'urgent']);
    Ticket::factory()->for($this->organization)->create(['priority' => 'low']);

    $response = actingAs($this->user)
        ->getJson('/api/tickets?priority=urgent')
        ->assertOk();

    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.priority'))->toBe('urgent');
});

it('filters tickets by assignee_id', function () {
    $assignee = User::factory()->for($this->organization)->create();
    Ticket::factory()->for($this->organization)->create(['assignee_id' => $assignee->id]);
    Ticket::factory()->for($this->organization)->create(['assignee_id' => null]);

    $response = actingAs($this->user)
        ->getJson('/api/tickets?assignee_id='.$assignee->id)
        ->assertOk();

    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.assignee_id'))->toBe($assignee->id);
});

it('supports text search across subject and description', function () {
    Ticket::factory()->for($this->organization)->create([
        'subject' => 'Stripe webhook failing',
        'description' => 'unrelated text',
    ]);
    Ticket::factory()->for($this->organization)->create([
        'subject' => 'Generic question',
        'description' => 'something about Stripe integration',
    ]);
    Ticket::factory()->for($this->organization)->create([
        'subject' => 'Unrelated',
        'description' => 'nothing here',
    ]);

    $response = actingAs($this->user)
        ->getJson('/api/tickets?search=stripe')
        ->assertOk();

    expect($response->json('data'))->toHaveCount(2);
});

it('does not return cross-org results even when filtering', function () {
    $otherOrg = Organization::factory()->create();
    Ticket::factory()->for($otherOrg)->create(['subject' => 'stripe issue']);

    actingAs($this->user)
        ->getJson('/api/tickets?search=stripe')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('can combine multiple filters and search', function () {
    $assignee = User::factory()->for($this->organization)->create();

    Ticket::factory()->for($this->organization)->create([
        'subject' => 'Stripe charge failed',
        'status' => 'open',
        'priority' => 'high',
        'assignee_id' => $assignee->id,
    ]);
    Ticket::factory()->for($this->organization)->create([
        'subject' => 'Stripe refund request',
        'status' => 'resolved',
        'priority' => 'high',
        'assignee_id' => $assignee->id,
    ]);

    $response = actingAs($this->user)
        ->getJson('/api/tickets?search=stripe&status=open&priority=high&assignee_id='.$assignee->id)
        ->assertOk();

    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.subject'))->toBe('Stripe charge failed');
});

it('creates a ticket scoped to the authenticated users organization', function () {
    $payload = [
        'subject' => 'Cannot reset password',
        'description' => 'User reports broken reset flow.',
        'status' => 'open',
        'priority' => 'medium',
    ];

    actingAs($this->user)
        ->postJson('/api/tickets', $payload)
        ->assertCreated()
        ->assertJsonPath('data.subject', $payload['subject'])
        ->assertJsonPath('data.organization_id', $this->organization->id);

    assertDatabaseHas('tickets', [
        'subject' => $payload['subject'],
        'organization_id' => $this->organization->id,
        'created_by' => $this->user->id,
    ]);
});

it('ignores any organization_id or created_by submitted by the client', function () {
    $otherOrg = Organization::factory()->create();

    actingAs($this->user)
        ->postJson('/api/tickets', [
            'subject' => 'Malicious ticket',
            'description' => 'Trying to escape tenant.',
            'organization_id' => $otherOrg->id,
            'created_by' => User::factory()->for($otherOrg)->create()->id,
        ])
        ->assertCreated()
        ->assertJsonPath('data.organization_id', $this->organization->id);
});

it('rejects invalid status values', function () {
    actingAs($this->user)
        ->postJson('/api/tickets', [
            'subject' => 'Bad status',
            'description' => 'foo',
            'status' => 'bogus',
        ])
        ->assertJsonValidationErrorFor('status');
});

it('rejects invalid priority values', function () {
    actingAs($this->user)
        ->postJson('/api/tickets', [
            'subject' => 'Bad priority',
            'description' => 'foo',
            'priority' => 'over-9000',
        ])
        ->assertJsonValidationErrorFor('priority');
});

it('rejects an assignee from another organization', function () {
    $otherOrgAssignee = User::factory()->for(Organization::factory()->create())->create();

    actingAs($this->user)
        ->postJson('/api/tickets', [
            'subject' => 'Cross-tenant assignee',
            'description' => 'foo',
            'assignee_id' => $otherOrgAssignee->id,
        ])
        ->assertJsonValidationErrorFor('assignee_id');
});

it('requires subject and description', function () {
    actingAs($this->user)
        ->postJson('/api/tickets', [])
        ->assertJsonValidationErrorFor('subject')
        ->assertJsonValidationErrorFor('description');
});

it('shows a ticket in the same organization', function () {
    $ticket = Ticket::factory()->for($this->organization)->create();

    actingAs($this->user)
        ->getJson('/api/tickets/'.$ticket->id)
        ->assertOk()
        ->assertJsonPath('data.id', $ticket->id);
});

it('returns 404 for a ticket in another organization', function () {
    $otherOrg = Organization::factory()->create();
    $ticket = Ticket::factory()->for($otherOrg)->create();

    actingAs($this->user)
        ->getJson('/api/tickets/'.$ticket->id)
        ->assertNotFound();
});

it('updates a ticket within the same organization', function () {
    $ticket = Ticket::factory()->for($this->organization)->create(['status' => 'open']);

    actingAs($this->user)
        ->putJson('/api/tickets/'.$ticket->id, ['status' => 'resolved'])
        ->assertOk()
        ->assertJsonPath('data.status', 'resolved');

    assertDatabaseHas('tickets', [
        'id' => $ticket->id,
        'status' => 'resolved',
    ]);
});

it('cannot update a ticket from another organization', function () {
    $otherOrg = Organization::factory()->create();
    $ticket = Ticket::factory()->for($otherOrg)->create();

    actingAs($this->user)
        ->putJson('/api/tickets/'.$ticket->id, ['status' => 'resolved'])
        ->assertNotFound();
});

it('soft deletes a ticket within the same organization', function () {
    $ticket = Ticket::factory()->for($this->organization)->create();

    actingAs($this->user)
        ->deleteJson('/api/tickets/'.$ticket->id)
        ->assertNoContent();

    assertSoftDeleted('tickets', ['id' => $ticket->id]);
});

it('cannot delete a ticket from another organization', function () {
    $otherOrg = Organization::factory()->create();
    $ticket = Ticket::factory()->for($otherOrg)->create();

    actingAs($this->user)
        ->deleteJson('/api/tickets/'.$ticket->id)
        ->assertNotFound();

    assertDatabaseHas('tickets', ['id' => $ticket->id]);
});

it('paginates large result sets', function () {
    Ticket::factory()->for($this->organization)->count(30)->create();

    $response = actingAs($this->user)
        ->getJson('/api/tickets?per_page=10')
        ->assertOk();

    expect($response->json('data'))->toHaveCount(10);
    expect($response->json('meta.last_page'))->toBe(3);
});
