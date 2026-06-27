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
            'organization_id'  => null, // Always set explicitly or via OrganizationFactory
            'name'             => fake()->name(),
            'email'            => fake()->unique()->safeEmail(),
            'role'             => 'agent',
            'avatar_url'       => null,
            'email_verified_at'=> now(),
            'password'         => Hash::make('password'),
            'is_active'        => true,
            'preferences'      => null,
            'remember_token'   => Str::random(10),
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'admin',
        ]);
    }

    public function manager(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'manager',
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
