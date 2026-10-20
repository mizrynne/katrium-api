<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
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

    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = min(max($request->integer('per_page', 15), 1), 100);

        return UserResource::collection(
            $this->users->paginate($request->string('search')->toString() ?: null, $perPage)
        );
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $user = $this->users->create($request->validated());

        return (new UserResource($user))
            ->additional(['message' => 'User account created successfully.'])
            ->response()
            ->setStatusCode(201);
    }

    public function show(User $user): UserResource
    {
        return new UserResource($user->load('roles'));
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $updated = $this->users->update($user, $request->validated(), $request->user());

        return (new UserResource($updated))
            ->additional(['message' => 'User account updated successfully.'])
            ->response();
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        $this->users->delete($user, $request->user());

        return response()->json(['message' => 'User account deleted successfully.']);
    }
}