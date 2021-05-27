<?php


namespace App\Api\V1\Controllers;

use App\Api\V1\Models\AdminRole;
use App\Contracts\Repository\IChipRepository;
use App\Api\V1\Controllers\BaseController;
use App\Api\V1\Models\UserAuth;
use Illuminate\Http\Request;

use App\Http\Controllers\Controller;
use Dingo\Api\Routing\Helpers;
use Illuminate\Support\Facades\Log;
use Laravel\Passport\Passport;

class ChipsController extends BaseController
{
    use Helpers;

    protected $admin;
    protected $chipRepo;

    public function __construct(Request $request, IChipRepository $chipRepo, UserAuth $user)
    {

        $this->chipRepo = $chipRepo;
    }

    public function getAll()
    {
        $result = $this->chipRepo->findAll();
        $response_message = $this->customHttpResponse(200, 'Success.', $result);
        return response()->json($response_message);
    }

    public function findOne($id)
    {
        $result = $this->chipRepo->find($id);
        $response_message = $this->customHttpResponse(200, 'Success.', $result);
        return response()->json($response_message);
    }
}
