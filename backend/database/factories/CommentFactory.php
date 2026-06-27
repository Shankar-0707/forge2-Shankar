<?php

namespace Database\Factories;

use App\Models\Comment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Comment>
 */
class CommentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * ticket_id and user_id must be supplied by the caller so that comments
     * always belong to a real ticket and a real (organization-scoped) author.
     */
    public function definition(): array
    {
        return [
            'body'        => fake()->paragraph(fake()->numberBetween(1, 4), true),
            'is_internal' => false,
        ];
    }

    public function internal(): static
    {
        return $this->state(fn (array $attributes) => ['is_internal' => true]);
    }

    public function public(): static
    {
        return $this->state(fn (array $attributes) => ['is_internal' => false]);
    }
}
