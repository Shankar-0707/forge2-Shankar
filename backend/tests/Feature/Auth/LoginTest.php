<?php

use App\Models\User;

describe('Login Endpoint', function () {
    it('logs in a user with valid credentials', function () {
        $user = User::factory()->create([
            'email' => 'john@acme.com',
            'password' => bcrypt('SecurePassword123!'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'john@acme.com',
            'password' => 'SecurePassword123!',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'user' => ['id', 'name', 'email', 'role', 'organization'],
                'token',
            ]);

        expect($response->json('token'))->not->toBeEmpty();
    });

    it('rejects invalid email', function () {
        User::factory()->create([
            'email' => 'john@acme.com',
            'password' => bcrypt('SecurePassword123!'),
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'wrong@acme.com',
            'password' => 'SecurePassword123!',
        ])->assertUnauthorized()
            ->assertJson(['message' => 'Invalid credentials.']);
    });

    it('rejects invalid password', function () {
        User::factory()->create([
            'email' => 'john@acme.com',
            'password' => bcrypt('SecurePassword123!'),
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'john@acme.com',
            'password' => 'WrongPassword123!',
        ])->assertUnauthorized()
            ->assertJson(['message' => 'Invalid credentials.']);
    });

    it('requires email and password', function () {
        $this->postJson('/api/v1/auth/login', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password']);
    });
});
