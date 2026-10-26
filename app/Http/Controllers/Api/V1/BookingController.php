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

    /**
     * List bookings
     *
     * Staff with `manage:bookings` see everything; others (Business Partners) only bookings in units they own.
     * Guest `email`/`phone`, `channel_confirmation_code` and `sync.beds24_booking_id`/`sync.error` are `null`
     * unless the caller has `manage:bookings`. Use `from`/`to` for the calendar window (overlap semantics).
     */
    public function index(IndexBookingRequest $request): AnonymousResourceCollection
    {
        return BookingResource::collection($this->bookings->paginate($request->user(), $request->validated()))
            ->additional(['success' => true]);
    }

    /**
     * Create a booking
     *
     * Link an existing guest (`guest_id`) or create/match one inline (`guest`, deduplicated by email then phone).
     * `check_in`/`check_out` accept a date (`YYYY-MM-DD`, property default times applied) or a wall-clock
     * datetime in the property's timezone. `direct` bookings are queued for push to Beds24 (`sync.status = pending`).
     *
     * Business-rule failures return `422` with `code` = `booking_overlap` (includes `conflict`) or `invalid_stay`.
     */
    public function store(StoreBookingRequest $request): JsonResponse
    {
        $booking = $this->bookings->create($request->validated());

        return (new BookingResource($booking))
            ->additional(['success' => true, 'message' => 'Booking created.'])
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Get a booking
     *
     * `404` when the caller cannot see the booking's unit.
     */
    public function show(Request $request, Booking $booking): BookingResource
    {
        abort_unless($this->bookings->isVisibleTo($booking, $request->user()), 404, 'Booking not found.');

        return (new BookingResource($booking->load(['unit', 'guest'])))
            ->additional(['success' => true]);
    }

    /**
     * Update a booking
     *
     * Bookings imported from an OTA are channel-managed: editing `unit_id`, `check_in`, `check_out`, `status`
     * or `total_price` returns `422` with `code = ota_booking_locked`. Changing a direct booking re-queues the Beds24 sync.
     */
    public function update(UpdateBookingRequest $request, Booking $booking): BookingResource
    {
        return (new BookingResource($this->bookings->update($booking, $request->validated())))
            ->additional(['success' => true, 'message' => 'Booking updated.']);
    }

    /**
     * Cancel a booking
     *
     * Sets status to `canceled` (idempotent). OTA bookings must be cancelled on the channel
     * (`422`, `code = ota_cancellation_not_allowed`).
     */
    public function destroy(Booking $booking): BookingResource
    {
        return (new BookingResource($this->bookings->cancel($booking)))
            ->additional(['success' => true, 'message' => 'Booking canceled.']);
    }
}