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
        $query = Booking::with('service');

        // Filter by date if provided
        if ($request->has('date')) {
            $request->validate([
                'date' => 'required|date'
            ]);
            $query->where('booking_date', $request->date);
        }

        // Filter by status if provided
        if ($request->has('status')) {
            $request->validate([
                'status' => 'required|in:pending,confirmed,cancelled'
            ]);
            $query->where('status', $request->status);
        }

        // Filter by date range if provided
        if ($request->has('date_from')) {
            $request->validate([
                'date_from' => 'required|date'
            ]);
            $query->where('booking_date', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $request->validate([
                'date_to' => 'required|date'
            ]);
            $query->where('booking_date', '<=', $request->date_to);
        }

        // Order by closest date and time (upcoming bookings first)
        $bookings = $query->orderBy('booking_date', 'asc')
            ->orderBy('start_time', 'asc')
            ->get();

        return response()->json([
            'bookings' => $bookings
        ]);
    }

    /**
     * Update booking status
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:pending,confirmed,cancelled'
        ]);

        $booking = Booking::findOrFail($id);
        $booking->status = $validated['status'];
        $booking->save();

        return response()->json([
            'message' => 'Booking status updated successfully',
            'booking' => $booking->load('service')
        ]);
    }

    /**
     * Delete a booking
     *
     * @param int $id
     * @return JsonResponse
     */
    public function destroy(int $id): JsonResponse
    {
        $booking = Booking::findOrFail($id);
        $booking->delete();

        return response()->json([
            'message' => 'Booking deleted successfully'
        ]);
    }
}

