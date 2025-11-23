<?php

namespace App\Services;

use App\Models\Booking;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BookingService
{
    /**
     * Check if a booking conflicts with existing bookings
     *
     * @param string $date
     * @param string $startTime
     * @param string $endTime
     * @return bool
     */
    public function hasConflict(string $date, string $startTime, string $endTime): bool
    {
        return Booking::where('booking_date', $date)
            ->where('status', '!=', 'cancelled')
            ->where(function ($query) use ($startTime, $endTime) {
                $query->whereBetween('start_time', [$startTime, $endTime])
                    ->orWhereBetween('end_time', [$startTime, $endTime])
                    ->orWhere(function ($q) use ($startTime, $endTime) {
                        $q->where('start_time', '<=', $startTime)
                            ->where('end_time', '>=', $endTime);
                    });
            })
            ->exists();
    }

    /**
     * Create a new booking with database transaction
     *
     * @param array $data
     * @return Booking
     * @throws \Exception
     */
    public function createBooking(array $data): Booking
    {
        return DB::transaction(function () use ($data) {
            // Check for conflicts before creating
            if ($this->hasConflict(
                $data['booking_date'],
                $data['start_time'],
                $data['end_time']
            )) {
                throw new \Exception('Booking conflict detected. The selected time slot is already booked.');
            }

            // Create the booking
            $booking = Booking::create($data);

            Log::info('Booking created', [
                'booking_id' => $booking->id,
                'service_id' => $booking->service_id,
                'date' => $booking->booking_date,
                'time' => $booking->start_time . ' - ' . $booking->end_time,
            ]);

            return $booking;
        });
    }

    /**
     * Get all bookings for a specific date
     *
     * @param string $date
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getBookingsForDate(string $date)
    {
        return Booking::where('booking_date', $date)
            ->with('service')
            ->orderBy('start_time')
            ->get();
    }

    /**
     * Get all bookings ordered by date and time
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getAllBookings()
    {
        return Booking::with('service')
            ->orderBy('booking_date')
            ->orderBy('start_time')
            ->get();
    }
}
