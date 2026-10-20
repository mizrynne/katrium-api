<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\IndexBookingRequest;
use App\Http\Requests\Api\V1\StoreBookingRequest;
use App\Http\Requests\Api\V1\UpdateBookingRequest;
use App\Http\Resources\Api\V1\BookingResource;
use App\Models\Booking;
use App\Services\BookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BookingController extends Controller
{
    public function __construct(private readonly BookingService $bookings) {}

    public function index(IndexBookingRequest $request): AnonymousResourceCollection
    {
        return BookingResource::collection($this->bookings->paginate($request->user(), $request->validated()))
            ->additional(['success' => true]);
    }

    public function store(StoreBookingRequest $request): JsonResponse
    {
        $booking = $this->bookings->create($request->validated());

        return (new BookingResource($booking))
            ->additional(['success' => true, 'message' => 'Booking created.'])
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, Booking $booking): BookingResource
    {
        abort_unless($this->bookings->isVisibleTo($booking, $request->user()), 404, 'Booking not found.');

        return (new BookingResource($booking->load(['unit', 'guest'])))
            ->additional(['success' => true]);
    }

    public function update(UpdateBookingRequest $request, Booking $booking): BookingResource
    {
        return (new BookingResource($this->bookings->update($booking, $request->validated())))
            ->additional(['success' => true, 'message' => 'Booking updated.']);
    }

    public function destroy(Booking $booking): BookingResource
    {
        return (new BookingResource($this->bookings->cancel($booking)))
            ->additional(['success' => true, 'message' => 'Booking canceled.']);
    }
}