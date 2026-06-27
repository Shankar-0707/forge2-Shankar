<?php

use App\Models\Organization;
use App\Models\User;

describe('Register Endpoint', function () {
    it('registers a new organization and user successfully', function () {
        $response = $this->postJson('/api/v1/auth/register', [
            'organization_name' => 'Acme Corp',
            'name' => 'John Doe',
            'email' => 'john@acme.com',
            'password' => 'SecurePassword123!',
            'password_confirmation' => 'SecurePassword123!',
        ]);

        $response->assertCreated()
            ->assertJsonStructure([
                'message',
                'user' => ['id', 'name', 'email', 'role', 'organization' => ['id', 'name', 'slug']],
                'token',
            ]);

        expect(Organization::where('name', 'Acme Corp')->exists())->toBeTrue();
        expect(User::where('email', 'john@acme.com')->exists())->toBeTrue();

        $user = User::where('email', 'john@acme.com')->first();
        expect($user->organization_id)->not->toBeNull();
        expect($user->role)->toBe('admin');
    });

    it('requires all fields', function () {
        $this->postJson('/api/v1/auth/register', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['organization_name', 'name', 'email', 'password']);
    });

    it('rejects duplicate email', function () {
        $existing = User::factory()->create(['email' => 'taken@example.com']);

        $this->postJson('/api/v1/auth/register', [
            'organization_name' => 'New Corp',
            'name' => 'Jane Doe',
            'email' => 'taken@example.com',
            'password' => 'SecurePassword123!',
            'password_confirmation' => 'SecurePassword123!',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    });

    it('rejects weak passwords', function () {
        $this->postJson('/api/v1/auth/register', [
            'organization_name' => 'Acme Corp',
            'name' => 'John Doe',
            'email' => 'john@acme.com',
            'password' => '123',
            'password_confirmation' => '123',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    });

    it('rejects mismatched password confirmation', function () {
        $this->postJson('/api/v1/auth/register', [
            'organization_name' => 'Acme Corp',
            'name' => 'John Doe',
            'email' => 'john@acme.com',
            'password' => 'SecurePassword123!',
            'password_confirmation' => 'DifferentPassword123!',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    });
});
