<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TicketResource;
use App\Models\Ticket;
use Illuminate\Http\Request;

class TicketController extends Controller
{
    public function index(Request $request)
    {
        $orgId = $request->user()->organization_id;

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:200'],
            'status' => ['nullable', 'string', 'in:' . implode(',', Ticket::STATUSES)],
            'priority' => ['nullable', 'string', 'in:' . implode(',', Ticket::PRIORITIES)],
            'assignee_id' => ['nullable', 'string'],
            'sort' => ['nullable', 'string', 'in:created_at,priority,sla_due_at,updated_at'],
            'direction' => ['nullable', 'string', 'in:asc,desc'],
        ]);

        $query = Ticket::forOrganization($orgId)
            ->with(['assignee', 'requester']);

        if (! empty($validated['search'])) {
            $query->search($validated['search']);
        }

        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        if (! empty($validated['priority'])) {
            $query->where('priority', $validated['priority']);
        }

        if (! empty($validated['assignee_id'])) {
            if ($validated['assignee_id'] === 'unassigned') {
                $query->whereNull('assignee_id');
            } else {
                $query->where('assignee_id', (int) $validated['assignee_id']);
            }
        }

        $sortColumn = $validated['sort'] ?? 'created_at';
        $sortDirection = $validated['direction'] ?? 'desc';

        if ($sortColumn === 'priority') {
            $priorityOrder = [
                Ticket::PRIORITY_URGENT => 1,
                Ticket::PRIORITY_HIGH => 2,
                Ticket::PRIORITY_MEDIUM => 3,
                Ticket::PRIORITY_LOW => 4,
            ];
            $query->orderByRaw("CASE WHEN priority IS NULL THEN 5 ELSE {$priorityOrder[Ticket::PRIORITY_LOW]} END")
                  ->orderByRaw("CASE priority
                      WHEN 'urgent' THEN 1
                      WHEN 'high' THEN 2
                      WHEN 'medium' THEN 3
                      WHEN 'low' THEN 4
                      ELSE 5 END " . $sortDirection);
        } else {
            $query->orderBy($sortColumn, $sortDirection);
        }

        $tickets = $query->paginate($request->integer('per_page', 50))
            ->through(fn (Ticket $ticket) => new TicketResource($ticket));

        return response()->json($tickets);
    }
}
