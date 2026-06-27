<?php

namespace App\Events;

use App\Models\ActivityLog;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CommentAdded implements LogsActivity
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Ticket $ticket,
        public ?User $actor = null,
        public ?int $commentId = null,
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
        return ActivityLog::EVENT_COMMENTED;
    }

    public function getMetadata(): array
    {
        return [
            'comment_id' => $this->commentId,
        ];
    }

    public function getOrganizationId(): int
    {
        return $this->ticket->organization_id;
    }
}
