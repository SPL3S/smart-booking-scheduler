<?php

namespace App\Repositories;

use App\Models\Booking;

interface BookingRepositoryInterface
{
    public function hasConflict(string $date, string $startTime, string $endTime): bool;
    public function create(array $data): Booking;
    public function getByDate(string $date);
    public function getByDateExcludingCancelled(string $date);
    public function getAll();
}
