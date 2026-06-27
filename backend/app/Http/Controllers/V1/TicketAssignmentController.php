<?php

namespace App\Http\Controllers\V1;

use App\Http\Requests\V1\AssignTicketRequest;
use App\Http\Resources\V1\TicketResource;
use App\Models\Ticket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Routing\Controller;

class TicketAssignmentController extends Controller
{
    /**
     * Assign (or reassign) a ticket to a specific agent.
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function assign(AssignTicketRequest $request, Ticket $ticket): JsonResource
    {
        $this->authorize('assign', $ticket);

        $ticket->update([
            'agent_id' => $request->input('agent_id'),
        ]);

        $ticket->load('agent');

        return new TicketResource($ticket);
    }

    /**
     * Self-assign (claim) a ticket for the authenticated user.
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function claim(Request $request, Ticket $ticket): JsonResponse|JsonResource
    {
        $this->authorize('claim', $ticket);

        // Prevent claiming a ticket already assigned to another agent.
        if ($ticket->agent_id !== null && $ticket->agent_id !== $request->user()->id) {
            return response()->json([
                'message' => 'This ticket is already assigned to another agent. Use the assign endpoint to reassign.',
                'current_agent_id' => $ticket->agent_id,
            ], 422);
        }

        // If already assigned to self, short-circuit with current state.
        if ($ticket->agent_id === $request->user()->id) {
            $ticket->load('agent');

            return new TicketResource($ticket);
        }

        $ticket->update([
            'agent_id' => $request->user()->id,
        ]);

        $ticket->load('agent');

        return new TicketResource($ticket);
    }
}
