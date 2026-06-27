<?php

namespace App\Policies;

use App\Models\Ticket;
use App\Models\User;

class TicketPolicy
{
    /**
     * Determine if the user can view any tickets.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine if the user can view the ticket.
     */
    public function view(User $user, Ticket $ticket): bool
    {
        return $user->organization_id === $ticket->organization_id;
    }

    /**
     * Determine if the user can create tickets.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine if the user can update the ticket.
     */
    public function update(User $user, Ticket $ticket): bool
    {
        return $user->organization_id === $ticket->organization_id
            && $user->isAgentOrAdmin();
    }

    /**
     * Determine if the user can assign the ticket to an agent.
     * Only agents and admins within the same organization.
     */
    public function assign(User $user, Ticket $ticket): bool
    {
        return $user->organization_id === $ticket->organization_id
            && $user->isAgentOrAdmin();
    }

    /**
     * Determine if the user can claim (self-assign) the ticket.
     * Only agents and admins within the same organization.
     */
    public function claim(User $user, Ticket $ticket): bool
    {
        return $user->organization_id === $ticket->organization_id
            && $user->isAgentOrAdmin();
    }

    /**
     * Determine if the user can delete the ticket.
     */
    public function delete(User $user, Ticket $ticket): bool
    {
        return $user->organization_id === $ticket->organization_id
            && $user->isAdmin();
    }
}
