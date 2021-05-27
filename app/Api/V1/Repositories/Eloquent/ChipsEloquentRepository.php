<?php

namespace App\Api\V1\Repositories\Eloquent;

use App\Api\V1\Models\Chips;
use App\Api\V1\Repositories\EloquentRepository;
use App\Contracts\Repository\IChipRepository;

class ChipsEloquentRepository extends  EloquentRepository implements IChipRepository
{

    public function model()
    {
        return Chips::class;
    }
}
