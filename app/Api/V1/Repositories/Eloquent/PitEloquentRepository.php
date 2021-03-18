<?php

namespace App\Api\V1\Repositories\Eloquent;

use App\Api\V1\Models\Pits;
use App\Contracts\Repository\IPitRepository;
use App\Api\V1\Repositories\EloquentRepository;

class PitEloquentRepository extends  EloquentRepository implements IPitRepository
{

    public function model()
    {
       return Pits::class;
    //    return "App\Api\V1\Models\Pits";
    }

   

}
