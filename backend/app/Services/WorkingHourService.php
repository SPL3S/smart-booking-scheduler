<?php

namespace App\Services;

use App\Models\WorkingHour;
use App\Repositories\WorkingDayRepository;

class WorkingHourService
{
    protected $workingDayRepository;

    public function __construct(WorkingDayRepository $workingDayRepository)
    {
        $this->workingDayRepository = $workingDayRepository;
    }

    /**
     * Get all working hours with localized day names.
     *
     * @param string|null $locale Locale code (e.g., 'en', 'es', 'fr')
     * @return array
     */
    public function getAllWithDayNames(?string $locale = null): array
    {
        $workingHours = WorkingHour::orderBy('day_of_week')->get();

        return $workingHours->map(function ($workingHour) use ($locale) {
            return [
                'id' => $workingHour->id,
                'day_of_week' => $workingHour->day_of_week,
                'day_name' => $this->workingDayRepository->getDayName($workingHour->day_of_week, $locale),
                'day_name_short' => $this->workingDayRepository->getDayNameShort($workingHour->day_of_week, $locale),
                'start_time' => $workingHour->start_time,
                'end_time' => $workingHour->end_time,
                'is_active' => $workingHour->is_active,
            ];
        })->toArray();
    }
}
