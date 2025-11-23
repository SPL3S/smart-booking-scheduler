<?php

namespace App\Services;

use App\Repositories\BookingRepositoryInterface;
use Illuminate\Database\Connection;
use Psr\Log\LoggerInterface;

class BookingService
{
    protected $bookingRepository;
    protected $db;
    protected $logger;

    public function __construct(
        BookingRepositoryInterface $bookingRepository,
        Connection $db,
        LoggerInterface $logger
    ) {
        $this->bookingRepository = $bookingRepository;
        $this->db = $db;
        $this->logger = $logger;
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
        return $this->db->transaction(function () use ($data) {
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

            $this->logger->info('Booking created', [
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

    /**
     * Get bookings with filters (for admin)
     *
     * @param array $filters
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getBookingsWithFilters(array $filters = [])
    {
        $query = \App\Models\Booking::with('service');

        if (isset($filters['date'])) {
            $query->where('booking_date', $filters['date']);
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['date_from'])) {
            $query->where('booking_date', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->where('booking_date', '<=', $filters['date_to']);
        }

        return $query->orderBy('booking_date', 'asc')
            ->orderBy('start_time', 'asc')
            ->get();
    }

    /**
     * Update booking status
     *
     * @param int $id
     * @param string $status
     * @return \App\Models\Booking
     */
    public function updateStatus(int $id, string $status)
    {
        $booking = \App\Models\Booking::findOrFail($id);
        $booking->status = $status;
        $booking->save();
        return $booking->load('service');
    }

    /**
     * Delete a booking
     *
     * @param int $id
     * @return bool
     */
    public function delete(int $id): bool
    {
        $booking = \App\Models\Booking::findOrFail($id);
        return $booking->delete();
    }
}
