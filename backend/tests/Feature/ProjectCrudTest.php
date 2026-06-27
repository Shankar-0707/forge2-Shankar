<?php

use App\Models\User;
use App\Models\Organization;
use App\Models\Project;
use Laravel\Sanctum\Sanctum;

describe('Project CRUD', function () {
    beforeEach(function () {
        $this->org = Organization::factory()->create();
        $this->user = User::factory()->forOrganization($this->org->id)->create();
        Sanctum::actingAs($this->user);
    });

    it('lists projects as a paginated collection', function () {
        Project::factory()->count(3)->forOrganization($this->org->id)->create();

        $this->getJson('/api/projects')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [['id', 'name', 'description', 'organization_id']],
                'meta' => ['current_page', 'total', 'per_page'],
            ])
            ->assertJsonPath('meta.total', 3);
    });

    it('shows a single project', function () {
        $project = Project::factory()->forOrganization($this->org->id)->create();

        $this->getJson("/api/projects/{$project->id}")
            ->assertOk()
            ->assertJsonPath('id', $project->id)
            ->assertJsonPath('name', $project->name)
            ->assertJsonPath('organization_id', $this->org->id);
    });

    it('creates a project with valid payload and scopes it to the user org', function () {
        $response = $this->postJson('/api/projects', [
            'name' => 'PulseDesk MVP',
            'description' => 'Initial release scope',
        ])->assertCreated();

        $response->assertJsonPath('name', 'PulseDesk MVP')
            ->assertJsonPath('organization_id', $this->org->id);

        $this->assertDatabaseHas('projects', [
            'name' => 'PulseDesk MVP',
            'organization_id' => $this->org->id,
        ]);
    });

    it('validates required fields on create', function () {
        $this->postJson('/api/projects', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name']);

        $this->postJson('/api/projects', ['name' => str_repeat('x', 256)])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    });

    it('updates an existing project', function () {
        $project = Project::factory()->forOrganization($this->org->id)->create();

        $this->putJson("/api/projects/{$project->id}", [
            'name' => 'Renamed Project',
            'description' => 'Updated description',
        ])->assertOk()
            ->assertJsonPath('name', 'Renamed Project');

        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'name' => 'Renamed Project',
        ]);
    });

    it('partially updates a project via PATCH', function () {
        $project = Project::factory()->forOrganization($this->org->id)->create([
            'description' => 'Original',
        ]);

        $this->patchJson("/api/projects/{$project->id}", [
            'name' => 'New Name Only',
        ])->assertOk()
            ->assertJsonPath('name', 'New Name Only')
            ->assertJsonPath('description', 'Original');
    });

    it('deletes a project', function () {
        $project = Project::factory()->forOrganization($this->org->id)->create();

        $this->deleteJson("/api/projects/{$project->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('projects', ['id' => $project->id]);
    });

    it('returns 404 for non-existent project', function () {
        $this->getJson('/api/projects/999999')->assertNotFound();
        $this->putJson('/api/projects/999999', ['name' => 'x'])->assertNotFound();
        $this->deleteJson('/api/projects/999999')->assertNotFound();
    });

    it('blocks unauthenticated access', function () {
        auth()->forgetGuards();

        $this->getJson('/api/projects')->assertUnauthorized();
        $this->postJson('/api/projects', ['name' => 'x'])->assertUnauthorized();
    });

    it('filters out projects from other orgs even on show', function () {
        $otherOrg = Organization::factory()->create();
        $otherProject = Project::factory()->forOrganization($otherOrg->id)->create();

        $this->getJson("/api/projects/{$otherProject->id}")
            ->assertNotFound();
    });
});
