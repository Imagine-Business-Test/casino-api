<?php


namespace App\Api\V1\Controllers;

use App\Api\V1\Models\AdminRole;
use App\Contracts\Repository\IChipRepository;
use App\Api\V1\Controllers\BaseController;
use App\Api\V1\Models\PitTypes;
use App\Api\V1\Models\UserAuth;
use App\Contracts\Repository\IPitEventLog;
use App\Contracts\Repository\IPitTypesRepository;
use Illuminate\Http\Request;

use App\Http\Controllers\Controller;
use Dingo\Api\Routing\Helpers;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Laravel\Passport\Passport;

class ReportController extends BaseController
{
    use Helpers;

    protected $admin;
    protected $pitEventLogRepo;
    protected $pitTypesRepo;

    public function __construct(Request $request, IPitEventLog $pitEventLogRepo, IPitTypesRepository $pitTypesRepo, UserAuth $user)
    {

        $this->pitEventLogRepo = $pitEventLogRepo;
        $this->pitTypesRepo = $pitTypesRepo;
    }

    public function generateReport(Request $request)
    {

        //prepare validation rules
        $validationArray = [

            'start' => 'sometimes|required|date_format:Y/m/d',
            'end' => 'sometimes|required|date_format:Y/m/d',

        ];

        //apply validation rule array above to a custom validator    
        $validator = Validator::make(
            $request->input(),
            $validationArray
        );

        //check if validation fails and halt execution with nice message
        if ($validator->fails()) {

            $errors = $validator->errors();

            $response_message = $this->customHttpResponse(401, 'Incorrect details.', $errors);
            return response()->json($response_message);
        }

        $pitTypes = $this->pitTypesRepo->findAll();

        if ($request->has('start') && $request->has('end')) {
            $start = $request->input('start');
            $end = $request->input('end');
            $salesVolRes = $this->pitEventLogRepo->getSalesVolume($pitTypes, $start, $end);
            $gameWinRes = $this->pitEventLogRepo->getGameWins($pitTypes, $start, $end);
        } else {
            $salesVolRes = $this->pitEventLogRepo->getSalesVolume($pitTypes);
            $gameWinRes = $this->pitEventLogRepo->getGameWins($pitTypes);
        }

        $headers = [];
        foreach ($pitTypes as $item) {
            $headers[] = $item->name;
        }

        $result = [
            'sales_volume' => ['headers' => $headers, 'data' => $salesVolRes],
            'game_wins' => ['headers' => $headers, 'data' => $gameWinRes],
        ];
        $response_message = $this->customHttpResponse(200, 'Success.', $result);
        return response()->json($response_message);
    }



    public function getReport(Request $request)
    {
        $generalReport = $this->pitEventLogRepo->getAllReport();
        $response_message = $this->customHttpResponse(200, 'Success.', $generalReport);
        return response()->json($response_message);
    }
}
