<?php

use App\Models\User;

describe('Logout Endpoint', function () {
    it('logs out an authenticated user', function () {
        $user = User::factory()->create();
        $token = $user->createToken('auth-token')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/v1/auth/logout')
            ->assertOk()
            ->assertJson(['message' => 'Logged out successfully.']);

        expect($user->fresh()->tokens)->toBeEmpty();
    });

    it('rejects unauthenticated requests', function () {
        $this->postJson('/api/v1/auth/logout')
            ->assertUnauthorized();
    });
});
