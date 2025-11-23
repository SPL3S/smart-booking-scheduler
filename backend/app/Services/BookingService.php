<?php

namespace App\Services;

use App\Repositories\BookingRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BookingService
{
    protected $bookingRepository;

    public function __construct(BookingRepositoryInterface $bookingRepository)
    {
        $this->bookingRepository = $bookingRepository;
    }

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
        return $this->bookingRepository->hasConflict($date, $startTime, $endTime);
    }

    /**
     * Create a new booking with database transaction
     *
     * @param array $data
     * @return \App\Models\Booking
     * @throws \Exception
     */
    public function createBooking(array $data)
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
            $booking = $this->bookingRepository->create($data);

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
        return $this->bookingRepository->getByDate($date);
    }

    /**
     * Get all bookings ordered by date and time
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getAllBookings()
    {
        return $this->bookingRepository->getAll();
    }
}
