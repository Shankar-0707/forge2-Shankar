<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

class Ticket extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'organization_id',
        'agent_id',
        'subject',
        'description',
        'status',
        'priority',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /*
    |--------------------------------------------------------------------------
    | Boot — Global Organization Scope
    |--------------------------------------------------------------------------
    */
    protected static function booted(): void
    {
        static::addGlobalScope('organization', function (Builder $builder) {
            if (Auth::check()) {
                $builder->where('tickets.organization_id', Auth::user()->organization_id);
            }
        });

        static::creating(function (Ticket $ticket) {
            if (Auth::check() && ! $ticket->organization_id) {
                $ticket->organization_id = Auth::user()->organization_id;
            }
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Query Scopes / Filters
    |--------------------------------------------------------------------------
    */
    public function scopeAssignedTo(Builder $query, int $userId): Builder
    {
        return $query->where('agent_id', $userId);
    }

    public function scopeMyTickets(Builder $query, ?int $userId = null): Builder
    {
        $userId ??= Auth::id();

        return $query->where('agent_id', $userId);
    }

    public function scopeUnassigned(Builder $query): Builder
    {
        return $query->whereNull('agent_id');
    }

    public function scopeAssigned(Builder $query): Builder
    {
        return $query->whereNotNull('agent_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */
    public function isAssigned(): bool
    {
        return $this->agent_id !== null;
    }

    public function isAssignedTo(User $user): bool
    {
        return $this->agent_id === $user->id;
    }

    public function isUnassigned(): bool
    {
        return $this->agent_id === null;
    }
}
