<?php

namespace App\Api\V1\Repositories\Eloquent;

use App\Api\V1\Models\ChipHolder;
use App\Api\V1\Repositories\EloquentRepository;
use App\Contracts\Repository\IChipHolderRepository;
use App\Utils\Mapper;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ChipHolderEloquentRepository extends  EloquentRepository implements IChipHolderRepository
{

    private $ChipHolder;

    private $userInfo;
    public function __construct(

        ChipHolder $chipHolder
    ) {
        parent::__construct();

        $this->chipHolder = $chipHolder;
        $this->userInfo = Auth::guard('api')->user();
    }

    public function model()
    {
        return ChipHolder::class;
    }

}
