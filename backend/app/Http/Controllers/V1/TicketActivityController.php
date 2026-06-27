<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TicketActivityController extends Controller
{
    /**
     * GET /api/v1/tickets/{ticket}/activity
     *
     * Returns a paginated audit trail for the given ticket.
     * Organisation scoping is enforced — a ticket that does not
     * belong to the authenticated user's organisation returns 404.
     */
    public function index(Request $request, Ticket $ticket): JsonResponse
    {
        $organizationId = (int) $request->user()->organization_id;

        abort_unless(
            $ticket->organization_id === $organizationId,
            404,
            'Ticket not found.',
        );

        $activities = $ticket
            ->activityLogs()
            ->with('actor:id,name,email')
            ->paginate($request->integer('per_page', 20));

        return response()->json($activities);
    }
}
