<?php

use App\Models\User;
use App\Models\Organization;
use App\Models\Project;
use Laravel\Sanctum\Sanctum;

describe('Tenant isolation', function () {
    beforeEach(function () {
        $this->orgA = Organization::factory()->create();
        $this->orgB = Organization::factory()->create();

        $this->userA = User::factory()->forOrganization($this->orgA->id)->create();
        $this->userB = User::factory()->forOrganization($this->orgB->id)->create();
    });

    it('only lists projects belonging to the user organization', function () {
        $orgAProject = Project::factory()->forOrganization($this->orgA->id)->create();
        $orgBProject = Project::factory()->forOrganization($this->orgB->id)->create();

        Sanctum::actingAs($this->userA);

        $this->getJson('/api/projects')
            ->assertOk()
            ->assertJsonFragment(['id' => $orgAProject->id])
            ->assertJsonMissing(['id' => $orgBProject->id]);
    });

    it('returns 404 when accessing another organization project by id', function () {
        $orgBProject = Project::factory()->forOrganization($this->orgB->id)->create();

        Sanctum::actingAs($this->userA);

        $this->getJson("/api/projects/{$orgBProject->id}")
            ->assertNotFound();
    });

    it('cannot update a project owned by another organization', function () {
        $orgBProject = Project::factory()->forOrganization($this->orgB->id)->create();

        Sanctum::actingAs($this->userA);

        $this->putJson("/api/projects/{$orgBProject->id}", [
            'name' => 'Hijacked Project',
        ])->assertNotFound();

        expect($orgBProject->fresh()->name)->toBe($orgBProject->name);
    });

    it('cannot delete a project owned by another organization', function () {
        $orgBProject = Project::factory()->forOrganization($this->orgB->id)->create();

        Sanctum::actingAs($this->userA);

        $this->deleteJson("/api/projects/{$orgBProject->id}")
            ->assertNotFound();

        $this->assertDatabaseHas('projects', ['id' => $orgBProject->id]);
    });

    it('ignores organization_id supplied in request input and uses authenticated user org', function () {
        Sanctum::actingAs($this->userA);

        $this->postJson('/api/projects', [
            'name' => 'New Project',
            'description' => 'Should be assigned to org A',
            'organization_id' => $this->orgB->id, // malicious attempt
        ])->assertCreated();

        $this->assertDatabaseHas('projects', [
            'name' => 'New Project',
            'organization_id' => $this->orgA->id,
        ]);

        $this->assertDatabaseMissing('projects', [
            'name' => 'New Project',
            'organization_id' => $this->orgB->id,
        ]);
    });

    it('prevents a user from one org impersonating another org via payload manipulation', function () {
        Sanctum::actingAs($this->userA);

        $response = $this->postJson('/api/projects', [
            'name' => 'Cross-tenant Attempt',
            'organization_id' => $this->orgB->id,
        ])->assertCreated();

        expect($response->json('organization_id'))->toBe($this->orgA->id);
    });

    it('guarantees two users in different orgs see disjoint datasets', function () {
        Project::factory()->count(3)->forOrganization($this->orgA->id)->create();
        Project::factory()->count(2)->forOrganization($this->orgB->id)->create();

        Sanctum::actingAs($this->userA);
        $orgAIds = $this->getJson('/api/projects')->json('data.*.id');

        Sanctum::actingAs($this->userB);
        $orgBIds = $this->getJson('/api/projects')->json('data.*.id');

        expect($orgAIds)->toHaveCount(3)
            ->and($orgBIds)->toHaveCount(2)
            ->and(collect($orgAIds)->intersect($orgBIds))->toBeEmpty();
    });
});
