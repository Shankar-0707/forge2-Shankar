<?php

use App\Models\Organization;
use App\Models\SlaPolicy;
use App\Models\Ticket;
use App\Services\SlaCalculator;
use Carbon\Carbon;

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->policy = SlaPolicy::factory()->for($this->org)->create([
        'medium_response_minutes' => 120,    // 2 hours
        'medium_resolution_minutes' => 1440, // 1 day
        'urgent_response_minutes' => 30,
        'urgent_resolution_minutes' => 240,
    ]);
    $this->calc = app(SlaCalculator::class);
});

it('resolves the active policy scoped to the ticket organization', function () {
    $otherOrg = Organization::factory()->create();
    SlaPolicy::factory()->for($otherOrg)->create();

    $ticket = Ticket::factory()->for($this->org)->priority('medium')->create();

    expect($this->calc->policyFor($ticket))->toBeInstanceOf(SlaPolicy::class)
        ->and($this->calc->policyFor($ticket)->is($this->policy))->toBeTrue();
});

it('returns null when no active policy exists for the organization', function () {
    $this->policy->delete();
    $ticket = Ticket::factory()->for($this->org)->create();

    expect($this->calc->policyFor($ticket))->toBeNull()
        ->and($this->calc->responseDueAt($ticket))->toBeNull()
        ->and($this->calc->resolutionDueAt($ticket))->toBeNull();
});

it('computes response and resolution deadlines from created_at', function () {
    Carbon::setTestNow('2025-03-01 10:00:00');

    $ticket = Ticket::factory()->for($this->org)->priority('medium')->create([
        'created_at' => Carbon::parse('2025-03-01 10:00:00'),
    ]);

    expect($this->calc->responseDueAt($ticket)->toDateTimeString())
        ->toBe('2025-03-01 12:00:00')
        ->and($this->calc->resolutionDueAt($ticket)->toDateTimeString())
        ->toBe('2025-03-02 10:00:00');

    Carbon::setTestNow();
});

it('applies urgent limits to urgent tickets', function () {
    $ticket = Ticket::factory()->for($this->org)->priority('urgent')->create([
        'created_at' => Carbon::parse('2025-03-01 10:00:00'),
    ]);

    expect($this->calc->responseDueAt($ticket)->toDateTimeString())
        ->toBe('2025-03-01 10:30:00')
        ->and($this->calc->resolutionDueAt($ticket)->toDateTimeString())
        ->toBe('2025-03-01 14:00:00');
});

it('reports positive time remaining before deadline', function () {
    Carbon::setTestNow('2025-03-01 11:00:00'); // 1 hour after create

    $ticket = Ticket::factory()->for($this->org)->priority('medium')->create([
        'created_at' => Carbon::parse('2025-03-01 10:00:00'),
    ]);

    expect($this->calc->resolutionTimeRemaining($ticket))->toBe(1380) // 23h
        ->and($this->calc->responseTimeRemaining($ticket))->toBe(60) // 1h
        ->and($this->calc->isResolutionBreached($ticket))->toBeFalse()
        ->and($this->calc->isResponseBreached($ticket))->toBeFalse();

    Carbon::setTestNow();
});

it('detects resolution breach with negative remaining time', function () {
    Carbon::setTestNow('2025-03-03 10:00:00'); // 2 days after create

    $ticket = Ticket::factory()->for($this->org)->priority('medium')->create([
        'created_at' => Carbon::parse('2025-03-01 10:00:00'),
    ]);

    expect($this->calc->resolutionTimeRemaining($ticket))->toBeLessThan(0)
        ->and($this->calc->isResolutionBreached($ticket))->toBeTrue();
});

it('does not flag response breach when answered in time', function () {
    $ticket = Ticket::factory()->for($this->org)->priority('medium')->create([
        'created_at' => Carbon::parse('2025-03-01 10:00:00'),
        'response_at' => Carbon::parse('2025-03-01 11:00:00'), // 1h after, under 2h limit
    ]);

    expect($this->calc->isResponseBreached($ticket))->toBeFalse();
});

it('flags response breach when answered late', function () {
    $ticket = Ticket::factory()->for($this->org)->priority('medium')->create([
        'created_at' => Carbon::parse('2025-03-01 10:00:00'),
        'response_at' => Carbon::parse('2025-03-01 13:00:00'), // 3h after, over 2h limit
    ]);

    expect($this->calc->isResponseBreached($ticket))->toBeTrue();
});

it('flags resolution breach against resolved_at timestamp', function () {
    $ticket = Ticket::factory()->for($this->org)->priority('medium')->create([
        'created_at' => Carbon::parse('2025-03-01 10:00:00'),
        'resolved_at' => Carbon::parse('2025-03-02 12:00:00'), // 26h, over 24h limit
    ]);

    expect($this->calc->isResolutionBreached($ticket))->toBeTrue();
});

it('falls back to medium priority when priority is unknown', function () {
    $ticket = Ticket::factory()->for($this->org)->create([
        'priority' => 'weird',
        'created_at' => Carbon::parse('2025-03-01 10:00:00'),
    ]);

    // medium_response_minutes = 120
    expect($this->calc->responseDueAt($ticket)->toDateTimeString())
        ->toBe('2025-03-01 12:00:00');
});
