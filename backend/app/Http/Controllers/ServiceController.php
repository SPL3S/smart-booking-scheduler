<?php

namespace App\Http\Controllers;

use App\Models\Service;
use App\Services\ServiceService;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    protected $serviceService;

    public function __construct(ServiceService $serviceService)
    {
        $this->serviceService = $serviceService;
    }

    public function index()
    {
        $services = $this->serviceService->getAll();
        return response()->json($services);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'duration_minutes' => 'required|integer|min:5|max:480',
            'price' => 'required|numeric|min:0'
        ]);

        $service = $this->serviceService->create($validated);

        return response()->json($service, 201);
    }

    public function show(Service $service)
    {
        return response()->json($service);
    }

    public function update(Request $request, Service $service)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'duration_minutes' => 'sometimes|integer|min:5|max:480',
            'price' => 'sometimes|numeric|min:0'
        ]);

        $service = $this->serviceService->update($service, $validated);

        return response()->json($service);
    }

    public function destroy(Service $service)
    {
        $this->serviceService->delete($service);
        return response()->json(['message' => 'Service deleted']);
    }
}
