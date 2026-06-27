<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class SlaPolicy extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'priority',
        'response_time_limit',
        'resolution_time_limit',
        'is_active',
        'business_hours_only',
    ];

    protected $casts = [
        'response_time_limit'    => 'integer',
        'resolution_time_limit'  => 'integer',
        'is_active'              => 'boolean',
        'business_hours_only'    => 'boolean',
    ];

    public const PRIORITIES = ['low', 'medium', 'high', 'urgent'];

    /**
     * Default SLA limits (in minutes) for each priority.
     * Used by seeder when no organization-specific policy exists.
     */
    public const DEFAULTS = [
        'low' => [
            'response_time_limit'   => 24 * 60,   // 24h
            'resolution_time_limit' => 7 * 24 * 60, // 7d
        ],
        'medium' => [
            'response_time_limit'   => 8 * 60,    // 8h
            'resolution_time_limit' => 3 * 24 * 60, // 3d
        ],
        'high' => [
            'response_time_limit'   => 2 * 60,    // 2h
            'resolution_time_limit' => 24 * 60,   // 24h
        ],
        'urgent' => [
            'response_time_limit'   => 30,        // 30m
            'resolution_time_limit' => 4 * 60,    // 4h
        ],
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Resolve the applicable SLA policy for a given priority & org.
     * Falls back to global (organization_id IS NULL) if org-specific missing.
     */
    public static function resolveFor(
        string $priority,
        ?int $organizationId = null
    ): ?self {
        return static::query()
            ->where('priority', $priority)
            ->where('is_active', true)
            ->when($organizationId, function (Builder $q) use ($organizationId) {
                $q->where(function (Builder $sub) use ($organizationId) {
                    $sub->where('organization_id', $organizationId)
                        ->orWhereNull('organization_id');
                });
            }, fn(Builder $q) => $q->whereNull('organization_id'))
            ->orderByRaw('organization_id IS NULL') // org-specific first
            ->first();
    }

    public function scopeForPriority(Builder $q, string $priority): Builder
    {
        return $q->where('priority', $priority);
    }

    public function scopeForOrganization(Builder $q, ?int $orgId): Builder
    {
        return $orgId
            ? $q->where('organization_id', $orgId)
            : $q->whereNull('organization_id');
    }
}
