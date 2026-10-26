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

    /**
     * List guests
     *
     * Two response tiers: callers with `manage:guests` get contact data, tag, notes, stats and violations;
     * read-only callers (`view:bookings`, i.e. Business Partners) get identity basics only, limited to guests
     * who stayed in their units. The `tag` filter is ignored for read-only callers.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            /** Matches name; also email/phone for `manage:guests` callers. */
            'search' => ['nullable', 'string', 'max:100'],
            'tag' => ['nullable', Rule::enum(GuestTag::class)],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
        ]);

        return GuestResource::collection($this->guests->paginate($request->user(), $filters));
    }

    /**
     * Get a guest
     *
     * Same two-tier shape as the list endpoint. `404` when a read-only caller has no stay in their units.
     */
    public function show(Request $request, int $guest): GuestResource
    {
        return new GuestResource($this->guests->findVisibleOrFail($request->user(), $guest));
    }

    /**
     * Create a guest
     *
     * Email is case-insensitive unique; phone/mobile are normalised to E.164 (default +63) and unique.
     * At least a name, email or phone is required (`422`).
     */
    public function store(StoreGuestRequest $request): GuestResource
    {
        return new GuestResource($this->guests->create($request->validated()));
    }

    /**
     * Update a guest
     *
     * Partial update; same uniqueness rules as create.
     */
    public function update(UpdateGuestRequest $request, Guest $guest): GuestResource
    {
        return new GuestResource($this->guests->update($guest, $request->validated()));
    }
}