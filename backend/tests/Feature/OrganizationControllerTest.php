<?php

use App\Models\Organization;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

describe('OrganizationController — Authentication', function () {
    it('requires authentication for all endpoints', function ($method, $endpoint) {
        $this->json($method, $endpoint)->assertUnauthorized();
    })->with([
        'index'   => ['GET', '/api/organizations'],
        'show'    => ['GET', '/api/organizations/1'],
        'update'  => ['PUT', '/api/organizations/1'],
        'destroy' => ['DELETE', '/api/organizations/1'],
    ]);
});

describe('OrganizationController — index', function () {
    it('returns the auth users organization', function () {
        $org = Organization::factory()->create();
        $user = User::factory()->for($org)->create();

        Sanctum::actingAs($user);

        $this->getJson('/api/organizations')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'slug', 'created_at', 'updated_at'],
                ],
            ])
            ->assertJsonPath('data.0.id', $org->id);
    });
});

describe('OrganizationController — show', function () {
    it('returns the organization with users loaded', function () {
        $org = Organization::factory()->create();
        $user = User::factory()->for($org)->create();

        Sanctum::actingAs($user);

        $this->getJson("/api/organizations/{$org->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $org->id)
            ->assertJsonPath('data.name', $org->name)
            ->assertJsonStructure([
                'data' => [
                    'id', 'name', 'slug', 'users', 'users_count',
                ],
            ]);
    });

    it('returns 404 when accessing another organization', function () {
        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();
        $user = User::factory()->for($orgA)->create();

        Sanctum::actingAs($user);

        $this->getJson("/api/organizations/{$orgB->id}")->assertNotFound();
    });
});

describe('OrganizationController — update', function () {
    it('updates the organization name', function () {
        $org = Organization::factory()->create(['name' => 'Old Name']);
        $user = User::factory()->for($org)->create();

        Sanctum::actingAs($user);

        $this->putJson("/api/organizations/{$org->id}", [
            'name' => 'New Name',
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'New Name');

        expect($org->fresh()->name)->toBe('New Name');
    });

    it('updates the organization slug', function () {
        $org = Organization::factory()->create();
        $user = User::factory()->for($org)->create();

        Sanctum::actingAs($user);

        $this->putJson("/api/organizations/{$org->id}", [
            'slug' => 'new-slug',
        ])
            ->assertOk()
            ->assertJsonPath('data.slug', 'new-slug');
    });

    it('rejects duplicate slug from another organization', function () {
        $orgA = Organization::factory()->create(['slug' => 'taken-slug']);
        $orgB = Organization::factory()->create();
        $user = User::factory()->for($orgB)->create();

        Sanctum::actingAs($user);

        $this->putJson("/api/organizations/{$orgB->id}", [
            'slug' => 'taken-slug',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['slug']);
    });

    it('allows keeping the same slug on update', function () {
        $org = Organization::factory()->create(['slug' => 'my-slug']);
        $user = User::factory()->for($org)->create();

        Sanctum::actingAs($user);

        $this->putJson("/api/organizations/{$org->id}", [
            'slug' => 'my-slug',
        ])->assertOk();
    });

    it('returns 404 when updating another organization', function () {
        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();
        $user = User::factory()->for($orgA)->create();

        Sanctum::actingAs($user);

        $this->putJson("/api/organizations/{$orgB->id}", [
            'name' => 'Hacked',
        ])->assertNotFound();
    });

    it('validates name is not empty', function () {
        $org = Organization::factory()->create();
        $user = User::factory()->for($org)->create();

        Sanctum::actingAs($user);

        $this->putJson("/api/organizations/{$org->id}", [
            'name' => '',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    });
});

describe('OrganizationController — destroy', function () {
    it('deletes the organization', function () {
        $org = Organization::factory()->create();
        $user = User::factory()->for($org)->create();

        Sanctum::actingAs($user);

        $this->deleteJson("/api/organizations/{$org->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Organization deleted successfully.');

        $this->assertDatabaseMissing('organizations', ['id' => $org->id]);
    });

    it('returns 404 when deleting another organization', function () {
        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();
        $user = User::factory()->for($orgA)->create();

        Sanctum::actingAs($user);

        $this->deleteJson("/api/organizations/{$orgB->id}")->assertNotFound();
    });

    it('cascades deletion to users', function () {
        $org = Organization::factory()->create();
        $user = User::factory()->for($org)->create();
        $otherUser = User::factory()->for($org)->create();

        Sanctum::actingAs($user);

        $this->deleteJson("/api/organizations/{$org->id}");

        $this->assertDatabaseMissing('users', ['id' => $otherUser->id]);
    });
});
