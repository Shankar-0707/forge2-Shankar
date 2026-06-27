<?php

use App\Models\User;
use App\Models\Organization;
use Laravel\Sanctum\Sanctum;
use Laravel\Sanctum\PersonalAccessToken;

describe('Authentication', function () {
    it('allows a user to login with valid credentials and receives a Sanctum token', function () {
        $org = Organization::factory()->create();
        $user = User::factory()->forOrganization($org->id)->withPassword('secret123')->create();

        $response = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'secret123',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'token',
                'user' => ['id', 'name', 'email', 'organization_id'],
            ])
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.organization_id', $org->id);

        expect($response->json('token'))->not->toBeEmpty();

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_type' => User::class,
            'tokenable_id' => $user->id,
            'name' => 'auth-token',
        ]);
    });

    it('rejects login with invalid password', function () {
        $org = Organization::factory()->create();
        $user = User::factory()->forOrganization($org->id)->create();

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertStatus(422);
    });

    it('rejects login with unknown email', function () {
        $this->postJson('/api/auth/login', [
            'email' => 'nobody@example.com',
            'password' => 'password',
        ])->assertStatus(422);
    });

    it('requires email and password fields', function () {
        $this->postJson('/api/auth/login', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'password']);
    });

    it('returns the authenticated user via /me', function () {
        $org = Organization::factory()->create();
        $user = User::factory()->forOrganization($org->id)->create();

        Sanctum::actingAs($user);

        $this->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('id', $user->id)
            ->assertJsonPath('organization_id', $org->id);
    });

    it('logs out and invalidates the current token', function () {
        $org = Organization::factory()->create();
        $user = User::factory()->forOrganization($org->id)->create();

        Sanctum::actingAs($user, ['*']);

        $token = $user->currentAccessToken;

        $this->postJson('/api/auth/logout')
            ->assertNoContent();

        expect(PersonalAccessToken::find($token->id))->toBeNull();
    });
});

describe('Protected routes', function () {
    it('rejects unauthenticated requests to protected endpoints', function () {
        $this->getJson('/api/auth/me')->assertUnauthorized();
        $this->getJson('/api/projects')->assertUnauthorized();
        $this->postJson('/api/auth/logout')->assertUnauthorized();
    });

    it('rejects requests with invalid tokens', function () {
        $this->withHeader('Authorization', 'Bearer invalid-token-string')
            ->getJson('/api/projects')
            ->assertUnauthorized();
    });
});
