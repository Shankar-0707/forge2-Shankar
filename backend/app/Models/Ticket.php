<?php

namespace App\Models;

use App\Services\SlaCalculator;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Ticket extends Model
{
    use HasFactory;

    public const PRIORITIES = ['low', 'medium', 'high', 'urgent'];

    protected $fillable = [
        'organization_id',
        'requester_id',
        'assignee_id',
        'subject',
        'description',
        'status',
        'priority',
        'response_at',
        'resolved_at',
    ];

    protected $casts = [
        'response_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    /**
     * Runtime cache for the resolved SLA policy. Not persisted.
     * Populated by SlaCalculator::policyFor().
     *
     * @var SlaPolicy|null
     */
    public $_cachedSlaPolicy = null;

    // ---------- Relationships ----------

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    // ---------- Scopes ----------

    public function scopeForOrg(Builder $query, int $organizationId): Builder
    {
        return $query->where('organization_id', $organizationId);
    }

    // ---------- SLA accessors ----------

    protected function slaCalculator(): SlaCalculator
    {
        return app(SlaCalculator::class);
    }

    /**
     * Response SLA deadline = created_at + response_minutes(priority).
     */
    public function getResponseDueAtAttribute(): ?Carbon
    {
        return $this->slaCalculator()->responseDueAt($this);
    }

    /**
     * Resolution SLA deadline = created_at + resolution_minutes(priority).
     */
    public function getResolutionDueAtAttribute(): ?Carbon
    {
        return $this->slaCalculator()->resolutionDueAt($this);
    }

    /**
     * Headline SLA clock: minutes remaining until resolution SLA breach.
     * Negative => already breached. null => no SLA policy applies.
     */
    public function getTimeRemainingAttribute(): ?int
    {
        return $this->slaCalculator()->resolutionTimeRemaining($this);
    }

    /**
     * Convenience: minutes remaining until response SLA breach.
     */
    public function getResponseTimeRemainingAttribute(): ?int
    {
        return $this->slaCalculator()->responseTimeRemaining($this);
    }

    /**
     * Whether the resolution SLA has been breached.
     */
    public function getIsSlaBreachedAttribute(): bool
    {
        return $this->slaCalculator()->isResolutionBreached($this);
    }

    /**
     * Whether the response SLA has been breached.
     */
    public function getIsResponseBreachedAttribute(): bool
    {
        return $this->slaCalculator()->isResponseBreached($this);
    }

    /**
     * Normalized priority for safe arithmetic (never null/invalid).
     */
    public function getNormalizedPriorityAttribute(): string
    {
        return SlaPolicy::normalizePriority($this->priority);
    }

    /**
     * Allow injection of a fixed "now" for testing accessors deterministically.
     * Usage: $ticket->setTestNow(Carbon::parse('...')).
     */
    public function setTestNow(?CarbonInterface $now): static
    {
        $this->_testNow = $now;

        return $this;
    }

    public function getTestNow(): ?CarbonInterface
    {
        return $this->_testNow ?? null;
    }

    private ?CarbonInterface $_testNow = null;
}
