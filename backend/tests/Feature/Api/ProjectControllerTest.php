<?php

declare(strict_types=1);

use App\Models\Organization;
use App\Models\Project;
use App\Models\User;

describe('ProjectController — authenticated', function (): void {

    // ── INDEX ────────────────────────────────────────────────────

    it('returns a paginated list of projects for the user organization', function (): void {
        [$org, $user] = createTenant();
        $projects = Project::factory()
            ->for($org)
            ->count(3)
            ->create();

        $response = $this->actingAsSanctum($user)
            ->getJson('/api/projects');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'description', 'status', 'color', 'created_at', 'updated_at'],
                ],
                'meta' => ['current_page', 'per_page', 'total'],
            ])
            ->assertJsonCount(3, 'data');
    });

    it('does not return projects from other organizations', function (): void {
        [$orgA, $userA] = createTenant();

        $orgB = Organization::factory()->create();
        Project::factory()->for($orgA)->create();
        Project::factory()->for($orgB)->count(2)->create();

        $response = $this->actingAsSanctum($userA)
            ->getJson('/api/projects');

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    });

    it('filters projects by status', function (): void {
        [$org, $user] = createTenant();

        Project::factory()->for($org)->active()->create();
        Project::factory()->for($org)->archived()->create();

        $response = $this->actingAsSanctum($user)
            ->getJson('/api/projects?status=archived');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'archived');
    });

    it('searches projects by name', function (): void {
        [$org, $user] = createTenant();

        Project::factory()->for($org)->create(['name' => 'Alpha Radar']);
        Project::factory()->for($org)->create(['name' => 'Beta Dashboard']);

        $response = $this->actingAsSanctum($user)
            ->getJson('/api/projects?search=Radar');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Alpha Radar');
    });

    // ── STORE ────────────────────────────────────────────────────

    it('creates a project scoped to the authenticated user organization', function (): void {
        [$org, $user] = createTenant();

        $response = $this->actingAsSanctum($user)
            ->postJson('/api/projects', [
                'name' => 'New Initiative',
                'description' => 'A strategic project for Q3.',
                'status' => 'active',
                'color' => '#3B82F6',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'New Initiative')
            ->assertJsonPath('data.status', 'active');

        expect(Project::where('organization_id', $org->id)->count())->toBe(1)
            ->and(Project::first()->created_by)->toBe($user->id);
    });

    it('never accepts organization_id from request input', function (): void {
        [$org, $user] = createTenant();
        $orgB = Organization::factory()->create();

        $response = $this->actingAsSanctum($user)
            ->postJson('/api/projects', [
                'name' => 'Hostile Takeover',
                'organization_id' => $orgB->id,
            ]);

        $response->assertCreated();

        expect(Project::first()->organization_id)->toBe($org->id)
            ->not->toBe($orgB->id);
    });

    it('validates required fields on store', function (): void {
        [, $user] = createTenant();

        $this->actingAsSanctum($user)
            ->postJson('/api/projects', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    });

    it('validates status enum on store', function (): void {
        [, $user] = createTenant();

        $this->actingAsSanctum($user)
            ->postJson('/api/projects', [
                'name' => 'Test',
                'status' => 'deleted',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    });

    // ── SHOW ─────────────────────────────────────────────────────

    it('shows a project belonging to the user organization', function (): void {
        [$org, $user] = createTenant();
        $project = Project::factory()->for($org)->create();

        $response = $this->actingAsSanctum($user)
            ->getJson("/api/projects/{$project->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $project->id);
    });

    it('returns 404 when accessing a project from another organization', function (): void {
        [$org, $user] = createTenant();
        $orgB = Organization::factory()->create();
        $foreignProject = Project::factory()->for($orgB)->create();

        $this->actingAsSanctum($user)
            ->getJson("/api/projects/{$foreignProject->id}")
            ->assertNotFound();
    });

    // ── UPDATE ───────────────────────────────────────────────────

    it('updates a project belonging to the user organization', function (): void {
        [$org, $user] = createTenant();
        $project = Project::factory()->for($org)->create();

        $response = $this->actingAsSanctum($user)
            ->putJson("/api/projects/{$project->id}", [
                'name' => 'Updated Name',
                'status' => 'on_hold',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Updated Name')
            ->assertJsonPath('data.status', 'on_hold');

        expect($project->fresh()->name)->toBe('Updated Name');
    });

    it('returns 404 when updating a project from another organization', function (): void {
        [, $user] = createTenant();
        $orgB = Organization::factory()->create();
        $foreignProject = Project::factory()->for($orgB)->create();

        $this->actingAsSanctum($user)
            ->putJson("/api/projects/{$foreignProject->id}", ['name' => 'Hacked'])
            ->assertNotFound();
    });

    it('strips organization_id from update payload', function (): void {
        [$org, $user] = createTenant();
        $project = Project::factory()->for($org)->create();
        $orgB = Organization::factory()->create();

        $this->actingAsSanctum($user)
            ->putJson("/api/projects/{$project->id}", [
                'name' => 'Still Mine',
                'organization_id' => $orgB->id,
            ])
            ->assertOk();

        expect($project->fresh()->organization_id)->toBe($org->id);
    });

    // ── DESTROY ──────────────────────────────────────────────────

    it('deletes a project belonging to the user organization', function (): void {
        [$org, $user] = createTenant();
        $project = Project::factory()->for($org)->create();

        $this->actingAsSanctum($user)
            ->deleteJson("/api/projects/{$project->id}")
            ->assertNoContent();

        expect(Project::find($project->id))->toBeNull();
    });

    it('returns 404 when deleting a project from another organization', function (): void {
        [, $user] = createTenant();
        $orgB = Organization::factory()->create();
        $foreignProject = Project::factory()->for($orgB)->create();

        $this->actingAsSanctum($user)
            ->deleteJson("/api/projects/{$foreignProject->id}")
            ->assertNotFound();

        expect($foreignProject->fresh())->not->toBeNull();
    });
});

describe('ProjectController — unauthenticated', function (): void {

    it('rejects index without a token', function (): void {
        $this->getJson('/api/projects')->assertUnauthorized();
    });

    it('rejects store without a token', function (): void {
        $this->postJson('/api/projects', ['name' => 'X'])->assertUnauthorized();
    });

    it('rejects show without a token', function (): void {
        $this->getJson('/api/projects/1')->assertUnauthorized();
    });

    it('rejects update without a token', function (): void {
        $this->putJson('/api/projects/1', [])->assertUnauthorized();
    });

    it('rejects destroy without a token', function (): void {
        $this->deleteJson('/api/projects/1')->assertUnauthorized();
    });
});

// ── Helper ─────────────────────────────────────────────────────────

function createTenant(): array
{
    $org = Organization::factory()->create();
    $user = User::factory()->for($org)->create();

    return [$org, $user];
}
