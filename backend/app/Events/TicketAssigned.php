<?php

namespace App\Events;

use App\Models\ActivityLog;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TicketAssigned implements LogsActivity
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Ticket $ticket,
        public ?User $actor = null,
        public ?int $oldAssigneeId = null,
        public ?int $newAssigneeId = null,
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
        return ActivityLog::EVENT_ASSIGNED;
    }

    public function getMetadata(): array
    {
        return [
            'from' => $this->oldAssigneeId,
            'to'   => $this->newAssigneeId,
        ];
    }

    public function getOrganizationId(): int
    {
        return $this->ticket->organization_id;
    }
}
