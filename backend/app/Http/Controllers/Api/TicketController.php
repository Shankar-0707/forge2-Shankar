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
     * Display a listing of the tickets scoped to the authenticated user's organization.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $orgId = $request->user()->organization_id;
        $query = Ticket::where('organization_id', $orgId)->with(['creator', 'assignee']);

        // Search filter
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Status filter
        if ($status = $request->input('status')) {
            if ($status !== 'all') {
                $query->where('status', $status);
            }
        }

        // Priority filter
        if ($priority = $request->input('priority')) {
            if ($priority !== 'all') {
                $query->where('priority', $priority);
            }
        }

        // Assignee filter
        if ($assigneeId = $request->input('assignee_id')) {
            if ($assigneeId === 'unassigned') {
                $query->whereNull('assigned_to');
            } elseif ($assigneeId === 'my_tickets') {
                $query->where('assigned_to', $request->user()->id);
            } elseif ($assigneeId !== 'all') {
                $query->where('assigned_to', $assigneeId);
            }
        }

        $tickets = $query->latest()->paginate(15);

        return TicketResource::collection($tickets);
    }

    /**
     * Store a newly created ticket in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'priority' => ['nullable', 'string', 'in:low,medium,high,urgent'],
        ]);

        $user = $request->user();

        $ticket = Ticket::create([
            'organization_id' => $user->organization_id,
            'created_by' => $user->id,
            'ticket_number' => 'TICK-' . fake()->unique()->numberBetween(1000, 9999),
            'title' => $validated['title'],
            'description' => $validated['description'],
            'status' => Ticket::STATUS_OPEN,
            'priority' => $validated['priority'] ?? Ticket::PRIORITY_NORMAL,
        ]);

        return response()->json(new TicketResource($ticket->load(['creator', 'assignee'])), 201);
    }

    /**
     * Update the specified ticket in storage.
     */
    public function update(Request $request, Ticket $ticket): JsonResponse
    {
        $this->authorizeOrg($ticket);

        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'string'],
            'status' => ['sometimes', 'string', 'in:open,claimed,pending,resolved,closed'],
            'priority' => ['sometimes', 'string', 'in:low,medium,high,urgent'],
        ]);

        $ticket->update($validated);

        return response()->json(new TicketResource($ticket->load(['creator', 'assignee'])));
    }

    /**
     * Remove the specified ticket from storage.
     */
    public function destroy(Ticket $ticket): JsonResponse
    {
        $this->authorizeOrg($ticket);

        $ticket->delete();

        return response()->json(['message' => 'Ticket deleted successfully.']);
    }
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
