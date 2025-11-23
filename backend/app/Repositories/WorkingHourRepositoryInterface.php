<?php

namespace App\Repositories;

use App\Models\WorkingHour;

interface WorkingHourRepositoryInterface
{
    public function getForDay(int $dayOfWeek): ?WorkingHour;
    public function getActiveDays(): array;
    public function getAllOrderedByDay();
}
