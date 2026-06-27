<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SlaPolicy extends Model
{
    use HasFactory;

    public const PRIORITIES = ['low', 'medium', 'high', 'urgent'];
    public const DEFAULT_PRIORITY = 'medium';

    protected $fillable = [
        'organization_id',
        'name',
        'description',
        'low_response_minutes',
        'medium_response_minutes',
        'high_response_minutes',
        'urgent_response_minutes',
        'low_resolution_minutes',
        'medium_resolution_minutes',
        'high_resolution_minutes',
        'urgent_resolution_minutes',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'low_response_minutes' => 'integer',
        'medium_response_minutes' => 'integer',
        'high_response_minutes' => 'integer',
        'urgent_response_minutes' => 'integer',
        'low_resolution_minutes' => 'integer',
        'medium_resolution_minutes' => 'integer',
        'high_resolution_minutes' => 'integer',
        'urgent_resolution_minutes' => 'integer',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForOrg(Builder $query, int $organizationId): Builder
    {
        return $query->where('organization_id', $organizationId);
    }

    /**
     * Normalize a priority string against the allowed values.
     */
    public static function normalizePriority(?string $priority): string
    {
        $priority = strtolower((string) $priority);

        return in_array($priority, self::PRIORITIES, true)
            ? $priority
            : self::DEFAULT_PRIORITY;
    }

    /**
     * Get the response time limit (minutes) for a given priority.
     */
    public function responseMinutesFor(string $priority): int
    {
        $priority = self::normalizePriority($priority);

        return (int) $this->getAttribute("{$priority}_response_minutes");
    }

    /**
     * Get the resolution time limit (minutes) for a given priority.
     */
    public function resolutionMinutesFor(string $priority): int
    {
        $priority = self::normalizePriority($priority);

        return (int) $this->getAttribute("{$priority}_resolution_minutes");
    }
}
