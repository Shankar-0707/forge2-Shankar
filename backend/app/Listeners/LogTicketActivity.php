<?php

namespace App\Listeners;

use App\Events\LogsActivity;
use App\Models\ActivityLog;

/**
 * Single listener registered for every event that implements LogsActivity.
 * Persists an immutable ActivityLog row for audit-trail purposes.
 */
class LogTicketActivity
{
    public function handle(LogsActivity $event): void
    {
        ActivityLog::create([
            'organization_id' => $event->getOrganizationId(),
            'ticket_id'       => $event->getTicketId(),
            'actor_id'        => $event->getActorId(),
            'event'           => $event->getEventName(),
            'metadata'        => $event->getMetadata(),
        ]);
    }
}
