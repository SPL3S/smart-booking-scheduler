<?php

namespace App\Services;

use App\Repositories\ServiceRepositoryInterface;
use App\Models\Service;

class ServiceService
{
    protected $serviceRepository;

    public function __construct(ServiceRepositoryInterface $serviceRepository)
    {
        $this->serviceRepository = $serviceRepository;
    }

    public function getAll()
    {
        return Service::all();
    }

    public function create(array $data): Service
    {
        return Service::create($data);
    }

    public function findOrFail(int $id): Service
    {
        return $this->serviceRepository->findOrFail($id);
    }

    public function update(Service $service, array $data): Service
    {
        $service->update($data);
        return $service->fresh();
    }

    public function delete(Service $service): bool
    {
        return $service->delete();
    }
}

