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

    public function index(IndexPropertyRequest $request): AnonymousResourceCollection
    {
        return PropertyResource::collection(
            $this->properties->paginate($request->user(), $request->validated())
        );
    }

    public function store(StorePropertyRequest $request): JsonResponse
    {
        $property = $this->properties->create($request->validated());

        return (new PropertyResource($property))->response()->setStatusCode(201);
    }

    public function show(Request $request, int $property): PropertyResource
    {
        return new PropertyResource($this->properties->show($request->user(), $property));
    }

    public function update(UpdatePropertyRequest $request, Property $property): PropertyResource
    {
        return new PropertyResource(
            $this->properties->update($request->user(), $property, $request->validated())
        );
    }

    public function destroy(Request $request, Property $property): PropertyResource
    {
        return new PropertyResource($this->properties->archive($request->user(), $property));
    }
}