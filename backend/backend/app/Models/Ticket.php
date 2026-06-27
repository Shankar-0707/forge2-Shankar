<?php

namespace App\Models;

use App\Traits\CalculatesSla;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Ticket extends Model
{
    use HasFactory;
    use CalculatesSla;

    protected $fillable = [
        'organization_id',
        'subject',
        'description',
        'status',
        'priority',
        'requester_id',
        'assignee_id',
        'team_id',
        'first_response_at',
        'resolved_at',
    ];

    protected $casts = [
        'first_response_at' => 'datetime',
        'resolved_at'       => 'datetime',
    ];

    public const STATUSES   = ['open', 'pending', 'on_hold', 'resolved', 'closed'];
    public const PRIORITIES = ['low', 'medium', 'high', 'urgent'];

    // ── Relationships ───────────────────────────────────────────────

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

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(TicketMessage::class);
    }

    // ── SLA Accessors (exposed by resource) ─────────────────────────

    public function getResponseTimeRemainingAttribute(): int
    {
        return $this->responseTimeRemainingAttribute();
    }

    public function getResolutionTimeRemainingAttribute(): int
    {
        return $this->resolutionTimeRemainingAttribute();
    }

    public function getResponseSlaBreachedAttribute(): bool
    {
        return $this->responseSlaBreachedAttribute();
    }

    public function getResolutionSlaBreachedAttribute(): bool
    {
        return $this->resolutionSlaBreachedAttribute();
    }

    public function getResponseTimeRemainingFormattedAttribute(): string
    {
        return $this->formatSlaDuration($this->response_time_remaining);
    }

    public function getResolutionTimeRemainingFormattedAttribute(): string
    {
        return $this->formatSlaDuration($this->resolution_time_remaining);
    }

    // ── Helpers ─────────────────────────────────────────────────────

    public function isResolved(): bool
    {
        return in_array($this->status, ['resolved', 'closed'], true);
    }

    public function markResponded(): void
    {
        if (!$this->first_response_at) {
            $this->forceFill(['first_response_at' => now()])->save();
        }
    }

    public function markResolved(): void
    {
        if (!$this->isResolved()) {
            $this->forceFill([
                'status'       => 'resolved',
                'resolved_at'  => now(),
            ])->save();
        }
    }

    // ── Scopes ──────────────────────────────────────────────────────

    public function scopeForOrganization(Builder $q, int $orgId): Builder
    {
        return $q->where('organization_id', $orgId);
    }

    public function scopeBreaching(Builder $q): Builder
    {
        return $q->whereIn('status', ['open', 'pending', 'on_hold'])
                 ->whereNull('resolved_at');
    }

    public function scopeByPriority(Builder $q, string $priority): Builder
    {
        return $q->where('priority', $priority);
    }
}
