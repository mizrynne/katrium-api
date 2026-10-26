<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\IndexUnitRequest;
use App\Http\Requests\Api\V1\StoreUnitRequest;
use App\Http\Requests\Api\V1\UpdateUnitRequest;
use App\Http\Resources\Api\V1\UnitResource;
use App\Models\Unit;
use App\Services\UnitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UnitController extends Controller
{
    public function __construct(private readonly UnitService $units)
    {
    }

    /**
     * List units
     *
     * Staff see all units. Business Partners are scoped to units where `partner_id` is their user id
     * (the `partner_id` filter is ignored for them) and `beds24_room_id` is omitted.
     */
    public function index(IndexUnitRequest $request): AnonymousResourceCollection
    {
        return UnitResource::collection(
            $this->units->paginate($request->user(), $request->validated())
        );
    }

    /**
     * Create a unit
     *
     * `partner_id` must reference a user holding the Partner role. `parent_id` must belong to the same property.
     */
    public function store(StoreUnitRequest $request): JsonResponse
    {
        $unit = $this->units->create($request->validated());

        return (new UnitResource($unit))->response()->setStatusCode(201);
    }

    /**
     * Get a unit
     *
     * Includes property, partner, parent and children.
     */
    public function show(Request $request, int $unit): UnitResource
    {
        return new UnitResource($this->units->show($request->user(), $unit));
    }

    /**
     * Update a unit
     *
     * `property_id` cannot be changed (`422`). Re-parenting under a descendant is rejected (`422`).
     */
    public function update(UpdateUnitRequest $request, Unit $unit): UnitResource
    {
        return new UnitResource(
            $this->units->update($request->user(), $unit, $request->validated())
        );
    }

    /**
     * Archive a unit
     *
     * Soft-archives (`is_active = false`).
     */
    public function destroy(Request $request, Unit $unit): UnitResource
    {
        return new UnitResource($this->units->archive($request->user(), $unit));
    }
}