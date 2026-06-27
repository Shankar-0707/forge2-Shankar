<?php

namespace App\Services;

use App\Models\SlaPolicy;
use App\Models\Ticket;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class SlaCalculator
{
    /**
     * Resolve the active SLA policy governing a ticket.
     * Always scopes by the ticket's own organization_id (safe in queued context).
     */
    public function policyFor(Ticket $ticket): ?SlaPolicy
    {
        // Use a runtime cache on the ticket instance to avoid duplicate queries.
        return $ticket->_cachedSlaPolicy ??= SlaPolicy::query()
            ->forOrg($ticket->organization_id)
            ->active()
            ->latest('updated_at')
            ->first();
    }

    /**
     * When the response SLA clock expires for this ticket.
     */
    public function responseDueAt(Ticket $ticket): ?Carbon
    {
        $policy = $this->policyFor($ticket);
        if (! $policy) {
            return null;
        }

        return $ticket->created_at
            ->copy()
            ->addMinutes($policy->responseMinutesFor($ticket->priority));
    }

    /**
     * When the resolution SLA clock expires for this ticket.
     */
    public function resolutionDueAt(Ticket $ticket): ?Carbon
    {
        $policy = $this->policyFor($ticket);
        if (! $policy) {
            return null;
        }

        return $ticket->created_at
            ->copy()
            ->addMinutes($policy->resolutionMinutesFor($ticket->priority));
    }

    /**
     * Minutes remaining until the response SLA is breached.
     * Negative value means the SLA is already breached.
     */
    public function responseTimeRemaining(Ticket $ticket, ?CarbonInterface $now = null): ?int
    {
        $due = $this->responseDueAt($ticket);
        if (! $due) {
            return null;
        }

        $now ??= Carbon::now();
        $reference = $ticket->response_at ?? $now;

        // If already responded, no clock is running.
        if ($ticket->response_at) {
            return max(0, (int) $due->diffInMinutes($ticket->response_at, false));
        }

        return (int) $now->diffInMinutes($due, false);
    }

    /**
     * Minutes remaining until the resolution SLA is breached.
     * Negative value means the SLA is already breached.
     */
    public function resolutionTimeRemaining(Ticket $ticket, ?CarbonInterface $now = null): ?int
    {
        $due = $this->resolutionDueAt($ticket);
        if (! $due) {
            return null;
        }

        $now ??= Carbon::now();
        $reference = $ticket->resolved_at ?? $now;

        return (int) $now->diffInMinutes($due, false);
    }

    public function isResponseBreached(Ticket $ticket, ?CarbonInterface $now = null): bool
    {
        if ($ticket->response_at) {
            // Already answered: was it answered late?
            return $this->responseDueAt($ticket)?->isBefore($ticket->response_at) ?? false;
        }

        $remaining = $this->responseTimeRemaining($ticket, $now);

        return $remaining !== null && $remaining < 0;
    }

    public function isResolutionBreached(Ticket $ticket, ?CarbonInterface $now = null): bool
    {
        if ($ticket->resolved_at) {
            return $this->resolutionDueAt($ticket)?->isBefore($ticket->resolved_at) ?? false;
        }

        $remaining = $this->resolutionTimeRemaining($ticket, $now);

        return $remaining !== null && $remaining < 0;
    }
}
