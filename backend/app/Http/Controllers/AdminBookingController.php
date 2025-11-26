<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Services\BookingService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AdminBookingController extends Controller
{
    protected $bookingService;

    public function __construct(BookingService $bookingService)
    {
        $this->bookingService = $bookingService;
    }

    /**
     * Get all bookings with optional filtering
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $filters = [];

        if ($request->has('date')) {
            $request->validate(['date' => 'required|date']);
            $filters['date'] = $request->date;
        }

        if ($request->has('status')) {
            $request->validate(['status' => 'required|in:pending,confirmed,cancelled']);
            $filters['status'] = $request->status;
        }

        if ($request->has('date_from')) {
            $request->validate(['date_from' => 'required|date']);
            $filters['date_from'] = $request->date_from;
        }

        if ($request->has('date_to')) {
            $request->validate(['date_to' => 'required|date']);
            $filters['date_to'] = $request->date_to;
        }

        $bookings = $this->bookingService->getBookingsWithFilters($filters);

        return response()->json([
            'bookings' => $bookings
        ]);
    }

    /**
     * Update booking status
     *
     * @param Request $request
     * @param Booking $booking
     * @return JsonResponse
     */
    public function update(Request $request, Booking $booking): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:pending,confirmed,cancelled'
        ]);

        $booking = $this->bookingService->updateStatus($booking->id, $validated['status']);

        return response()->json([
            'message' => 'Booking status updated successfully',
            'booking' => $booking
        ]);
    }

    /**
     * Delete a booking
     *
     * @param Booking $booking
     * @return JsonResponse
     */
    public function destroy(Booking $booking): JsonResponse
    {
        $this->bookingService->delete($booking->id);

        return response()->noContent();
    }
}
