<?php

use App\Events\CommentAdded;
use App\Models\ActivityLog;
use App\Models\Organization;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Test data helpers
|--------------------------------------------------------------------------
*/

function createOrgUser(): array
{
    $org  = Organization::factory()->create();
    $user = User::factory()->create(['organization_id' => $org->id]);

    return [$org, $user];
}

/*
|--------------------------------------------------------------------------
| Event → ActivityLog persistence
|--------------------------------------------------------------------------
*/

it('logs a "created" activity when a ticket is created', function () {
    [$org, $user] = createOrgUser();

    $this->actingAs($user);

    $ticket = Ticket::factory()->create([
        'organization_id' => $org->id,
        'created_by'      => $user->id,
        'title'           => 'Cannot log in',
    ]);

    $log = ActivityLog::query()
        ->where('ticket_id', $ticket->id)
        ->where('event', ActivityLog::EVENT_CREATED)
        ->first();

    expect($log)
        ->not->toBeNull()
        ->organization_id->toBe($org->id)
        ->actor_id->toBe($user->id)
        ->and($log->metadata['title'])->toBe('Cannot log in');
});

it('logs a "created" activity with null actor when no user is authenticated', function () {
    [$org] = createOrgUser();

    // No actingAs — simulates system / queued ticket creation
    $ticket = Ticket::factory()->create([
        'organization_id' => $org->id,
    ]);

    $log = ActivityLog::query()
        ->where('ticket_id', $ticket->id)
        ->where('event', ActivityLog::EVENT_CREATED)
        ->first();

    expect($log)
        ->not->toBeNull()
        ->actor_id->toBeNull();
});

it('logs a "status_changed" activity when ticket status changes', function () {
    [$org, $user] = createOrgUser();

    $this->actingAs($user);

    $ticket = Ticket::factory()->create([
        'organization_id' => $org->id,
        'created_by'      => $user->id,
        'status'          => Ticket::STATUS_OPEN,
    ]);

    // Isolate — remove the auto-created log
    ActivityLog::query()->where('ticket_id', $ticket->id)->delete();

    $ticket->update(['status' => Ticket::STATUS_IN_PROGRESS]);

    $log = ActivityLog::firstWhere('ticket_id', $ticket->id);

    expect($log)
        ->not->toBeNull()
        ->event->toBe(ActivityLog::EVENT_STATUS_CHANGED)
        ->actor_id->toBe($user->id)
        ->and($log->metadata['from'])->toBe(Ticket::STATUS_OPEN)
        ->and($log->metadata['to'])->toBe(Ticket::STATUS_IN_PROGRESS);
});

it('does not log a status change when status is unchanged', function () {
    [$org, $user] = createOrgUser();

    $this->actingAs($user);

    $ticket = Ticket::factory()->create([
        'organization_id' => $org->id,
        'created_by'      => $user->id,
        'status'          => Ticket::STATUS_OPEN,
    ]);

    ActivityLog::query()->where('ticket_id', $ticket->id)->delete();

    // Update a different column — status stays the same
    $ticket->update(['title' => 'Updated title']);

    expect(ActivityLog::where('ticket_id', $ticket->id)->count())->toBe(0);
});

it('logs an "assigned" activity when ticket is assigned', function () {
    [$org, $user] = createOrgUser();

    $this->actingAs($user);

    $assignee = User::factory()->create(['organization_id' => $org->id]);

    $ticket = Ticket::factory()->create([
        'organization_id' => $org->id,
        'created_by'      => $user->id,
        'assigned_to'     => null,
    ]);

    ActivityLog::query()->where('ticket_id', $ticket->id)->delete();

    $ticket->update(['assigned_to' => $assignee->id]);

    $log = ActivityLog::firstWhere('ticket_id', $ticket->id);

    expect($log)
        ->not->toBeNull()
        ->event->toBe(ActivityLog::EVENT_ASSIGNED)
        ->and($log->metadata['from'])->toBeNull()
        ->and($log->metadata['to'])->toBe($assignee->id);
});

it('logs an "assigned" activity when ticket is reassigned', function () {
    [$org, $user] = createOrgUser();

    $this->actingAs($user);

    $firstAssignee  = User::factory()->create(['organization_id' => $org->id]);
    $secondAssignee = User::factory()->create(['organization_id' => $org->id]);

    $ticket = Ticket::factory()->create([
        'organization_id' => $org->id,
        'created_by'      => $user->id,
        'assigned_to'     => $firstAssignee->id,
    ]);

    ActivityLog::query()->where('ticket_id', $ticket->id)->delete();

    $ticket->update(['assigned_to' => $secondAssignee->id]);

    $log = ActivityLog::firstWhere('ticket_id', $ticket->id);

    expect($log)
        ->not->toBeNull()
        ->event->toBe(ActivityLog::EVENT_ASSIGNED)
        ->and($log->metadata['from'])->toBe($firstAssignee->id)
        ->and($log->metadata['to'])->toBe($secondAssignee->id);
});

it('logs a "commented" activity when CommentAdded event is dispatched', function () {
    [$org, $user] = createOrgUser();

    $this->actingAs($user);

    $ticket = Ticket::factory()->createQuietly([
        'organization_id' => $org->id,
        'created_by'      => $user->id,
    ]);

    CommentAdded::dispatch($ticket, $user, commentId: 99);

    $log = ActivityLog::firstWhere('ticket_id', $ticket->id);

    expect($log)
        ->not->toBeNull()
        ->event->toBe(ActivityLog::EVENT_COMMENTED)
        ->actor_id->toBe($user->id)
        ->organization_id->toBe($org->id)
        ->and($log->metadata['comment_id'])->toBe(99);
});

/*
|--------------------------------------------------------------------------
| GET /api/v1/tickets/{ticket}/activity
|--------------------------------------------------------------------------
*/

it('returns paginated activity for a ticket in the users organisation', function () {
    [$org, $user] = createOrgUser();

    $ticket = Ticket::factory()->createQuietly([
        'organization_id' => $org->id,
        'created_by'      => $user->id,
    ]);

    ActivityLog::factory()->count(3)->create([
        'organization_id' => $org->id,
        'ticket_id'       => $ticket->id,
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->getJson("/api/v1/tickets/{$ticket->id}/activity");

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'ticket_id', 'actor_id', 'event', 'metadata', 'created_at'],
            ],
            'meta' => ['current_page', 'total', 'per_page', 'last_page'],
        ])
        ->assertJsonPath('meta.total', 3);
});

it('returns activity sorted newest-first', function () {
    [$org, $user] = createOrgUser();

    $ticket = Ticket::factory()->createQuietly([
        'organization_id' => $org->id,
        'created_by'      => $user->id,
    ]);

    $old = ActivityLog::factory()->create([
        'organization_id' => $org->id,
        'ticket_id'       => $ticket->id,
        'created_at'      => now()->subDays(2),
    ]);

    $new = ActivityLog::factory()->create([
        'organization_id' => $org->id,
        'ticket_id'       => $ticket->id,
        'created_at'      => now(),
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->getJson("/api/v1/tickets/{$ticket->id}/activity");

    $response->assertOk();

    expect($response->json('data.0.id'))->toBe($new->id)
        ->and($response->json('data.1.id'))->toBe($old->id);
});

it('returns empty data when ticket has no activity', function () {
    [$org, $user] = createOrgUser();

    $ticket = Ticket::factory()->createQuietly([
        'organization_id' => $org->id,
        'created_by'      => $user->id,
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->getJson("/api/v1/tickets/{$ticket->id}/activity");

    $response->assertOk()
        ->assertJsonPath('data', [])
        ->assertJsonPath('meta.total', 0);
});

it('returns 404 when ticket belongs to another organisation', function () {
    [$orgA, $userA] = createOrgUser();
    [$orgB, $userB] = createOrgUser();

    $ticket = Ticket::factory()->createQuietly([
        'organization_id' => $orgB->id,
        'created_by'      => $userB->id,
    ]);

    $response = $this->actingAs($userA, 'sanctum')
        ->getJson("/api/v1/tickets/{$ticket->id}/activity");

    $response->assertNotFound();
});

it('returns 401 when unauthenticated', function () {
    [$org] = createOrgUser();

    $ticket = Ticket::factory()->createQuietly([
        'organization_id' => $org->id,
    ]);

    $this->getJson("/api/v1/tickets/{$ticket->id}/activity")
        ->assertUnauthorized();
});

it('respects per_page query parameter', function () {
    [$org, $user] = createOrgUser();

    $ticket = Ticket::factory()->createQuietly([
        'organization_id' => $org->id,
        'created_by'      => $user->id,
    ]);

    ActivityLog::factory()->count(25)->create([
        'organization_id' => $org->id,
        'ticket_id'       => $ticket->id,
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->getJson("/api/v1/tickets/{$ticket->id}/activity?per_page=5");

    $response->assertOk()
        ->assertJsonPath('meta.per_page', 5)
        ->assertJsonPath('meta.total', 25);
});

it('includes actor relationship in response', function () {
    [$org, $user] = createOrgUser();

    $ticket = Ticket::factory()->createQuietly([
        'organization_id' => $org->id,
        'created_by'      => $user->id,
    ]);

    ActivityLog::factory()->create([
        'organization_id' => $org->id,
        'ticket_id'       => $ticket->id,
        'actor_id'        => $user->id,
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->getJson("/api/v1/tickets/{$ticket->id}/activity");

    $response->assertOk();

    expect($response->json('data.0.actor'))
        ->not->toBeNull()
        ->id->toBe($user->id)
        ->and($response->json('data.0.actor'))->toHaveKeys(['name', 'email']);
});
