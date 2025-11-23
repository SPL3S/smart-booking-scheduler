<?php

namespace App\Http\Controllers;

use App\Models\WorkingHour;
use App\Services\WorkingHourService;
use Illuminate\Http\Request;

class WorkingHourController extends Controller
{
    protected $workingHourService;

    public function __construct(WorkingHourService $workingHourService)
    {
        $this->workingHourService = $workingHourService;
    }

    public function index(Request $request)
    {
        $locale = $request->query('locale') ?? $request->getLocale();
        $workingHours = $this->workingHourService->getAllWithDayNames($locale);

        return response()->json([
            'locale' => $locale,
            'working_hours' => $workingHours
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'day_of_week' => 'required|integer|between:0,6|unique:working_hours',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'is_active' => 'sometimes|boolean'
        ]);

        $workingHour = $this->workingHourService->create($validated);

        return response()->json($workingHour, 201);
    }

    public function update(Request $request, WorkingHour $workingHour)
    {
        // Normalize time inputs to H:i:s format before validation
        $input = $request->all();
        if (isset($input['start_time']) && !empty($input['start_time']) && strlen($input['start_time']) === 5) {
            $input['start_time'] .= ':00';
        }
        if (isset($input['end_time']) && !empty($input['end_time']) && strlen($input['end_time']) === 5) {
            $input['end_time'] .= ':00';
        }

        // Merge normalized input back to request
        $request->merge($input);

        $validated = $request->validate([
            'start_time' => 'sometimes|date_format:H:i:s',
            'end_time' => 'sometimes|date_format:H:i:s|after:start_time',
            'is_active' => 'sometimes|boolean'
        ]);

        $workingHour = $this->workingHourService->update($workingHour, $validated);

        return response()->json($workingHour);
    }

    public function destroy(WorkingHour $workingHour)
    {
        $this->workingHourService->delete($workingHour);
        return response()->json(['message' => 'Working hour deleted']);
    }
}
