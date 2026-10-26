<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\IndexPropertyRequest;
use App\Http\Requests\Api\V1\StorePropertyRequest;
use App\Http\Requests\Api\V1\UpdatePropertyRequest;
use App\Http\Resources\Api\V1\PropertyResource;
use App\Models\Property;
use App\Services\PropertyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PropertyController extends Controller
{
    public function __construct(private readonly PropertyService $properties)
    {
    }

    /**
     * List properties
     *
     * Staff see every property. Business Partners only see properties containing units they own,
     * and `beds24_id` is omitted for them.
     */
    public function index(IndexPropertyRequest $request): AnonymousResourceCollection
    {
        return PropertyResource::collection(
            $this->properties->paginate($request->user(), $request->validated())
        );
    }

    /**
     * Create a property
     */
    public function store(StorePropertyRequest $request): JsonResponse
    {
        $property = $this->properties->create($request->validated());

        return (new PropertyResource($property))->response()->setStatusCode(201);
    }

    /**
     * Get a property
     *
     * Includes the caller-visible units. Returns `404` if a Business Partner does not own any unit in it.
     */
    public function show(Request $request, int $property): PropertyResource
    {
        return new PropertyResource($this->properties->show($request->user(), $property));
    }

    /**
     * Update a property
     */
    public function update(UpdatePropertyRequest $request, Property $property): PropertyResource
    {
        return new PropertyResource(
            $this->properties->update($request->user(), $property, $request->validated())
        );
    }

    /**
     * Archive a property
     *
     * Soft-archives (`is_active = false`). Returns `409` while the property still has active units.
     */
    public function destroy(Request $request, Property $property): PropertyResource
    {
        return new PropertyResource($this->properties->archive($request->user(), $property));
    }
}