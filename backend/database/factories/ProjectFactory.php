<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Project> */
class ProjectFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'description' => fake()->optional()->sentence(),
            'status' => fake()->randomElement(['active', 'archived', 'on_hold']),
            'color' => fake()->hexColor(),
            'due_date' => fake()->optional()->dateTimeBetween('now', '+3 months'),
        ];
    }

    /**
     * Indicate the project is active.
     */
    public function active(): static
    {
        return $this->state(fn () => ['status' => 'active']);
    }

    /**
     * Indicate the project is archived.
     */
    public function archived(): static
    {
        return $this->state(fn () => ['status' => 'archived']);
    }
}
