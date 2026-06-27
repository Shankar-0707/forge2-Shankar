<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Project\StoreProjectRequest;
use App\Http\Requests\Project\UpdateProjectRequest;
use App\Http\Resources\ProjectResource;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProjectController extends Controller
{
    /**
     * Display a paginated list of projects for the authenticated user's organization.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $org = auth()->user()->organization_id;

        $projects = Project::query()
            ->where('organization_id', $org)
            ->when(
                $request->input('status'),
                fn ($query, $status) => $query->where('status', $status),
            )
            ->when(
                $request->input('search'),
                fn ($query, $search) => $query->where('name', 'like", "%{$search}%"),
            )
            ->withCount(['tickets as open_tickets_count' => fn ($q) => $q->whereNull('resolved_at')])
            ->latest()
            ->paginate(
                perPage: $request->integer('per_page', 15),
                page: $request->integer('page', 1),
            );

        return ProjectResource::collection($projects);
    }

    /**
     * Store a newly created project in the authenticated user's organization.
     */
    public function store(StoreProjectRequest $request): JsonResponse
    {
        $org = auth()->user()->organization_id;

        $project = Project::create([
            ...$request->validated(),
            'organization_id' => $org,
            'created_by' => auth()->id(),
        ]);

        return (new ProjectResource($project))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display the specified project, ensuring it belongs to the user's organization.
     */
    public function show(Project $project): ProjectResource
    {
        $org = auth()->user()->organization_id;

        abort_unless($project->organization_id === $org, 404, 'Project not found.');

        return new ProjectResource(
            $project->loadCount(['tickets as open_tickets_count' => fn ($q) => $q->whereNull('resolved_at')])
        );
    }

    /**
     * Update the specified project, ensuring it belongs to the user's organization.
     */
    public function update(UpdateProjectRequest $request, Project $project): ProjectResource
    {
        $org = auth()->user()->organization_id;

        abort_unless($project->organization_id === $org, 404, 'Project not found.');

        $project->update($request->validated());

        return new ProjectResource($project->fresh());
    }

    /**
     * Remove the specified project, ensuring it belongs to the user's organization.
     */
    public function destroy(Project $project): JsonResponse
    {
        $org = auth()->user()->organization_id;

        abort_unless($project->organization_id === $org, 404, 'Project not found.');

        $project->delete();

        return response()->json(null, 204);
    }
}
