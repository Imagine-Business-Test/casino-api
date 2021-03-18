<?php

namespace App\Api\V1\Repositories\Eloquent;

use App\Api\V1\Models\Businesses;
use App\Api\V1\Models\oAuthClient;
use App\Api\V1\Models\UserAuth;
use App\Api\V1\Models\UserProfile;
use App\Api\V1\Repositories\EloquentRepository;
use App\Contracts\Repository\IBusinessRepository;
use App\Contracts\Repository\IExchangeStoreRepository;
use App\Utils\Mapper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ExchangeStoreEloquentRepository extends  EloquentRepository implements IExchangeStoreRepository
{
    public $user;
    public $userProfile;
    public $business;
    public function __construct(Businesses $business, UserAuth $user, UserProfile $userProfile)
    {
        parent::__construct();

    }

    public function model()
    {
        return Businesses::class;
    }

}
