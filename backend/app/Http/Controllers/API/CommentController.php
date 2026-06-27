<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCommentRequest;
use App\Http\Resources\CommentResource;
use App\Models\Ticket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CommentController extends Controller
{
    /**
     * List comments for a ticket.
     * Internal notes are hidden from customers via the visibleTo scope.
     */
    public function index(Ticket $ticket): AnonymousResourceCollection
    {
        $this->authorizeTicketAccess($ticket);

        $comments = $ticket->comments()
            ->visibleTo(auth()->user())
            ->with('user')
            ->orderBy('created_at')
            ->get();

        return CommentResource::collection($comments);
    }

    /**
     * Store a new comment or internal note.
     * Only staff (admins/agents) can create internal notes.
     * Customers attempting is_internal=true silently get a public reply.
     */
    public function store(StoreCommentRequest $request, Ticket $ticket): JsonResponse
    {
        $this->authorizeTicketAccess($ticket);

        $validated = $request->validated();
        $user = auth()->user();

        // Only staff can create internal notes — never trust client input for this flag
        $isInternal = ($validated['is_internal'] ?? false) && $user->isStaff();

        $comment = $ticket->comments()->create([
            'user_id'         => $user->id,
            'organization_id' => $user->organization_id,
            'body'            => $validated['body'],
            'is_internal'     => $isInternal,
        ]);

        return (new CommentResource($comment->load('user')))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Ensure the authenticated user can access this ticket.
     * - Organization boundary enforced for all roles
     * - Customers can only access their own tickets
     * Returns 404 (not 403) to avoid leaking existence of cross-org tickets.
     */
    private function authorizeTicketAccess(Ticket $ticket): void
    {
        $user = auth()->user();

        if ($ticket->organization_id !== $user->organization_id) {
            abort(404);
        }

        if ($user->isCustomer() && $ticket->user_id !== $user->id) {
            abort(404);
        }
    }
}
