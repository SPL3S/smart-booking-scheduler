<?php

namespace App\Repositories;

use App\Models\Service;

class ServiceRepository implements ServiceRepositoryInterface
{
    public function findOrFail(int $id): Service
    {
        return Service::findOrFail($id);
    }
}
