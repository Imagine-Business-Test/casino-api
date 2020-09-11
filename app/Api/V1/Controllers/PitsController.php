<?php


namespace App\Api\V1\Controllers;

use App\Api\V1\Models\AdminRole;
use App\Contracts\Repository\IPitRepository;
use App\Api\V1\Controllers\BaseController;
use App\Api\V1\Models\UserAuth;
use Illuminate\Http\Request;

use App\Http\Controllers\Controller;
use Dingo\Api\Routing\Helpers;
use Illuminate\Support\Facades\Log;
use Laravel\Passport\Passport;

class PitsController extends BaseController
{
    use Helpers;

    protected $admin;
    protected $pitRepo;

    public function __construct(Request $request, IPitRepository $pitRepo, UserAuth $user)
    {
    
        $this->pitRepo = $pitRepo;
    }

    public function getAll()
    {
        return $this->pitRepo->findAll();
    }
}
