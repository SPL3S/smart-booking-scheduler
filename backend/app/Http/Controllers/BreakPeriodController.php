<?php

namespace App\Http\Controllers;

use App\Models\BreakPeriod;
use App\Models\WorkingHour;
use App\Services\BreakPeriodService;
use Illuminate\Http\Request;

class BreakPeriodController extends Controller
{
    protected $breakPeriodService;

    public function __construct(BreakPeriodService $breakPeriodService)
    {
        $this->breakPeriodService = $breakPeriodService;
    }

    /**
     * Get all break periods for a working hour.
     *
     * @param WorkingHour $workingHour
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(WorkingHour $workingHour)
    {
        $breakPeriods = $this->breakPeriodService->getForWorkingHour($workingHour);

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
        $validated = $request->validate([
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'name' => 'nullable|string|max:255',
        ]);

        try {
            $breakPeriod = $this->breakPeriodService->create($workingHour, $validated);
            return response()->json($breakPeriod, 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => [
                    'start_time' => ['Break period must start after or at working hour start time'],
                    'end_time' => ['Break period must end before or at working hour end time']
                ]
            ], 422);
        }
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
        $validated = $request->validate([
            'start_time' => 'sometimes|required|date_format:H:i',
            'end_time' => 'sometimes|required|date_format:H:i|after:start_time',
            'name' => 'nullable|string|max:255',
            'is_active' => 'sometimes|boolean',
        ]);

        try {
            $breakPeriod = $this->breakPeriodService->update($breakPeriod, $validated);
            return response()->json($breakPeriod);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => [
                    'start_time' => ['Break period must start after or at working hour start time'],
                    'end_time' => ['Break period must end before or at working hour end time']
                ]
            ], 422);
        }
    }

    /**
     * Delete a break period.
     *
     * @param BreakPeriod $breakPeriod
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(BreakPeriod $breakPeriod)
    {
        $this->breakPeriodService->delete($breakPeriod);

        return response()->json(null, 204);
    }
}
