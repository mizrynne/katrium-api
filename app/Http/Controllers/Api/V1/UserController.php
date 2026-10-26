<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\IndexUserRequest;
use App\Http\Requests\Api\V1\StoreUserRequest;
use App\Http\Requests\Api\V1\UpdateUserRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UserController extends Controller
{
    public function __construct(private readonly UserService $users) {}

    /**
     * List users
     *
     * Paginated, ordered by name. Each item includes `role_permissions`, `direct_permissions`
     * (overrides) and the merged `permissions`.
     */
    public function index(IndexUserRequest $request): AnonymousResourceCollection
    {
        return UserResource::collection(
            $this->users->paginate($request->validated('search'), (int) $request->validated('per_page', 15))
        );
    }

    /**
     * Pre-provision a user
     *
     * Creates the account so the person can sign in with Google. Passwords are rejected (`422`).
     * Only a Superadmin may grant the `Superadmin` role or the `manage:users` permission (`403` otherwise);
     * non-Superadmins can only grant permissions they hold themselves.
     */
    public function store(StoreUserRequest $request): JsonResponse
    {
        $user = $this->users->create($request->validated());

        return (new UserResource($user))
            ->additional(['message' => 'User account created successfully.'])
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Get a user
     */
    public function show(User $user): UserResource
    {
        return new UserResource($user->load('roles'));
    }

    /**
     * Update a user
     *
     * Change name, email, role and/or the direct permission overrides (`permissions` replaces the full override set).
     * Users cannot change their own role or overrides (`422`). Only a Superadmin may modify a Superadmin (`403`).
     */
    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $updated = $this->users->update($user, $request->validated(), $request->user());

        return (new UserResource($updated))
            ->additional(['message' => 'User account updated successfully.'])
            ->response();
    }

    /**
     * Delete a user
     *
     * Revokes all of the user's tokens and deletes the account. You cannot delete yourself (`422`).
     */
    public function destroy(Request $request, User $user): JsonResponse
    {
        $this->users->delete($user, $request->user());

        return response()->json(['message' => 'User account deleted successfully.']);
    }
}