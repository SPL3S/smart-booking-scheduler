<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Service;
use App\Services\SlotGeneratorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class BookingController extends Controller
{
    protected $slotGenerator;

    public function __construct(SlotGeneratorService $slotGenerator)
    {
        $this->slotGenerator = $slotGenerator;
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

    public function store(Request $request)
    {
        $validated = $request->validate([
            'service_id' => 'required|exists:services,id',
            'client_email' => 'required|email',
            'booking_date' => 'required|date|after_or_equal:today',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
        ]);

        // prevent double booking
        try {
            $booking = DB::transaction(function () use ($validated) {
                // Lock and check for conflicts
                $conflict = Booking::where('booking_date', $validated['booking_date'])
                    ->where('status', '!=', 'cancelled')
                    ->where(function ($query) use ($validated) {
                        $query->whereBetween('start_time', [$validated['start_time'], $validated['end_time']])
                            ->orWhereBetween('end_time', [$validated['start_time'], $validated['end_time']])
                            ->orWhere(function ($q) use ($validated) {
                                $q->where('start_time', '<=', $validated['start_time'])
                                  ->where('end_time', '>=', $validated['end_time']);
                            });
                    })
                    ->lockForUpdate()
                    ->first();

                if ($conflict) {
                    throw new \Exception('This time slot is already booked.');
                }

                return Booking::create($validated);
            });

            return response()->json([
                'message' => 'Booking created successfully',
                'booking' => $booking->load('service')
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 422);
        }
    }

    public function index()
    {
        $bookings = Booking::with('service')
            ->orderBy('booking_date')
            ->orderBy('start_time')
            ->get();

        return response()->json($bookings);
    }
}