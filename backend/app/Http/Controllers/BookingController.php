<?php

namespace App\Http\Controllers;

use App\Services\SlotGeneratorService;
use App\Services\BookingService;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;

class BookingController extends Controller
{
    protected $slotGenerator;
    protected $bookingService;

    public function __construct(SlotGeneratorService $slotGenerator, BookingService $bookingService)
    {
        $this->slotGenerator = $slotGenerator;
        $this->bookingService = $bookingService;
    }

    public function getAvailableSlots(Request $request)
    {
        $request->validate([
            'date' => 'required|date|after_or_equal:today',
            'service_id' => 'required|exists:services,id'
        ]);

        $slots = $this->slotGenerator->getAvailableSlots(
            $request->date,
            $request->service_id
        );

        return response()->json(['slots' => $slots]);
    }

    public function getWorkingDays()
    {
        $workingDays = $this->slotGenerator->getActiveWorkingDays();

        return response()->json(['working_days' => $workingDays]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'service_id' => 'required|exists:services,id',
            'client_email' => 'required|email',
            'booking_date' => 'required|date|after_or_equal:today',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
        ]);

        try {
            $booking = $this->bookingService->createBooking($validated);

            return response()->json([
                'message' => 'Booking created successfully',
                'booking' => $booking->load('service')
            ], 201);
        } catch (QueryException $e) {
            // Handle database constraint violations (e.g., unique constraint on booking_date + start_time)
            if ($e->getCode() === '23000') {
                return response()->json([
                    'message' => 'This time slot is already booked. Please select another time.'
                ], 422);
            }

            // Re-throw unexpected database errors to be handled by global exception handler
            throw $e;
        }
    }

    public function index(Request $request)
    {
        // If date is provided, get bookings for that specific date
        if ($request->has('date')) {
            $request->validate([
                'date' => 'required|date'
            ]);

            $bookings = $this->bookingService->getBookingsForDate($request->date);
        } else {
            // Otherwise, get all bookings
            $bookings = $this->bookingService->getAllBookings();
        }

        return response()->json($bookings);
    }
}
