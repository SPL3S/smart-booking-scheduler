<?php

namespace App\Services;

use App\Models\BreakPeriod;
use App\Models\WorkingHour;
use Carbon\Carbon;

class BreakPeriodService
{
    /**
     * Get all break periods for a working hour
     *
     * @param WorkingHour $workingHour
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getForWorkingHour(WorkingHour $workingHour)
    {
        return $workingHour->breakPeriods()
            ->orderBy('start_time')
            ->get();
    }

    /**
     * Create a break period
     *
     * @param WorkingHour $workingHour
     * @param array $data
     * @return BreakPeriod
     * @throws \Exception
     */
    public function create(WorkingHour $workingHour, array $data): BreakPeriod
    {
        // Validate break is within working hours
        if (!$this->isBreakWithinWorkingHours(
            $data['start_time'],
            $data['end_time'],
            $workingHour
        )) {
            throw new \Exception('Break period must be within working hours');
        }

        return $workingHour->breakPeriods()->create($data);
    }

    /**
     * Update a break period
     *
     * @param BreakPeriod $breakPeriod
     * @param array $data
     * @return BreakPeriod
     * @throws \Exception
     */
    public function update(BreakPeriod $breakPeriod, array $data): BreakPeriod
    {
        // If start_time or end_time is being updated, validate break is within working hours
        if (isset($data['start_time']) || isset($data['end_time'])) {
            $startTime = $data['start_time'] ?? $breakPeriod->start_time;
            $endTime = $data['end_time'] ?? $breakPeriod->end_time;

            if (!$this->isBreakWithinWorkingHours($startTime, $endTime, $breakPeriod->workingHour)) {
                throw new \Exception('Break period must be within working hours');
            }
        }

        $breakPeriod->update($data);
        return $breakPeriod->fresh();
    }

    /**
     * Delete a break period
     *
     * @param BreakPeriod $breakPeriod
     * @return bool
     */
    public function delete(BreakPeriod $breakPeriod): bool
    {
        return $breakPeriod->delete();
    }

    /**
     * Check if break period is within working hours
     *
     * @param string $breakStartTime
     * @param string $breakEndTime
     * @param WorkingHour $workingHour
     * @return bool
     */
    public function isBreakWithinWorkingHours(
        string $breakStartTime,
        string $breakEndTime,
        WorkingHour $workingHour
    ): bool {
        $breakStart = Carbon::parse($breakStartTime);
        $breakEnd = Carbon::parse($breakEndTime);
        $workingStart = Carbon::parse($workingHour->start_time);
        $workingEnd = Carbon::parse($workingHour->end_time);

        return $breakStart->greaterThanOrEqualTo($workingStart) &&
               $breakEnd->lessThanOrEqualTo($workingEnd);
    }
}

