<?php


namespace App\Api\V1\Controllers;


use App\Api\V1\Models\User;
use App\Contracts\Repository\IBusinessRepository;
use App\Contracts\Repository\IChipVaultRepository;
use App\Http\Controllers\Api\V1\SMSGatewayController;
use Ixudra\Curl\Facades\Curl;
use App\Contracts\Repository\IUserRepository;
use App\Helper\UserScope;
use App\Plugins\PUGXShortId\Shortid;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Exception;
use Illuminate\Support\Facades\Validator;


class VaultController extends BaseController
{

    private $chipVaultRepo;

    public function __construct(IChipVaultRepository $chipVaultRepo)
    {
        $this->chipVaultRepo = $chipVaultRepo;
    }


    public function receive(Request $request)
    {

        $detailz = $request->input();

        // Log::info("logging raw");
        // Log::info(json_encode($detailz));


        $validator = Validator::make(
            $request->input(),
            [
                'chips' => 'required',
            ]
        );

        $user = $request->user('api');

        if ($validator->fails()) {

            //send nicer error to the user
            $response_message = $this->customHttpResponse(401, 'Incorrect Details. All fields are required.');
            return response()->json($response_message);
        }


        try {


            $detail = $request->input('chips');
            $descr = $request->input('descr');
            $totalQty = 0;
            $totalValue = 0;
            $detailed = [];

            foreach ((array) $detail as $chip) {
                $chip = (object) $chip;

                $totalQty += $chip->qty;
                $amount = $chip->qty * $chip->value;
                $totalValue += $amount;

                $chip->total_value = $amount;
                $detailed[] = $chip;
            }

            $vaultInfo = [
                'total_qty' => $totalQty,
                'total_value' => $totalValue,
                'comment' => $descr,
                'detail' => $detailed,

            ];

            // Log::info("Total Value =  " . json_encode($totalValue));
            // Log::info(json_encode($detailed));

            $q1 = $this->chipVaultRepo->receive($vaultInfo);
            $resonse = json_decode($q1->getContent());

            if ($resonse->status_code === 200) {

                $response_message = $this->customHttpResponse(200, "Entity added successfully Although email was sent as it's disabled.");
                return response()->json($response_message);
            } else {

                //send nicer data to the user
                $response_message = $this->customHttpResponse(500, 'Transaction Error.');
                return response()->json($response_message);
            }
        } catch (\Throwable $th) {

            //send nicer data to the user
            $response_message = $this->customHttpResponse(500, 'Transaction Error.');
            return response()->json($response_message);
        }
    }
}
