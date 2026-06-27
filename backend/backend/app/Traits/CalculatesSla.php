<?php

namespace App\Traits;

use App\Models\SlaPolicy;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Trait CalculatesSla
 *
 * Provides SLA computation helpers to the Ticket model.
 * Computes elapsed time excluding periods where the ticket
 * is "on hold" (status = on_hold).
 *
 * Remaining time is computed as:
 *   limit_minutes - elapsed_minutes
 * and clamped at 0 when breached.
 */
trait CalculatesSla
{
    /**
     * Get the SLA policy applicable to this ticket.
     */
    public function slaPolicy(): ?SlaPolicy
    {
        return SlaPolicy::resolveFor(
            $this->priority,
            $this->organization_id
        );
    }

    /**
     * Total seconds the ticket has spent "active" (not on hold).
     */
    public function slaElapsedSeconds(): int
    {
        $created = $this->created_at;
        if (!$created) {
            return 0;
        }

        // If closed/resolved, freeze clock at resolved time
        $until = $this->isResolved()
            ? Carbon::parse($this->resolved_at ?? $this->updated_at)
            : now();

        // Simple approximation: total minus time spent on hold.
        // For production, replace with precise interval math against
        // status history records.
        $onHoldSeconds = $this->getOnHoldDurationSeconds($created, $until);

        return max(0, $created->diffInSeconds($until) - $onHoldSeconds);
    }

    /**
     * Get seconds spent on hold. Override in model to read from
     * status history table for precision.
     */
    protected function getOnHoldDurationSeconds(
        CarbonInterface $from,
        CarbonInterface $to
    ): int {
        return 0; // Placeholder: integrate with TicketStatusHistory later
    }

    public function responseTimeRemainingAttribute(): int
    {
        $policy = $this->slaPolicy();
        if (!$policy) {
            return 0;
        }

        $limitSeconds = $policy->response_time_limit * 60;

        // If already responded, no further response SLA applies
        if ($this->first_response_at) {
            return max(0, $limitSeconds - $this->created_at->diffInSeconds(
                Carbon::parse($this->first_response_at)
            ));
        }

        return max(0, $limitSeconds - $this->slaElapsedSeconds());
    }

    public function resolutionTimeRemainingAttribute(): int
    {
        $policy = $this->slaPolicy();
        if (!$policy) {
            return 0;
        }

        $limitSeconds = $policy->resolution_time_limit * 60;
        return max(0, $limitSeconds - $this->slaElapsedSeconds());
    }

    public function responseSlaBreachedAttribute(): bool
    {
        if (!$this->slaPolicy()) {
            return false;
        }
        if ($this->first_response_at) {
            return $this->created_at->diffInMinutes(
                Carbon::parse($this->first_response_at)
            ) > $this->slaPolicy()->response_time_limit;
        }
        return $this->response_time_remaining === 0;
    }

    public function resolutionSlaBreachedAttribute(): bool
    {
        if (!$this->slaPolicy()) {
            return false;
        }
        if ($this->isResolved()) {
            return $this->slaElapsedSeconds() >
                ($this->slaPolicy()->resolution_time_limit * 60);
        }
        return $this->resolution_time_remaining === 0;
    }

    /**
     * Formatted human-readable remaining time (e.g. "2h 15m").
     */
    public function formatSlaDuration(int $seconds): string
    {
        if ($seconds <= 0) {
            return '00:00';
        }

        $hours   = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);

        return $hours > 0
            ? sprintf('%dh %dm', $hours, $minutes)
            : sprintf('%dm', $minutes);
    }
}
