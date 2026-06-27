<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'remember_token' => Str::random(10),
            'organization_id' => null,
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn () => [
            'email_verified_at' => null,
        ]);
    }

    public function forOrganization($orgId): static
    {
        return $this->state(fn () => [
            'organization_id' => $orgId,
        ]);
    }

    public function withPassword(string $password): static
    {
        return $this->state(fn () => [
            'password' => Hash::make($password),
        ]);
    }
}
