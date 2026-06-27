<?php

namespace App\Models;

use App\Events\TicketAssigned;
use App\Events\TicketCreated;
use App\Events\TicketStatusChanged;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;

class Ticket extends Model
{
    use HasFactory;

    public const STATUS_OPEN        = 'open';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_RESOLVED    = 'resolved';
    public const STATUS_CLOSED      = 'closed';

    protected $fillable = [
        'organization_id',
        'title',
        'description',
        'status',
        'priority',
        'assigned_to',
        'created_by',
    ];

    /*
    |--------------------------------------------------------------------------
    | Model lifecycle — dispatch activity events
    |--------------------------------------------------------------------------
    */

    protected static function booted(): void
    {
        static::created(function (Ticket $ticket) {
            TicketCreated::dispatch($ticket, Auth::user());
        });

        static::updated(function (Ticket $ticket) {
            if ($ticket->wasChanged('status')) {
                TicketStatusChanged::dispatch(
                    $ticket,
                    Auth::user(),
                    $ticket->getOriginal('status'),
                    $ticket->status,
                );
            }

            if ($ticket->wasChanged('assigned_to')) {
                TicketAssigned::dispatch(
                    $ticket,
                    Auth::user(),
                    $ticket->getOriginal('assigned_to')
                        ? (int) $ticket->getOriginal('assigned_to')
                        : null,
                    $ticket->assigned_to
                        ? (int) $ticket->assigned_to
                        : null,
                );
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

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class)->latest();
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeForOrganization(Builder $query, int $organizationId): Builder
    {
        return $query->where('organization_id', $organizationId);
    }
}
