<?php

use App\Models\Category;
use App\Models\Organization;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Test Setup
|--------------------------------------------------------------------------
*/
beforeEach(function () {
    $this->organization = Organization::factory()->create();
    $this->user = User::factory()->create([
        'organization_id' => $this->organization->id,
    ]);
    $this->otherOrg = Organization::factory()->create();
    $this->otherUser = User::factory()->create([
        'organization_id' => $this->otherOrg->id,
    ]);
});

/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/
it('returns 401 for unauthenticated users', function () {
    $this->getJson('/api/v1/dashboard/metrics')
        ->assertUnauthorized();
});

it('returns 200 for authenticated users', function () {
    $this->actingAs($this->user)
        ->getJson('/api/v1/dashboard/metrics')
        -> ->assertOk();
});

/*
|--------------------------------------------------------------------------
| Response Shape / Structure
|--------------------------------------------------------------------------
*/
it('returns the expected JSON structure', function () {
    $this->actingAs($this->user)
        ->getJson('/api/v1/dashboard/metrics')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                'tickets' => [
                    'total',
                    'open',
                    'pending',
                    'closed',
                    'unassigned',
                ],
                'sla' => [
                    'within_sla',
                    'breached',
                    'at_risk',
                ],
                'performance' => [
                    'avg_first_response_time_minutes',
                    'avg_resolution_time_hours',
                    'satisfaction_score',
                ],
                'trend' => [
                    'created_today',
                    'created_this_week',
                    'resolved_today',
                    'resolved_this_week',
                ],
                'breakdown' => [
                    'by_priority' => [
                        'low',
                        'medium',
                        'high',
                        'urgent',
                    ],
                    'by_status' => [
                        'open',
                        'in_progress',
                    ],
                ],
            ],
        ]);
});

it('returns all numeric fields as integers or floats', function () {
    $response = $this->actingAs($this->user)
        ->getJson('/api/v1/dashboard/metrics')
        ->assertOk()
        ->json('data');

    expect($response['tickets']['total'])->toBeInt();
    expect($response['tickets']['open'])->toBeInt();
    expect($response['tickets']['pending'])->toBeInt();
    expect($response['tickets']['closed'])->toBeInt();
    expect($response['tickets']['unassigned'])->toBeInt();
    expect($response['sla']['within_sla'])->toBeInt();
    expect($response['sla']['breached'])->toBeInt();
    expect($response['sla']['at_risk'])->toBeInt();
    expect($response['performance']['avg_first_response_time_minutes'])->toBeFloat();
    expect($response['performance']['avg_resolution_time_hours'])->toBeFloat();
    expect($response['performance']['satisfaction_score'])->toBeFloat();
    expect($response['trend']['created_today'])->toBeInt();
    expect($response['trend']['created_this_week'])->toBeInt();
    expect($response['trend']['resolved_today'])->toBeInt();
    expect($response['trend']['resolved_this_week'])->toBeInt();
    expect($response['breakdown']['by_priority']['low'])->toBeInt();
    expect($response['breakdown']['by_priority']['medium'])->toBeInt();
    expect($response['breakdown']['by_priority']['high'])->toBeInt();
    expect($response['breakdown']['by_priority']['urgent'])->toBeInt();
    expect($response['breakdown']['by_status']['open'])->toBeInt();
    expect($response['breakdown']['by_status']['in_progress'])->toBeInt();
});

/*
|--------------------------------------------------------------------------
| Accuracy — Empty State
|--------------------------------------------------------------------------
*/
it('returns zero values when organization has no tickets', function () {
    $response = $this->actingAs($this->user)
        ->getJson('/api/v1/dashboard/metrics')
        ->assertOk()
        ->json('data');

    expect($response['tickets']['total'])->toBe(0);
    expect($response['tickets']['open'])->toBe(0);
    expect($response['tickets']['pending'])->toBe(0);
    expect($response['tickets']['closed'])->toBe(0);
    expect($response['tickets']['unassigned'])->toBe(0);
    expect($response['sla']['within_sla'])->toBe(0);
    expect($response['sla']['breached'])->toBe(0);
    expect($response['sla']['at_risk'])->toBe(0);
    expect($response['performance']['avg_first_response_time_minutes'])->toBe(0.0);
    expect($response['performance']['avg_resolution_time_hours'])->toBe(0.0);
    expect($response['performance']['satisfaction_score'])->toBe(0.0);
    expect($response['trend']['created_today'])->toBe(0);
    expect($response['trend']['created_this_week'])->toBe(0);
    expect($response['trend']['resolved_today'])->toBe(0);
    expect($response['trend']['resolved_this_week'])->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Accuracy — Ticket Counts
|--------------------------------------------------------------------------
*/
it('correctly counts total open pending and closed tickets', function () {
    Ticket::factory()->count(3)->create([
        'organization_id' => $this->organization->id,
        'status' => 'open',
    ]);
    Ticket::factory()->count(2)->create([
        'organization_id' => $this->organization->id,
        'status' => 'pending',
    ]);
    Ticket::factory()->count(5)->create([
        'organization_id' => $this->organization->id,
        'status' => 'closed',
    ]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/v1/dashboard/metrics')
        ->assertOk()
        ->json('data');

    expect($response['tickets']['total'])->toBe(10);
    expect($response['tickets']['open'])->toBe(3);
    expect($response['tickets']['pending'])->toBe(2);
    expect($response['tickets']['closed'])->toBe(5);
});

it('counts only unassigned tickets in the unassigned metric', function () {
    Ticket::factory()->count(4)->create([
        'organization_id' => $this->organization->id,
        'status' => 'open',
        'assigned_to' => null,
    ]);
    Ticket::factory()->count(3)->create([
        'organization_id' => $this->organization->id,
        'status' => 'open',
        'assigned_to' => $this->user->id,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/v1/dashboard/metrics')
        ->assertOk()
        ->json('data');

    expect($response['tickets']['unassigned'])->toBe(4);
});

/*
|--------------------------------------------------------------------------
| Accuracy — Organization Scoping
|--------------------------------------------------------------------------
*/
it('only counts tickets belonging to the authenticated users organization', function () {
    // My org's tickets
    Ticket::factory()->count(6)->create([
        'organization_id' => $this->organization->id,
        'status' => 'open',
    ]);

    // Other org's tickets — must NOT be counted
    Ticket::factory()->count(15)->create([
        'organization_id' => $this->otherOrg->id,
        'status' => 'open',
    ]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/v1/dashboard/metrics')
        ->assertOk()
        ->json('data');

    expect($response['tickets']['total'])->toBe(6);
    expect($response['tickets']['open'])->toBe(6);
});

it('returns different metrics for users in different organizations', function () {
    Ticket::factory()->count(3)->create([
        'organization_id' => $this->organization->id,
        'status' => 'open',
    ]);
    Ticket::factory()->count(7)->create([
        'organization_id' => $this->otherOrg->id,
        'status' => 'open',
    ]);

    $myMetrics = $this->actingAs($this->user)
        ->getJson('/api/v1/dashboard/metrics')
        ->assertOk()
        ->json('data');

    $theirMetrics = $this->actingAs($this->otherUser)
        ->getJson('/api/v1/dashboard/metrics')
        ->assertOk()
        ->json('data');

    expect($myMetrics['tickets']['total'])->toBe(3);
    expect($theirMetrics['tickets']['total'])->toBe(7);
});

/*
|--------------------------------------------------------------------------
| Accuracy — Breakdown by Priority
|--------------------------------------------------------------------------
*/
it('correctly breaks down tickets by priority', function () {
    Ticket::factory()->count(2)->create([
        'organization_id' => $this->organization->id,
        'status' => 'open',
        'priority' => 'low',
    ]);
    Ticket::factory()->count(3)->create([
        'organization_id' => $this->organization->id,
        'status' => 'open',
        'priority' => 'medium',
    ]);
    Ticket::factory()->count(1)->create([
        'organization_id' => $this->organization->id,
        'status' => 'open',
        'priority' => 'high',
    ]);
    Ticket::factory()->count(4)->create([
        'organization_id' => $this->organization->id,
        'status' => 'open',
        'priority' => 'urgent',
    ]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/v1/dashboard/metrics')
        ->assertOk()
        ->json('data');

    expect($response['breakdown']['by_priority']['low'])->toBe(2);
    expect($response['breakdown']['by_priority']['medium'])->toBe(3);
    expect($response['breakdown']['by_priority']['high'])->toBe(1);
    expect($response['breakdown']['by_priority']['urgent'])->toBe(4);
});

/*
|--------------------------------------------------------------------------
| Accuracy — Breakdown by Status
|--------------------------------------------------------------------------
*/
it('correctly breaks down open tickets by status subcategory', function () {
    Ticket::factory()->count(5)->create([
        'organization_id' => $this->organization->id,
        'status' => 'open',
    ]);
    Ticket::factory()->count(4)->create([
        'organization_id' => $this->organization->id,
        'status' => 'in_progress',
    ]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/v1/dashboard/metrics')
        ->assertOk()
        ->json('data');

    expect($response['breakdown']['by_status']['open'])->toBe(5);
    expect($response['breakdown']['by_status']['in_progress'])->toBe(4);
});

/*
|--------------------------------------------------------------------------
| Accuracy — SLA Metrics
|--------------------------------------------------------------------------
*/
it('correctly counts SLA within breached and at risk tickets', function () {
    // Within SLA
    Ticket::factory()->create([
        'organization_id' => $this->organization->id,
        'status' => 'open',
        'sla_due_at' => now()->addHours(5),
    ]);

    // Breached SLA
    Ticket::factory()->count(2)->create([
        'organization_id' => $this->organization->id,
        'status' => 'open',
        'sla_due_at' => now()->subHours(2),
    ]);

    // At risk (within 1 hour)
    Ticket::factory()->create([
        'organization_id' => $this->organization->id,
        'status' => 'open',
        'sla_due_at' => now()->addMinutes(30),
    ]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/v1/dashboard/metrics')
        ->assertOk()
        ->json('data');

    expect($response['sla']['within_sla'])->toBe(1);
    expect($response['sla']['breached'])->toBe(2);
    expect($response['sla']['at_risk'])->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Accuracy — Performance Metrics
|--------------------------------------------------------------------------
*/
it('calculates average first response time correctly', function () {
    Ticket::factory()->create([
        'organization_id' => $this->organization->id,
        'status' => 'closed',
        'first_response_at' => now()->subHours(3),
        'created_at' => now()->subHours(5),
        'resolved_at' => now()->subHour(),
    ]);

    Ticket::factory()->create([
        'organization_id' => $this->organization->id,
        'status' => 'closed',
        'first_response_at' => now()->subHours(1),
        'created_at' => now()->subHours(7),
        'resolved_at' => now()->subMinutes(30),
    ]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/v1/dashboard/metrics')
        ->assertOk()
        ->json('data');

    // Ticket 1: 2 hours = 120 min
    // Ticket 2: 6 hours = 360 min
    // Average: 240 minutes
    expect($response['performance']['avg_first_response_time_minutes'])->toBeFloat();
    expect($response['performance']['avg_first_response_time_minutes'])->toBeGreaterThan(235.0);
    expect($response['performance']['avg_first_response_time_minutes'])->toBeLessThan(245.0);
});

it('calculates average resolution time correctly', function () {
    Ticket::factory()->create([
        'organization_id' => $this->organization->id,
        'status' => 'closed',
        'created_at' => now()->subHours(4),
        'resolved_at' => now()->subHours(1),
    ]);

    Ticket::factory()->create([
        'organization_id' => $this->organization->id,
        'status' => 'closed',
        'created_at' => now()->subHours(10),
        'resolved_at' => now()->subHours(2),
    ]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/v1/dashboard/metrics')
        ->assertOk()
        ->json('data');

    // Ticket 1: 3 hours
    // Ticket 2: 8 hours
    // Average: 5.5 hours
    expect($response['performance']['avg_resolution_time_hours'])->toBeFloat();
    expect($response['performance']['avg_resolution_time_hours'])->toBeGreaterThan(5.0);
    expect($response['performance']['avg_resolution_time_hours'])->toBeLessThan(6.0);
});

it('calculates satisfaction score as the average of rated tickets', function () {
    Ticket::factory()->create([
        'organization_id' => $this->organization->id,
        'status' => 'closed',
        'satisfaction_rating' => 5,
    ]);
    Ticket::factory()->create([
        'organization_id' => $this->organization->id,
        'status' => 'closed',
        'satisfaction_rating' => 3,
    ]);
    // Unrated ticket — should be excluded from the average
    Ticket::factory()->create([
        'organization_id' => $this->organization->id,
        'status' => 'closed',
        'satisfaction_rating' => null,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/v1/dashboard/metrics')
        ->assertOk()
        ->json('data');

    // Average of 5 and 3 = 4.0
    expect($response['performance']['satisfaction_score'])->toBe(4.0);
});

/*
|--------------------------------------------------------------------------
| Accuracy — Trend Metrics
|--------------------------------------------------------------------------
*/
it('counts tickets created today correctly', function () {
    // Created today
    Ticket::factory()->count(3)->create([
        'organization_id' => $this->organization->id,
        'created_at' => now(),
    ]);

    // Created yesterday
    Ticket::factory()->count(2)->create([
        'organization_id' => $this->organization->id,
        'created_at' => now()->subDay(),
    ]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/v1/dashboard/metrics')
        ->assertOk()
        ->json('data');

    expect($response['trend']['created_today'])->toBe(3);
});

it('counts tickets created this week correctly', function () {
    // Within this week
    Ticket::factory()->count(4)->create([
        'organization_id' => $this->organization->id,
        'created_at' => now()->subDays(2),
    ]);

    // Outside this week
    Ticket::factory()->count(5)->create([
        'organization_id' => $this->organization->id,
        'created_at' => now()->subDays(10),
    ]);

    // Created today (also counts)
    Ticket::factory()->count(2)->create([
        'organization_id' => $this->organization->id,
        'created_at' => now(),
    ]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/v1/dashboard/metrics')
        ->assertOk()
        ->json('data');

    expect($response['trend']['created_this_week'])->toBe(6);
});

it('counts tickets resolved today correctly', function () {
    Ticket::factory()->count(3)->create([
        'organization_id' => $this->organization->id,
        'status' => 'closed',
        'resolved_at' => now(),
    ]);

    Ticket::factory()->count(2)->create([
        'organization_id' => $this->organization->id,
        'status' => 'closed',
        'resolved_at' => now()->subDay(),
    ]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/v1/dashboard/metrics')
        ->assertOk()
        ->json('data');

    expect($response['trend']['resolved_today'])->toBe(3);
});

it('counts tickets resolved this week correctly', function () {
    Ticket::factory()->count(4)->create([
        'organization_id' => $this->organization->id,
        'status' => 'closed',
        'resolved_at' => now()->subDays(3),
    ]);

    Ticket::factory()->count(5)->create([
        'organization_id' => $this->organization->id,
        'status' => 'closed',
        'resolved_at' => now()->subDays(10),
    ]);

    Ticket::factory()->count(1)->create([
        'organization_id' => $this->organization->id,
        'status' => 'closed',
        'resolved_at' => now(),
    ]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/v1/dashboard/metrics')
        ->assertOk()
        ->json('data');

    expect($response['trend']['resolved_this_week'])->toBe(5);
});

/*
|--------------------------------------------------------------------------
| Accuracy — Trend respects organization scope
|--------------------------------------------------------------------------
*/
it('does not count other organizations tickets in trend metrics', function () {
    Ticket::factory()->count(10)->create([
        'organization_id' => $this->otherOrg->id,
        'created_at' => now(),
        'status' => 'closed',
        'resolved_at' => now(),
    ]);

    Ticket::factory()->count(2)->create([
        'organization_id' => $this->organization->id,
        'created_at' => now(),
        'status' => 'closed',
        'resolved_at' => now(),
    ]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/v1/dashboard/metrics')
        ->assertOk()
        ->json('data');

    expect($response['trend']['created_today'])->toBe(2);
    expect($response['trend']['resolved_today'])->toBe(2);
});
