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

    public function index(IndexUnitRequest $request): AnonymousResourceCollection
    {
        return UnitResource::collection(
            $this->units->paginate($request->user(), $request->validated())
        );
    }

    public function store(StoreUnitRequest $request): JsonResponse
    {
        $unit = $this->units->create($request->validated());

        return (new UnitResource($unit))->response()->setStatusCode(201);
    }

    public function show(Request $request, int $unit): UnitResource
    {
        return new UnitResource($this->units->show($request->user(), $unit));
    }

    public function update(UpdateUnitRequest $request, Unit $unit): UnitResource
    {
        return new UnitResource(
            $this->units->update($request->user(), $unit, $request->validated())
        );
    }

    public function destroy(Request $request, Unit $unit): UnitResource
    {
        return new UnitResource($this->units->archive($request->user(), $unit));
    }
}