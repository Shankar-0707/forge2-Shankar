<?php

namespace App\Events;

/**
 * Contract that every activity-producing event must implement.
 * The LogTicketActivity listener reads from this interface so a
 * single listener can handle all event types.
 */
interface LogsActivity
{
    public function getTicketId(): int;

    public function getActorId(): ?int;

    public function getEventName(): string;

    /** @return array<string,mixed> */
    public function getMetadata(): array;

    public function getOrganizationId(): int;
}
