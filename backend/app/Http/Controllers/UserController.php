<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UserController extends Controller
{
    /**
     * Display a listing of users within the auth user's organization.
     */
    public function index(): AnonymousResourceCollection
    {
        $users = User::where('organization_id', auth()->user()->organization_id)
            ->orderBy('name')
            ->paginate(15);

        return UserResource::collection($users);
    }

    /**
     * Store a newly created user in the auth user's organization.
     * organization_id is always derived from auth — never from request input.
     */
    public function store(StoreUserRequest $request): UserResource
    {
        $user = User::create([
            ...$request->validated(),
            'organization_id' => auth()->user()->organization_id,
        ]);

        return new UserResource($user);
    }

    /**
     * Display the specified user.
     * Tenant isolation: only users within the auth user's organization are accessible.
     */
    public function show(User $user): UserResource
    {
        $this->ensureSameOrganization($user);

        return new UserResource($user);
    }

    /**
     * Update the specified user.
     * Tenant isolation: only users within the auth user's organization are updatable.
     */
    public function update(UpdateUserRequest $request, User $user): UserResource
    {
        $this->ensureSameOrganization($user);

        $user->update($request->validated());

        return new UserResource($user->fresh());
    }

    /**
     * Remove the specified user.
     * Tenant isolation: only users within the auth user's organization are deletable.
     * Users cannot delete their own account.
     */
    public function destroy(User $user): JsonResponse
    {
        $this->ensureSameOrganization($user);

        if ($user->id === auth()->id()) {
            return response()->json([
                'message' => 'You cannot delete your own account.',
            ], 403);
        }

        $user->delete();

        return response()->json([
            'message' => 'User deleted successfully.',
        ]);
    }

    /**
     * Ensure the given user belongs to the auth user's organization.
     * Returns 404 (not 403) to avoid leaking existence of cross-tenant resources.
     */
    private function ensureSameOrganization(User $user): void
    {
        abort_unless(
            $user->organization_id === auth()->user()->organization_id,
            404
        );
    }
}
