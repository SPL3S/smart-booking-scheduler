<?php

namespace App\Http\Controllers;

use App\Models\WorkingHour;
use Illuminate\Http\Request;

class WorkingHourController extends Controller
{
    public function index()
    {
        $workingHours = WorkingHour::orderBy('day_of_week')->get();
        return response()->json($workingHours);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'day_of_week' => 'required|integer|between:0,6|unique:working_hours',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'is_active' => 'sometimes|boolean'
        ]);

        $workingHour = WorkingHour::create($validated);

        return response()->json($workingHour, 201);
    }

    public function update(Request $request, WorkingHour $workingHour)
    {
        $validated = $request->validate([
            'start_time' => 'sometimes|date_format:H:i:s',
            'end_time' => 'sometimes|date_format:H:i:s|after:start_time',
            'is_active' => 'sometimes|boolean'
        ]);

        $workingHour->update($validated);

        return response()->json($workingHour);
    }

    public function destroy(WorkingHour $workingHour)
    {
        $workingHour->delete();
        return response()->json(['message' => 'Working hour deleted']);
    }
}