<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ReassignTicketRequest;
use App\Http\Resources\TicketResource;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TicketController extends Controller
{
    /**
     * Display the specified ticket, scoped to the auth user's organization.
     */
    public function show(Ticket $ticket): JsonResponse
    {
        $this->authorizeOrg($ticket);

        return response()->json(new TicketResource($ticket->load(['creator', 'assignee'])));
    }

    /**
     * Claim an unassigned (or re-claim) ticket for the current user.
     */
    public function claim(Request $request, Ticket $ticket): JsonResponse
    {
        $this->authorizeOrg($ticket);

        /** @var User $user */
        $user = $request->user();

        if (! $user->canManageTickets()) {
            return response()->json([
                'message' => 'You do not have permission to claim tickets.',
            ], 403);
        }

        $wasFirstResponse = $ticket->first_response_at === null;

        $ticket->update([
            'assigned_to' => $user->id,
            'status' => Ticket::STATUS_CLAIMED,
            'first_response_at' => $ticket->first_response_at ?? now(),
        ]);

        return response()->json([
            'message' => 'Ticket claimed successfully.',
            'ticket' => new TicketResource($ticket->fresh(['creator', 'assignee'])),
            'first_response_recorded' => $wasFirstResponse,
        ]);
    }

    /**
     * Reassign a ticket to another user within the same organization.
     */
    public function reassign(ReassignTicketRequest $request, Ticket $ticket): JsonResponse
    {
        $this->authorizeOrg($ticket);

        /** @var User $user */
        $user = $request->user();

        if (! $user->canManageTickets()) {
            return response()->json([
                'message' => 'You do not have permission to reassign tickets.',
            ], 403);
        }

        $targetUserId = $request->validated('user_id');

        // Defensive double-check: target must be in the same org
        $targetUser = User::where('id', $targetUserId)
            ->where('organization_id', $user->organization_id)
            ->first();

        if (! $targetUser) {
            return response()->json([
                'message' => 'The selected user is not available in your organization.',
            ], 422);
        }

        $wasFirstResponse = $ticket->first_response_at === null;

        $ticket->update([
            'assigned_to' => $targetUser->id,
            'status' => Ticket::STATUS_CLAIMED,
            'first_response_at' => $ticket->first_response_at ?? now(),
        ]);

        return response()->json([
            'message' => "Ticket reassigned to {$targetUser->name}.",
            'ticket' => new TicketResource($ticket->fresh(['creator', 'assignee'])),
            'first_response_recorded' => $wasFirstResponse,
        ]);
    }

    /**
     * List assignable team members for the reassign dropdown.
     */
    public function assignableUsers(Request $request): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();

        $users = User::where('organization_id', $user->organization_id)
            ->whereIn('role', [User::ROLE_ADMIN, User::ROLE_AGENT])
            ->orderBy('name')
            ->get();

        return \App\Http\Resources\UserResource::collection($users);
    }

    /**
     * Ensure the ticket belongs to the auth user's organization.
     * Never trust organization_id from the request.
     */
    private function authorizeOrg(Ticket $ticket): void
    {
        $orgId = auth()->user()->organization_id;

        abort_unless($ticket->organization_id === $orgId, 404);
    }
}
