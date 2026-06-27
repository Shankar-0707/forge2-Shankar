<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class ProjectFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => $this->faker->catchPhrase(),
            'description' => $this->faker->optional()->paragraph(),
            'organization_id' => null,
        ];
    }

    public function forOrganization($orgId): static
    {
        return $this->state(fn () => [
            'organization_id' => $orgId,
        ]);
    }
}
