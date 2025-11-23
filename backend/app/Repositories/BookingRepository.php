<?php

namespace App\Repositories;

use App\Models\Booking;

class BookingRepository implements BookingRepositoryInterface
{
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

    public function create(array $data): Booking
    {
        return Booking::create($data);
    }

    public function getByDate(string $date)
    {
        return Booking::where('booking_date', $date)
            ->with('service')
            ->orderBy('start_time')
            ->get();
    }

    public function getByDateExcludingCancelled(string $date)
    {
        return Booking::where('booking_date', $date)
            ->where('status', '!=', 'cancelled')
            ->get();
    }

    public function getAll()
    {
        return Booking::with('service')
            ->orderBy('booking_date')
            ->orderBy('start_time')
            ->get();
    }
}
