<?php

namespace App\Services;

use App\Repositories\WorkingDayRepository;
use App\Repositories\WorkingHourRepositoryInterface;
use App\Models\WorkingHour;

class WorkingHourService
{
    protected $workingDayRepository;
    protected $workingHourRepository;

    public function __construct(
        WorkingDayRepository $workingDayRepository,
        WorkingHourRepositoryInterface $workingHourRepository
    ) {
        $this->workingDayRepository = $workingDayRepository;
        $this->workingHourRepository = $workingHourRepository;
    }

    /**
     * Get all working hours with localized day names.
     *
     * @param string|null $locale Locale code (e.g., 'en', 'es', 'fr')
     * @return array
     */
    public function getAllWithDayNames(?string $locale = null): array
    {
        $workingHours = $this->workingHourRepository->getAllOrderedByDay();

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

    /**
     * Create a working hour
     *
     * @param array $data
     * @return WorkingHour
     */
    public function create(array $data): WorkingHour
    {
        return WorkingHour::create($data);
    }

    /**
     * Update a working hour
     *
     * @param WorkingHour $workingHour
     * @param array $data
     * @return WorkingHour
     */
    public function update(WorkingHour $workingHour, array $data): WorkingHour
    {
        $workingHour->update($data);
        return $workingHour->fresh();
    }

    /**
     * Delete a working hour
     *
     * @param WorkingHour $workingHour
     * @return bool
     */
    public function delete(WorkingHour $workingHour): bool
    {
        return $workingHour->delete();
    }
}
