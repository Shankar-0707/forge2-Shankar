<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateOrganizationRequest;
use App\Http\Resources\OrganizationResource;
use App\Models\Organization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class OrganizationController extends Controller
{
    /**
     * Display the authenticated user's organization.
     */
    public function index(): AnonymousResourceCollection
    {
        $organization = Organization::findOrFail(
            auth()->user()->organization_id
        );

        return OrganizationResource::collection([$organization]);
    }

    /**
     * Display the specified organization.
     * Tenant isolation: only the auth user's own organization is accessible.
     */
    public function show(Organization $organization): OrganizationResource
    {
        abort_unless(
            $organization->id === auth()->user()->organization_id,
            404
        );

        $organization->load('users');

        return new OrganizationResource($organization);
    }

    /**
     * Update the specified organization.
     * Tenant isolation: only the auth user's own organization is updatable.
     */
    public function update(UpdateOrganizationRequest $request, Organization $organization): OrganizationResource
    {
        abort_unless(
            $organization->id === auth()->user()->organization_id,
            404
        );

        $organization->update($request->validated());

        return new OrganizationResource($organization->fresh());
    }

    /**
     * Remove the specified organization.
     * Tenant isolation: only the auth user's own organization is deletable.
     */
    public function destroy(Organization $organization): JsonResponse
    {
        abort_unless(
            $organization->id === auth()->user()->organization_id,
            404
        );

        $organization->delete();

        return response()->json([
            'message' => 'Organization deleted successfully.',
        ]);
    }
}
