<?php

namespace App\Events;

use App\Models\ActivityLog;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TicketStatusChanged implements LogsActivity
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Ticket $ticket,
        public ?User $actor = null,
        public ?string $oldStatus = null,
        public ?string $newStatus = null,
    ) {}

    public function getTicketId(): int
    {
        return $this->ticket->id;
    }

    public function getActorId(): ?int
    {
        return $this->actor?->id;
    }

    public function getEventName(): string
    {
        return ActivityLog::EVENT_STATUS_CHANGED;
    }

    public function getMetadata(): array
    {
        return [
            'from' => $this->oldStatus,
            'to'   => $this->newStatus,
        ];
    }

    public function getOrganizationId(): int
    {
        return $this->ticket->organization_id;
    }
}
