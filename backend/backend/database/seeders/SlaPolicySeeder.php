<?php

namespace Database\Seeders;

use App\Models\SlaPolicy;
use Illuminate\Database\Seeder;

class SlaPolicySeeder extends Seeder
{
    public function run(): void
    {
        // Global defaults (organization_id = NULL) for each priority
        foreach (SlaPolicy::PRIORITIES as $priority) {
            SlaPolicy::updateOrCreate(
                [
                    'organization_id' => null,
                    'priority'        => $priority,
                ],
                array_merge(
                    ['priority' => $priority],
                    SlaPolicy::DEFAULTS[$priority],
                    [
                        'is_active'           => true,
                        'business_hours_only' => false,
                    ]
                )
            );
        }
    }
}
