<?php

namespace App\Http\Controllers;

use App\Models\BreakPeriod;
use App\Models\WorkingHour;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class BreakPeriodController extends Controller
{
    /**
     * Get all break periods for a working hour.
     *
     * @param WorkingHour $workingHour
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(WorkingHour $workingHour)
    {
        $breakPeriods = $workingHour->breakPeriods()
            ->orderBy('start_time')
            ->get();

        return response()->json($breakPeriods);
    }

    /**
     * Create a new break period for a working hour.
     *
     * @param Request $request
     * @param WorkingHour $workingHour
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request, WorkingHour $workingHour)
    {
        $validator = Validator::make($request->all(), [
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'name' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $validated = $validator->validated();

        // Validate break is within working hours
        if (!$this->isBreakWithinWorkingHours($validated['start_time'], $validated['end_time'], $workingHour)) {
            return response()->json([
                'message' => 'Break period must be within working hours',
                'errors' => [
                    'start_time' => ['Break period must start after or at working hour start time'],
                    'end_time' => ['Break period must end before or at working hour end time']
                ]
            ], 422);
        }

        $breakPeriod = $workingHour->breakPeriods()->create($validated);

        return response()->json($breakPeriod, 201);
    }

    /**
     * Update a break period.
     *
     * @param Request $request
     * @param BreakPeriod $breakPeriod
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, BreakPeriod $breakPeriod)
    {
        $validator = Validator::make($request->all(), [
            'start_time' => 'sometimes|required|date_format:H:i',
            'end_time' => 'sometimes|required|date_format:H:i|after:start_time',
            'name' => 'nullable|string|max:255',
            'is_active' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $validated = $validator->validated();

        // If start_time or end_time is being updated, validate break is within working hours
        if (isset($validated['start_time']) || isset($validated['end_time'])) {
            $startTime = $validated['start_time'] ?? $breakPeriod->start_time;
            $endTime = $validated['end_time'] ?? $breakPeriod->end_time;

            if (!$this->isBreakWithinWorkingHours($startTime, $endTime, $breakPeriod->workingHour)) {
                return response()->json([
                    'message' => 'Break period must be within working hours',
                    'errors' => [
                        'start_time' => ['Break period must start after or at working hour start time'],
                        'end_time' => ['Break period must end before or at working hour end time']
                    ]
                ], 422);
            }
        }

        $breakPeriod->update($validated);

        return response()->json($breakPeriod);
    }

    /**
     * Delete a break period.
     *
     * @param BreakPeriod $breakPeriod
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(BreakPeriod $breakPeriod)
    {
        $breakPeriod->delete();

        return response()->json(null, 204);
    }

    /**
     * Check if break period is within working hours.
     *
     * @param string $breakStartTime
     * @param string $breakEndTime
     * @param WorkingHour $workingHour
     * @return bool
     */
    private function isBreakWithinWorkingHours(string $breakStartTime, string $breakEndTime, WorkingHour $workingHour): bool
    {
        $breakStart = Carbon::parse($breakStartTime);
        $breakEnd = Carbon::parse($breakEndTime);
        $workingStart = Carbon::parse($workingHour->start_time);
        $workingEnd = Carbon::parse($workingHour->end_time);

        return $breakStart->greaterThanOrEqualTo($workingStart) && 
               $breakEnd->lessThanOrEqualTo($workingEnd);
    }
}

