<?php

namespace App\Http\Controllers\V1;

use App\Http\Requests\V1\IndexTicketRequest;
use App\Http\Resources\V1\TicketResource;
use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;

class TicketController extends Controller
{
    /**
     * Display a paginated list of tickets with optional filters.
     *
     * Supported filters:
     * - my_tickets: tickets assigned to the authenticated user
     * - unassigned: tickets with no agent assigned
     * - assigned: tickets that have an agent assigned
     */
    public function index(IndexTicketRequest $request): AnonymousResourceCollection
    {
        $query = Ticket::query()->with('agent');

        $filter = $request->validated('filter');
        $userId = $request->user()->id;

        match ($filter) {
            'my_tickets' => $query->where('agent_id', $userId),
            'unassigned' => $query->whereNull('agent_id'),
            'assigned'   => $query->whereNotNull('agent_id'),
            default      => null,
        };

        $tickets = $query
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return TicketResource::collection($tickets);
    }

    /**
     * Store a newly created ticket.
     * Organization ID is always derived from the authenticated user.
     */
    public function store(Request $request): JsonResource
    {
        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'priority' => ['nullable', 'string', 'in:low,medium,high,urgent'],
        ]);

        $ticket = Ticket::create([
            ...$validated,
            'organization_id' => $request->user()->organization_id,
            'status' => $validated['status'] ?? 'open',
            'priority' => $validated['priority'] ?? 'medium',
        ]);

        $ticket->load('agent');

        return new TicketResource($ticket);
    }

    /**
     * Display the specified ticket.
     */
    public function show(Ticket $ticket): JsonResource
    {
        $ticket->load('agent');

        return new TicketResource($ticket);
    }

    /**
     * Update the specified ticket.
     */
    public function update(Request $request, Ticket $ticket): JsonResource
    {
        $this->authorize('update', $ticket);

        $validated = $request->validate([
            'subject' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'string'],
            'status' => ['sometimes', 'string', 'in:open,in_progress,resolved,closed'],
            'priority' => ['sometimes', 'string', 'in:low,medium,high,urgent'],
        ]);

        $ticket->update($validated);
        $ticket->load('agent');

        return new TicketResource($ticket);
    }

    /**
     * Remove the specified ticket.
     */
    public function destroy(Ticket $ticket): \Illuminate\Http\Response
    {
        $this->authorize('delete', $ticket);

        $ticket->delete();

        return response()->noContent();
    }
}
