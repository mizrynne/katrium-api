<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\GuestTag;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreGuestRequest;
use App\Http\Requests\Api\V1\UpdateGuestRequest;
use App\Http\Resources\Api\V1\GuestResource;
use App\Models\Guest;
use App\Services\GuestService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class GuestController extends Controller
{
    public function __construct(private readonly GuestService $guests) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'tag' => ['nullable', Rule::enum(GuestTag::class)],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
        ]);

        return GuestResource::collection($this->guests->paginate($request->user(), $filters));
    }

    public function show(Request $request, int $guest): GuestResource
    {
        return new GuestResource($this->guests->findVisibleOrFail($request->user(), $guest));
    }

    /** Resource of a freshly created model => 201 automatically. */
    public function store(StoreGuestRequest $request): GuestResource
    {
        return new GuestResource($this->guests->create($request->validated()));
    }

    public function update(UpdateGuestRequest $request, Guest $guest): GuestResource
    {
        return new GuestResource($this->guests->update($guest, $request->validated()));
    }
}
