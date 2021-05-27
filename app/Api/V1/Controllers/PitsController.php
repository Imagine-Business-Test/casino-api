<?php


namespace App\Api\V1\Controllers;

use App\Contracts\Repository\IPitRepository;
use App\Api\V1\Controllers\BaseController;
use App\Contracts\Repository\IPitTypesRepository;
use Illuminate\Http\Request;

use App\Http\Controllers\Controller;
use App\Utils\PitMapper;
use Illuminate\Support\Facades\Validator;
use Dingo\Api\Routing\Helpers;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Laravel\Passport\Passport;

class PitsController extends BaseController
{
    use Helpers;

    protected $pitRepo;
    protected $pitTypesRepo;

    public function __construct(IPitRepository $pitRepo, IPitTypesRepository $pitTypesRepo)
    {

        $this->pitRepo = $pitRepo;
        $this->pitTypesRepo = $pitTypesRepo;
    }

    public function getAllPitTypes()
    {
        $result = $this->pitTypesRepo->findAll();
        $response_message = $this->customHttpResponse(200, 'Success.', $result);
        return response()->json($response_message);
    }
    public function getAll()
    {
        $result = $this->pitRepo->findAll();
        $response_message = $this->customHttpResponse(200, 'Success.', $result);
        return response()->json($response_message);
    }

    public function findOne($id)
    {
        $result = $this->pitRepo->find($id);
        $response_message = $this->customHttpResponse(200, 'Success.', $result);
        return response()->json($response_message);
    }

    public function create(Request $request)
    {
        $validator = Validator::make(
            $request->input(),
            [
                'name' => 'required',
                'pit_boss_id' => 'required',
                'game_type' => 'required'
            ]
        );

        $detail = $request->input();
        $user = $request->user('api');

        $detail['user_id'] = $user->id;
        $detail['business_id'] = $user->business_id;


        if ($validator->fails()) {

            //send nicer error to the user
            $response_message = $this->customHttpResponse(401, 'Check details. Some fields are required');
            return response()->json($response_message);
        }
        try {

            DB::beginTransaction();

            $dataToDB = PitMapper::toPit($detail);
            $newPit = $this->pitRepo->add($dataToDB);
            DB::commit();
            $response_message = $this->customHttpResponse(200, 'Successful.', $newPit);
            return response()->json($response_message);
        } catch (\Throwable $th) {
            DB::rollBack();
            Log::info($th);
            $response_message = $this->customHttpResponse(500, 'DB error.');
            return response()->json($response_message);
        }
    }
}
