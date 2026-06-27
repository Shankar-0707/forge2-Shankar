<?php

use App\Models\Organization;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

describe('UserController — Authentication', function () {
    it('requires authentication for all endpoints', function ($method, $endpoint) {
        $this->json($method, $endpoint)->assertUnauthorized();
    })->with([
        'index'   => ['GET', '/api/users'],
        'store'   => ['POST', '/api/users'],
        'show'    => ['GET', '/api/users/1'],
        'update'  => ['PUT', '/api/users/1'],
        'destroy' => ['DELETE', '/api/users/1'],
    ]);
});

describe('UserController — index', function () {
    it('returns paginated users from the same organization', function () {
        $org = Organization::factory()->create();
        $users = User::factory()->for($org)->count(3)->create();
        $authUser = User::factory()->for($org)->create();

        Sanctum::actingAs($authUser);

        $response = $this->getJson('/api/users')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'email', 'role', 'organization_id'],
                ],
                'meta' => ['current_page', 'total', 'per_page'],
            ]);

        $returnedIds = collect($response->json('data'))->pluck('id');

        foreach ($users as $user) {
            expect($returnedIds)->toContain($user->id);
        }
        expect($returnedIds)->toContain($authUser->id);
    });

    it('does not return users from other organizations', function () {
        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();

        $userA = User::factory()->for($orgA)->create();
        $userB = User::factory()->for($orgB)->create();

        Sanctum::actingAs($userA);

        $response = $this->getJson('/api/users')->assertOk();

        $returnedIds = collect($response->json('data'))->pluck('id');

        expect($returnedIds)->toContain($userA->id)
            ->and($returnedIds)->not->toContain($userB->id);
    });
});

describe('UserController — store', function () {
    it('creates a user in the auth users organization', function () {
        $org = Organization::factory()->create();
        $authUser = User::factory()->for($org)->create();

        Sanctum::actingAs($authUser);

        $response = $this->postJson('/api/users', [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'securepassword123',
        ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Jane Doe')
            ->assertJsonPath('data.email', 'jane@example.com')
            ->assertJsonPath('data.organization_id', $org->id);

        $this->assertDatabaseHas('users', [
            'email' => 'jane@example.com',
            'organization_id' => $org->id,
        ]);
    });

    it('never accepts organization_id from request input', function () {
        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();
        $authUser = User::factory()->for($orgA)->create();

        Sanctum::actingAs($authUser);

        $this->postJson('/api/users', [
            'name' => 'Spy',
            'email' => 'spy@example.com',
            'password' => 'password123',
            'organization_id' => $orgB->id,
        ])
            ->assertCreated()
            ->assertJsonPath('data.organization_id', $orgA->id);
    });

    it('validates required fields', function () {
        $org = Organization::factory()->create();
        $authUser = User::factory()->for($org)->create();

        Sanctum::actingAs($authUser);

        $this->postJson('/api/users', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'email', 'password']);
    });

    it('validates email uniqueness', function () {
        $org = Organization::factory()->create();
        $existing = User::factory()->for($org)->create(['email' => 'taken@example.com']);
        $authUser = User::factory()->for($org)->create();

        Sanctum::actingAs($authUser);

        $this->postJson('/api/users', [
            'name' => 'Dup',
            'email' => 'taken@example.com',
            'password' => 'password123',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    });

    it('validates password minimum length', function () {
        $org = Organization::factory()->create();
        $authUser = User::factory()->for($org)->create();

        Sanctum::actingAs($authUser);

        $this->postJson('/api/users', [
            'name' => 'Short Pass',
            'email' => 'short@example.com',
            'password' => '123',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    });

    it('accepts a valid role', function () {
        $org = Organization::factory()->create();
        $authUser = User::factory()->for($org)->create();

        Sanctum::actingAs($authUser);

        $this->postJson('/api/users', [
            'name' => 'Agent',
            'email' => 'agent@example.com',
            'password' => 'password123',
            'role' => 'agent',
        ])
            ->assertCreated()
            ->assertJsonPath('data.role', 'agent');
    });

    it('rejects an invalid role', function () {
        $org = Organization::factory()->create();
        $authUser = User::factory()->for($org)->create();

        Sanctum::actingAs($authUser);

        $this->postJson('/api/users', [
            'name' => 'Bad Role',
            'email' => 'badrole@example.com',
            'password' => 'password123',
            'role' => 'superadmin',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['role']);
    });
});

describe('UserController — show', function () {
    it('returns a user from the same organization', function () {
        $org = Organization::factory()->create();
        $target = User::factory()->for($org)->create();
        $authUser = User::factory()->for($org)->create();

        Sanctum::actingAs($authUser);

        $this->getJson("/api/users/{$target->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $target->id)
            ->assertJsonPath('data.name', $target->name);
    });

    it('returns 404 for a user from another organization', function () {
        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();
        $userB = User::factory()->for($orgB)->create();
        $authUser = User::factory()->for($orgA)->create();

        Sanctum::actingAs($authUser);

        $this->getJson("/api/users/{$userB->id}")->assertNotFound();
    });
});

describe('UserController — update', function () {
    it('updates a user in the same organization', function () {
        $org = Organization::factory()->create();
        $target = User::factory()->for($org)->create(['name' => 'Old Name']);
        $authUser = User::factory()->for($org)->create();

        Sanctum::actingAs($authUser);

        $this->putJson("/api/users/{$target->id}", [
            'name' => 'Updated Name',
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Updated Name');

        expect($target->fresh()->name)->toBe('Updated Name');
    });

    it('updates user email and keeps it unique', function () {
        $org = Organization::factory()->create();
        $target = User::factory()->for($org)->create(['email' => 'old@example.com']);
        $authUser = User::factory()->for($org)->create();

        Sanctum::actingAs($authUser);

        $this->putJson("/api/users/{$target->id}", [
            'email' => 'new@example.com',
        ])
            ->assertOk()
            ->assertJsonPath('data.email', 'new@example.com');
    });

    it('allows keeping the same email on update', function () {
        $org = Organization::factory()->create();
        $target = User::factory()->for($org)->create(['email' => 'keep@example.com']);
        $authUser = User::factory()->for($org)->create();

        Sanctum::actingAs($authUser);

        $this->putJson("/api/users/{$target->id}", [
            'email' => 'keep@example.com',
        ])->assertOk();
    });

    it('rejects an email already taken by another user', function () {
        $org = Organization::factory()->create();
        $other = User::factory()->for($org)->create(['email' => 'taken@example.com']);
        $target = User::factory()->for($org)->create();
        $authUser = User::factory()->for($org)->create();

        Sanctum::actingAs($authUser);

        $this->putJson("/api/users/{$target->id}", [
            'email' => 'taken@example.com',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    });

    it('updates user role', function () {
        $org = Organization::factory()->create();
        $target = User::factory()->for($org)->create(['role' => 'user']);
        $authUser = User::factory()->for($org)->create();

        Sanctum::actingAs($authUser);

        $this->putJson("/api/users/{$target->id}", [
            'role' => 'admin',
        ])
            ->assertOk()
            ->assertJsonPath('data.role', 'admin');
    });

    it('returns 404 when updating a user from another organization', function () {
        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();
        $userB = User::factory()->for($orgB)->create();
        $authUser = User::factory()->for($orgA)->create();

        Sanctum::actingAs($authUser);

        $this->putJson("/api/users/{$userB->id}", [
            'name' => 'Hacked',
        ])->assertNotFound();
    });

    it('does not allow organization_id to be changed via update', function () {
        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();
        $target = User::factory()->for($orgA)->create();
        $authUser = User::factory()->for($orgA)->create();

        Sanctum::actingAs($authUser);

        $this->putJson("/api/users/{$target->id}", [
            'organization_id' => $orgB->id,
        ])->assertOk();

        // organization_id should remain unchanged — not in UpdateUserRequest rules
        expect($target->fresh()->organization_id)->toBe($orgA->id);
    });
});

describe('UserController — destroy', function () {
    it('deletes a user from the same organization', function () {
        $org = Organization::factory()->create();
        $target = User::factory()->for($org)->create();
        $authUser = User::factory()->for($org)->create();

        Sanctum::actingAs($authUser);

        $this->deleteJson("/api/users/{$target->id}")
            ->assertOk()
            ->assertJsonPath('message', 'User deleted successfully.');

        $this->assertDatabaseMissing('users', ['id' => $target->id]);
    });

    it('prevents a user from deleting themselves', function () {
        $org = Organization::factory()->create();
        $authUser = User::factory()->for($org)->create();

        Sanctum::actingAs($authUser);

        $this->deleteJson("/api/users/{$authUser->id}")
            ->assertForbidden()
            ->assertJsonPath('message', 'You cannot delete your own account.');

        $this->assertDatabaseHas('users', ['id' => $authUser->id]);
    });

    it('returns 404 when deleting a user from another organization', function () {
        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();
        $userB = User::factory()->for($orgB)->create();
        $authUser = User::factory()->for($orgA)->create();

        Sanctum::actingAs($authUser);

        $this->deleteJson("/api/users/{$userB->id}")->assertNotFound();
    });
});
