<?php

use App\Models\Organization;
use App\Models\SlaPolicy;
use App\Models\Ticket;

it('resolves global policy when no org-specific policy exists', function () {
    SlaPolicy::factory()->create([
        'organization_id'      => null,
        'priority'             => 'high',
        'response_time_limit'  => 120,
        'resolution_time_limit'=> 1440,
    ]);

    $org = Organization::factory()->create();
    $policy = SlaPolicy::resolveFor('high', $org->id);

    expect($policy)
        ->not->toBeNull()
        ->priority->toBe('high')
        ->organization_id->toBeNull();
});

it('prefers org-specific policy over global', function () {
    $org = Organization::factory()->create();

    SlaPolicy::factory()->create([
        'organization_id'      => null,
        'priority'             => 'urgent',
        'response_time_limit'  => 30,
        'resolution_time_limit'=> 240,
    ]);

    SlaPolicy::factory()->create([
        'organization_id'      => $org->id,
        'priority'             => 'urgent',
        'response_time_limit'  => 15,
        'resolution_time_limit'=> 120,
    ]);

    $policy = SlaPolicy::resolveFor('urgent', $org->id);

    expect($policy)
        ->organization_id->toBe($org->id)
        ->response_time_limit->toBe(15)
        ->resolution_time_limit->toBe(120);
});

it('returns null for inactive policies', function () {
    SlaPolicy::factory()->create([
        'organization_id' => null,
        'priority'        => 'low',
        'is_active'       => false,
    ]);

    expect(SlaPolicy::resolveFor('low'))->toBeNull();
});

it('respects organization scoping on tickets', function () {
    $orgA = Organization::factory()->create();
    $orgB = Organization::factory()->create();

    $ticketA = Ticket::factory()->create([
        'organization_id' => $orgA->id,
        'subject'         => 'Org A ticket',
    ]);
    $ticketB = Ticket::factory()->create([
        'organization_id' => $orgB->id,
        'subject'         => 'Org B ticket',
    ]);

    $scoped = Ticket::forOrganization($orgA->id)->get();

    expect($scoped)
        ->toHaveCount(1)
        ->and($scoped->first()->id)->toBe($ticketA->id);
});
