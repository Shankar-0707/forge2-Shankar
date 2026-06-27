<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTicketRequest;
use App\Http\Requests\UpdateTicketRequest;
use App\Models\Ticket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;

class TicketController extends Controller
{
    /**
     * Display a paginated, filterable, searchable list of tickets
     * scoped to the authenticated user's organization.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $organizationId = $this->organizationId();

        $tickets = Ticket::query()
            ->where('organization_id', $organizationId)
            ->when(
                $request->filled('status'),
                fn ($query) => $query->where('status', $request->string('status')->toString())
            )
            ->when(
                $request->filled('priority'),
                fn ($query) => $query->where('priority', $request->string('priority')->toString())
            )
            ->when(
                $request->filled('assignee_id'),
                fn ($query) => $query->where('assignee_id', $request->input('assignee_id'))
            )
            ->when(
                $request->filled('search'),
                function ($query) use ($request): void {
                    // Escape LIKE wildcards to avoid query injection via search input.
                    $term = '%'.str_replace(
                        ['\\', '%', '_'],
                        ['\\\\', '\%', '\_'],
                        $request->string('search')->toString()
                    ).'%';

                    $query->where(function ($inner) use ($term): void {
                        $inner->where('subject', 'like', $term)
                            ->orWhere('description', 'like', $term);
                    });
                }
            )
            ->with(['assignee:id,name,email', 'creator:id,name,email'])
            ->latest()
            ->paginate(
                perPage: (int) $request->integer('per_page', 15),
                page: (int) $request->integer('page', 1),
            );

        return JsonResource::collection($tickets);
    }

    /**
     * Persist a new ticket. Organization is always derived from the
     * authenticated user — never from request input.
     */
    public function store(StoreTicketRequest $request): JsonResponse
    {
        $ticket = Ticket::create([
            ...$request->validated(),
            'organization_id' => $this->organizationId(),
            'created_by' => $request->user()->id,
        ]);

        $ticket->load(['assignee:id,name,email', 'creator:id,name,email']);

        return (new JsonResource($ticket))
            ->response()
            ->setStatusCode(JsonResponse::HTTP_CREATED);
    }

    /**
     * Display a single ticket if it belongs to the user's organization.
     * Always return 404 (not 403) on cross-tenant access to avoid
     * leaking existence of resources.
     */
    public function show(Ticket $ticket): JsonResource
    {
        $this->ensureTenantAccess($ticket);

        $ticket->load(['assignee:id,name,email', 'creator:id,name,email']);

        return new JsonResource($ticket);
    }

    public function update(UpdateTicketRequest $request, Ticket $ticket): JsonResource
    {
        $this->ensureTenantAccess($ticket);

        $ticket->update($request->validated());

        $ticket->load(['assignee:id,name,email', 'creator:id,name,email']);

        return new JsonResource($ticket);
    }

    public function destroy(Ticket $ticket): JsonResponse
    {
        $this->ensureTenantAccess($ticket);

        $ticket->delete();

        return response()->json(null, JsonResponse::HTTP_NO_CONTENT);
    }

    /**
     * Resolve the authenticated user's organization id from the auth
     * context. This is the single source of truth for tenant scoping.
     */
    private function organizationId(): int|string
    {
        $organizationId = $this->request()?->user()?->organization_id;

        if ($organizationId === null) {
            abort(500, 'Authenticated user is not associated with an organization.');
        }

        return $organizationId;
    }

    private function request(): ?\Illuminate\Http\Request
    {
        return app(\Illuminate\Http\Request::class);
    }

    /**
     * If the bound ticket does not belong to the user's organization,
     * abort with a 404 to avoid revealing resource existence.
     */
    private function ensureTenantAccess(Ticket $ticket): void
    {
        if ($ticket->organization_id !== $this->organizationId()) {
            abort(JsonResponse::HTTP_NOT_FOUND);
        }
    }
}
