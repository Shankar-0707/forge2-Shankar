<?php

namespace App\Models;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ticket extends Model
{
    protected $fillable = [
        'subject',
        'description',
        'status',
        'priority',
        'requester_id',
        'assignee_id',
        'tags',
    ];

    protected $casts = [
        'status' => TicketStatus::class,
        'priority' => TicketPriority::class,
        'tags' => 'array',
    ];

    /*
    |------------------------------------------------------------------
    | Boot - Global Organization Scope & Auto-Assignment
    |------------------------------------------------------------------
    */

    protected static function booted(): void
    {
        // Auto-set organization_id from authenticated user — never from request input
        static::creating(function (self $ticket) {
            if (auth()->check() && ! $ticket->organization_id) {
                $ticket->organization_id = auth()->user()->organization_id;
            }
        });
    }

    /*
    |------------------------------------------------------------------
    | Scopes
    |------------------------------------------------------------------
    */

    /**
     * Bypass the global organization scope (use sparingly).
     */
    public function scopeWithoutOrganization(Builder $query): Builder
    {
        return $query->withoutGlobalScope('organization');
    }

    /*
    |------------------------------------------------------------------
    | Relationships
    |------------------------------------------------------------------
    */

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

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class)->orderBy('created_at');
    }

    public function publicComments(): HasMany
    {
        return $this->hasMany(Comment::class)->where('is_internal', false)->orderBy('created_at');
    }

    public function internalComments(): HasMany
    {
        return $this->hasMany(Comment::class)->where('is_internal', true)->orderBy('created_at');
    }
}
