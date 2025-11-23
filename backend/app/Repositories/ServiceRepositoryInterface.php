<?php

namespace App\Repositories;

use App\Models\Service;

interface ServiceRepositoryInterface
{
    public function findOrFail(int $id): Service;
}
