<?php

use App\Models\Organization;
use App\Models\SlaPolicy;
use App\Models\Ticket;
use Carbon\Carbon;

beforeEach(function () {
    $this->org = Organization::factory()->create();

    $this->policy = SlaPolicy::factory()->for($this->org)->create([
        'low_response_minutes' => 1440,
        'low_resolution_minutes' => 4320,
        'medium_response_minutes' => 720,
        'medium_resolution_minutes' => 1440,
        'high_response_minutes' => 240,
        'high_resolution_minutes' => 480,
        'urgent_response_minutes' => 60,
        'urgent_resolution_minutes' => 120,
        'is_active' => true,
    ]);
});

it('exposes response_due_at and resolution_due_at accessors', function () {
    Carbon::setTestNow('2025-03-01 10:00:00');

    $ticket = Ticket::factory()
        ->for($this->org)
        ->priority('high')
        ->create(['created_at' => Carbon::parse('2025-03-01 10:00:00')]);

    expect($ticket->response_due_at->toDateTimeString())->toBe('2025-03-01 14:00:00') // +4h
        ->and($ticket->resolution_due_at->toDateTimeString())->toBe('2025-03-01 18:00:00'); // +8h

    Carbon::setTestNow();
});

it('exposes time_remaining as minutes until resolution deadline', function () {
    Carbon::setTestNow('2025-03-01 11:00:00');

    $ticket = Ticket::factory()
        ->for($this->org)
        ->priority('medium') // 1440 min resolution
        ->create(['created_at' => Carbon::parse('2025-03-01 10:00:00')]);

    expect($ticket->time_remaining)->toBe(1380); // 23 hours

    Carbon::setTestNow();
});

it('reports is_sla_breached when resolution deadline is past', function () {
    Carbon::setTestNow('2025-03-05 10:00:00');

    $ticket = Ticket::factory()
        ->for($this->org)
        ->priority('medium')
        ->create(['created_at' => Carbon::parse('2025-03-01 10:00:00')]);

    expect($ticket->is_sla_breached)->toBeTrue();

    Carbon::setTestNow();
});

it('reports is_sla_breached false when within deadline', function () {
    Carbon::setTestNow('2025-03-01 11:00:00');

    $ticket = Ticket::factory()
        ->for($this->org)
        ->priority('medium')
        ->create(['created_at' => Carbon::parse('2025-03-01 10:00:00')]);

    expect($ticket->is_sla_breached)->toBeFalse();

    Carbon::setTestNow();
});

it('returns null SLA fields when no active policy exists for organization', function () {
    $this->policy->update(['is_active' => false]);

    $ticket = Ticket::factory()->for($this->org)->create();

    expect($ticket->response_due_at)->toBeNull()
        ->and($ticket->resolution_due_at)->toBeNull()
        ->and($ticket->time_remaining)->toBeNull();
});

it('never leaks another organizations policy to a ticket', function () {
    $otherOrg = Organization::factory()->create();
    SlaPolicy::factory()->for($otherOrg)->create([
        'medium_response_minutes' => 9999,
        'is_active' => true,
    ]);

    Carbon::setTestNow('2025-03-01 10:00:00');
    $ticket = Ticket::factory()
        ->for($this->org)
        ->priority('medium')
        ->create(['created_at' => Carbon::parse('2025-03-01 10:00:00')]);

    // Uses our org's 720-minute limit, not 9999.
    expect($ticket->response_due_at->toDateTimeString())
        ->toBe('2025-03-01 22:00:00');

    Carbon::setTestNow();
});

it('caches the resolved policy on the ticket instance', function () {
    $ticket = Ticket::factory()->for($this->org)->priority('low')->create();

    $firstCall = $ticket->_cachedSlaPolicy;
    expect($firstCall)->toBeNull(); // not populated until first accessor call

    $due = $ticket->response_due_at;

    expect($ticket->_cachedSlaPolicy)->not->toBeNull()
        ->and($ticket->_cachedSlaPolicy->is($this->policy))->toBeTrue();
});
