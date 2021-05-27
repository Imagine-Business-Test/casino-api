<?php

namespace App\Api\V1\Repositories\Eloquent;

use App\Api\V1\Models\PitTypes;
use App\Api\V1\Repositories\EloquentRepository;
use App\Contracts\Repository\IPitTypesRepository;

class PitTypesEloquentRepository extends  EloquentRepository implements IPitTypesRepository
{

    public function model()
    {
        return PitTypes::class;
    }

    public function add($detail)
    {
    }
}
