<?php

namespace App\Repositories;

use App\Models\WorkingHour;

class WorkingHourRepository implements WorkingHourRepositoryInterface
{
    public function getForDay(int $dayOfWeek): ?WorkingHour
    {
        return WorkingHour::where('day_of_week', $dayOfWeek)
            ->where('is_active', true)
            ->first();
    }

    public function getActiveDays(): array
    {
        return WorkingHour::where('is_active', true)
            ->pluck('day_of_week')
            ->toArray();
    }

    public function getAllOrderedByDay()
    {
        return WorkingHour::orderBy('day_of_week')->get();
    }
}
