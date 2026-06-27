<?php

use App\Models\ActivityLog;
use App\Models\Organization;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Test Helpers
|--------------------------------------------------------------------------
*/

function createOrgUser(?Organization $organization = null): array
{
    $organization ??= Organization::factory()->create();

    $user = User::factory()->create([
        'organization_id' => $organization->id,
    ]);

    Sanctum::actingAs($user);

    return [$user, $organization];
}

function createTicket(Organization $organization, User $user, array $overrides = []): Ticket
{
    return Ticket::factory()->create(array_merge([
        'organization_id' => $organization->id,
        'created_by'      => $user->id,
        'status'          => 'open',
        'assigned_to'     => null,
    ], $overrides));
}

/*
|--------------------------------------------------------------------------
| Activity Log — Status Changes
|--------------------------------------------------------------------------
*/

describe('Activity Log — Status Changes', function () {
    it('creates an activity log entry when a ticket status changes', function () {
        [$user, $org] = createOrgUser();
        $ticket = createTicket($org, $user, ['status' => 'open']);

        $this->patchJson("/api/v1/tickets/{$ticket->id}", [
            'status' => 'in_progress',
        ])->assertOk();

        $this->assertDatabaseHas('activity_logs', [
            'organization_id' => $org->id,
            'ticket_id'       => $ticket->id,
            'user_id'         => $user->id,
            'event'           => 'status_changed',
        ]);
    });

    it('stores old and new status values in the log properties', function () {
        [$user, $org] = createOrgUser();
        $ticket = createTicket($org, $user, ['status' => 'open']);

        $this->patchJson("/api/v1/tickets/{$ticket->id}", [
            'status' => 'resolved',
        ])->assertOk();

        $log = ActivityLog::where('ticket_id', $ticket->id)
            ->where('event', 'status_changed')
            ->first();

        expect($log)->not->toBeNull()
            ->and($log->properties['old'])->toBe('open')
            and($log->properties['new'])->toBe('resolved');
    });

    it('creates separate log entries for consecutive status changes', function () {
        [$user, $org] = createOrgUser();
        $ticket = createTicket($org, $user, ['status' => 'open']);

        $this->patchJson("/api/v1/tickets/{$ticket->id}", [
            'status' => 'in_progress',
        ])->assertOk();

        $this->patchJson("/api/v1/tickets/{$ticket->id}", [
            'status' => 'resolved',
        ])->assertOk();

        $logs = ActivityLog::where('ticket_id', $ticket->id)
            ->where('event', 'status_changed')
            ->get();

        expect($logs)->toHaveCount(2);

        expect($logs->pluck('properties.new')->toArray())
            ->toBe(['in_progress', 'resolved']);
    });

    it('does not create a status change log when the status remains the same', function () {
        [$user, $org] = createOrgUser();
        $ticket = createTicket($org, $user, ['status' => 'open']);

        $this->patchJson("/api/v1/tickets/{$ticket->id}", [
            'status' => 'open',
        ])->assertOk();

        $this->assertDatabaseMissing('activity_logs', [
            'ticket_id' => $ticket->id,
            'event'     => 'status_changed',
        ]);
    });

    it('records the correct actor for status changes', function () {
        [$user, $org] = createOrgUser();

        $creator = User::factory()->create([
            'organization_id' => $org->id,
        ]);

        $ticket = createTicket($org, $creator, ['status' => 'open']);

        $this->patchJson("/api/v1/tickets/{$ticket->id}", [
            'status' => 'pending',
        ])->assertOk();

        $log = ActivityLog::where('ticket_id', $ticket->id)->first();

        // The actor should be the authenticated user, not the ticket creator
        expect($log->user_id)->toBe($user->id);
    });
});

/*
|--------------------------------------------------------------------------
| Activity Log — Assignments
|--------------------------------------------------------------------------
*/

describe('Activity Log — Assignments', function () {
    it('creates an activity log entry when a ticket is assigned', function () {
        [$user, $org] = createOrgUser();
        $ticket = createTicket($org, $user, ['assigned_to' => null]);

        $assignee = User::factory()->create([
            'organization_id' => $org->id,
        ]);

        $this->patchJson("/api/v1/tickets/{$ticket->id}", [
            'assigned_to' => $assignee->id,
        ])->assertOk();

        $this->assertDatabaseHas('activity_logs', [
            'organization_id' => $org->id,
            'ticket_id'       => $ticket->id,
            'user_id'         => $user->id,
            'event'           => 'ticket_assigned',
        ]);
    });

    it('stores the assignee ID in the log properties when assigned', function () {
        [$user, $org] = createOrgUser();
        $ticket = createTicket($org, $user, ['assigned_to' => null]);

        $assignee = User::factory()->create([
            'organization_id' => $org->id,
        ]);

        $this->patchJson("/api/v1/tickets/{$ticket->id}", [
            'assigned_to' => $assignee->id,
        ])->assertOk();

        $log = ActivityLog::where('ticket_id', $ticket->id)
            ->where('event', 'ticket_assigned')
            ->first();

        expect($log)->not->toBeNull()
            ->and($log->properties['old'])->toBeNull()
            ->and($log->properties['new'])->toBe($assignee->id);
    });

    it('creates an activity log entry when a ticket is reassigned', function () {
        [$user, $org] = createOrgUser();

        $firstAssignee = User::factory()->create([
            'organization_id' => $org->id,
        ]);

        $ticket = createTicket($org, $user, [
            'assigned_to' => $firstAssignee->id,
        ]);

        $newAssignee = User::factory()->create([
            'organization_id' => $org->id,
        ]);

        $this->patchJson("/api/v1/tickets/{$ticket->id}", [
            'assigned_to' => $newAssignee->id,
        ])->assertOk();

        $log = ActivityLog::where('ticket_id', $ticket->id)
            ->where('event', 'ticket_assigned')
            ->first();

        expect($log)->not->toBeNull()
            ->and($log->properties['old'])->toBe($firstAssignee->id)
            ->and($log->properties['new'])->toBe($newAssignee->id);
    });

    it('creates an activity log entry when a ticket is unassigned', function () {
        [$user, $org] = createOrgUser();

        $assignee = User::factory()->create([
            'organization_id' => $org->id,
        ]);

        $ticket = createTicket($org, $user, [
            'assigned_to' => $assignee->id,
        ]);

        $this->patchJson("/api/v1/tickets/{$ticket->id}", [
            'assigned_to' => null,
        ])->assertOk();

        $this->assertDatabaseHas('activity_logs', [
            'organization_id' => $org->id,
            'ticket_id'       => $ticket->id,
            'event'           => 'ticket_unassigned',
        ]);

        $log = ActivityLog::where('ticket_id', $ticket->id)
            ->where('event', 'ticket_unassigned')
            ->first();

        expect($log)->not->toBeNull()
            ->and($log->properties['old'])->toBe($assignee->id)
            ->and($log->properties['new'])->toBeNull();
    });

    it('does not create an assignment log when the assignee remains the same', function () {
        [$user, $org] = createOrgUser();

        $assignee = User::factory()->create([
            'organization_id' => $org->id,
        ]);

        $ticket = createTicket($org, $user, [
            'assigned_to' => $assignee->id,
        ]);

        $this->patchJson("/api/v1/tickets/{$ticket->id}", [
            'assigned_to' => $assignee->id,
        ])->assertOk();

        $this->assertDatabaseMissing('activity_logs', [
            'ticket_id' => $ticket->id,
            'event'     => 'ticket_assigned',
        ]);
    });
});

/*
|--------------------------------------------------------------------------
| Activity Log — Combined Changes
|--------------------------------------------------------------------------
*/

describe('Activity Log — Combined Changes', function () {
    it('creates separate log entries when both status and assignment change in one request', function () {
        [$user, $org] = createOrgUser();
        $ticket = createTicket($org, $user, [
            'status'      => 'open',
            'assigned_to' => null,
        ]);

        $assignee = User::factory()->create([
            'organization_id' => $org->id,
        ]);

        $this->patchJson("/api/v1/tickets/{$ticket->id}", [
            'status'      => 'in_progress',
            'assigned_to' => $assignee->id,
        ])->assertOk();

        $logs = ActivityLog::where('ticket_id', $ticket->id)->get();

        expect($logs)->toHaveCount(2)
            ->and($logs->pluck('event')->toArray())
            ->toContain('status_changed')
            ->toContain('ticket_assigned');
    });

    it('creates a single log when only one field changes among multiple sent', function () {
        [$user, $org] = createOrgUser();

        $assignee = User::factory()->create([
            'organization_id' => $org->id,
        ]);

        $ticket = createTicket($org, $user, [
            'status'      => 'open',
            'assigned_to' => $assignee->id,
        ]);

        // Send both fields but only status actually changes
        $this->patchJson("/api/v1/tickets/{$ticket->id}", [
            'status'      => 'in_progress',
            'assigned_to' => $assignee->id,
        ])->assertOk();

        $logs = ActivityLog::where('ticket_id', $ticket->id)->get();

        expect($logs)->toHaveCount(1)
            ->and($logs->first()->event)->toBe('status_changed');
    });
});

/*
|--------------------------------------------------------------------------
| Activity Log — Organization Scoping
|--------------------------------------------------------------------------
*/

describe('Activity Log — Organization Scoping', function () {
    it('does not create activity logs when updating a ticket from another organization', function () {
        [$user, $org] = createOrgUser();

        $otherOrg = Organization::factory()->create();
        $otherUser = User::factory()->create([
            'organization_id' => $otherOrg->id,
        ]);

        $ticket = createTicket($otherOrg, $otherUser, ['status' => 'open']);

        $this->patchJson("/api/v1/tickets/{$ticket->id}", [
            'status' => 'in_progress',
        ])->assertForbidden();

        $this->assertDatabaseMissing('activity_logs', [
            'ticket_id' => $ticket->id,
        ]);
    });

    it('scopes activity logs to the authenticated user\'s organization', function () {
        [$user, $org] = createOrgUser();

        $ticket = createTicket($org, $user, ['status' => 'open']);

        $this->patchJson("/api/v1/tickets/{$ticket->id}", [
            'status' => 'in_progress',
        ])->assertOk();

        $otherOrg = Organization::factory()->create();
        $otherUser = User::factory()->create([
            'organization_id' => $otherOrg->id,
        ]);
        $otherTicket = createTicket($otherOrg, $otherUser, ['status' => 'open']);

        $this->patchJson("/api/v1/tickets/{$otherTicket->id}", [
            'status' => 'resolved',
        ])->assertForbidden();

        $this->assertDatabaseHas('activity_logs', [
            'organization_id' => $org->id,
            'ticket_id'       => $ticket->id,
        ]);

        $this->assertDatabaseMissing('activity_logs', [
            'organization_id' => $org->id,
            'ticket_id'       => $otherTicket->id,
        ]);
    });
});

/*
|--------------------------------------------------------------------------
| Activity Log — Retrieval
|--------------------------------------------------------------------------
*/

describe('Activity Log — Retrieval', function () {
    it('returns activity logs scoped to the ticket and organization', function () {
        [$user, $org] = createOrgUser();

        $ticket = createTicket($org, $user, ['status' => 'open']);

        $this->patchJson("/api/v1/tickets/{$ticket->id}", [
            'status' => 'in_progress',
        ])->assertOk();

        $this->patchJson("/api/v1/tickets/{$ticket->id}", [
            'status' => 'resolved',
        ])->assertOk();

        $response = $this->getJson("/api/v1/tickets/{$ticket->id}/activity")
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'ticket_id', 'user_id', 'event', 'properties', 'created_at'],
                ],
            ]);

        $logs = $response->json('data');

        expect($logs)->toHaveCount(2)
            ->and(collect($logs)->pluck('event')->unique()->toArray())
            ->toContain('status_changed');
    });

    it('only returns activity logs for tickets within the user\'s organization', function () {
        [$user, $org] = createOrgUser();

        $otherOrg = Organization::factory()->create();
        $otherUser = User::factory()->create([
            'organization_id' => $otherOrg->id,
        ]);
        $otherTicket = createTicket($otherOrg, $otherUser, ['status' => 'open']);

        $this->getJson("/api/v1/tickets/{$otherTicket->id}/activity")
            ->assertForbidden();
    });

    it('returns activity logs ordered by most recent first', function () {
        [$user, $org] = createOrgUser();
        $ticket = createTicket($org, $user, ['status' => 'open']);

        $this->patchJson("/api/v1/tickets/{$ticket->id}", [
            'status' => 'in_progress',
        ])->assertOk();

        $this->patchJson("/api/v1/tickets/{$ticket->id}", [
            'status' => 'resolved',
        ])->assertOk();

        $logs = $this->getJson("/api/v1/tickets/{$ticket->id}/activity")
            ->assertOk()
            ->json('data');

        expect($logs[0]['properties']['new'])->toBe('resolved')
            ->and($logs[1]['properties']['new'])->toBe('in_progress');
    });
});
