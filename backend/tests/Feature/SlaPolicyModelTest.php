<?php

use App\Models\Organization;
use App\Models\SlaPolicy;

it('scopes active policies correctly', function () {
    $org = Organization::factory()->create();
    SlaPolicy::factory()->for($org)->inactive()->create();
    $active = SlaPolicy::factory()->for($org)->create(['is_active' => true]);

    $results = SlaPolicy::forOrg($org->id)->active()->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->is($active))->toBeTrue();
});

it('normalizes unknown priorities to medium', function () {
    expect(SlaPolicy::normalizePriority('low'))->toBe('low')
        ->and(SlaPolicy::normalizePriority('weird'))->toBe('medium')
        ->and(SlaPolicy::normalizePriority(null))->toBe('medium')
        ->and(SlaPolicy::normalizePriority('URGENT'))->toBe('urgent');
});

it('returns correct response/resolution minutes per priority', function () {
    $org = Organization::factory()->create();
    $policy = SlaPolicy::factory()->for($org)->create([
        'urgent_response_minutes' => 30,
        'urgent_resolution_minutes' => 90,
    ]);

    expect($policy->responseMinutesFor('urgent'))->toBe(30)
        ->and($policy->resolutionMinutesFor('urgent'))->toBe(90)
        ->and($policy->responseMinutesFor('bogus'))->toBe($policy->medium_response_minutes);
});

it('cascades deletion with organization', function () {
    $org = Organization::factory()->create();
    $policy = SlaPolicy::factory()->for($org)->create();

    $org->delete();

    expect(SlaPolicy::whereKey($policy->id)->exists())->toBeFalse();
});
