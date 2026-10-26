<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreViolationRequest;
use App\Http\Resources\Api\V1\GuestViolationResource;
use App\Models\Guest;
use App\Services\GuestViolationService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class GuestViolationController extends Controller
{
    public function __construct(private readonly GuestViolationService $violations) {}

    /**
     * List a guest's violations
     *
     * Newest first.
     */
    public function index(Request $request, Guest $guest): AnonymousResourceCollection
    {
        $perPage = $request->validate([
            'per_page' => ['nullable', 'integer', 'between:1,100'],
        ])['per_page'] ?? 25;

        return GuestViolationResource::collection($this->violations->paginateFor($guest, (int) $perPage));
    }

    /**
     * Record a violation
     *
     * Set `blacklist_guest: true` to also flip the guest's tag to `blacklisted` in the same transaction.
     */
    public function store(StoreViolationRequest $request, Guest $guest): GuestViolationResource
    {
        return new GuestViolationResource(
            $this->violations->record($guest, $request->user(), $request->validated())
        );
    }
}