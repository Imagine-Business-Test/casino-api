<?php


namespace App\Api\V1\Controllers;


use App\Api\V1\Models\User;
use App\Contracts\Repository\IBusinessRepository;
use App\Contracts\Repository\IChipVaultRepository;
use App\Contracts\Repository\IExchangeVaultRepository;
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


class ExchangeController extends BaseController
{

    private $exchangeVaultRepo;

    public function __construct(IExchangeVaultRepository $exchangeVaultRepo)
    {
        $this->exchangeVaultRepo = $exchangeVaultRepo;
    }


    public function disburse(Request $request)
    {

        $validator = Validator::make(
            $request->input(),
            [
                'total_amount' => 'required',
                'holder_type' => 'required',
                'holder_id' => 'required',
            ]
        );

        $user = $request->user('api');

        if ($validator->fails()) {

            //send nicer error to the user
            $response_message = $this->customHttpResponse(401, 'Incorrect Details. All fields are required.');
            return response()->json($response_message);
        }




        try {


            $detail = $request->input();

            if ($request->has('vaults') && count($request->input('vaults')) > 0) {

                //use the vaults details
                $q1 = $this->exchangeVaultRepo->dispatchControlled($detail);
            } else {
                //use just the Total_amount and auto select from any random the equivalent of total_amount
                $q1 = $this->exchangeVaultRepo->dispatchAuto($detail);
            }

            $response = json_decode($q1->getContent());

            if ($response->status_code === 200) {

                $response_message = $this->customHttpResponse(200, "Entity added successfully Although email was sent as it's disabled.");
                return response()->json($response_message);
            } else {

                //send nicer data to the user
                return response()->json($response);
            }
        } catch (\Throwable $th) {

            //send nicer data to the user
            $response_message = $this->customHttpResponse(500, 'Transaction Error.');
            return response()->json($response_message);
        }
    }


    public function receive(Request $request)
    {

        $validator = Validator::make(
            $request->input(),
            [
                'vaults' => 'required',
                'holder_type' => 'required',
                'holder_id' => 'required',
            ]
        );

        $user = $request->user('api');

        if ($validator->fails()) {

            //send nicer error to the user
            $response_message = $this->customHttpResponse(401, 'Incorrect Details. All fields are required.');
            return response()->json($response_message);
        }


        try {


            $details = $request->input();

            $totalQty = 0;
            $totalValue = 0;
            $detailed = [];

            foreach ($details['vaults'] as $vault) {
                $vault = (object) $vault;

                $totalQty += $vault->qty;
                $amount = $vault->qty * $vault->value;
                $totalValue += $amount;

                $vault->total_value = $amount;
                $detailed[] = $vault;
            }
            

            $details['total_qty'] = $totalQty;
            $details['total_value'] = $totalValue;
            $details['vaults'] = $detailed;

            Log::info("Total Value =  ");
            Log::info(json_encode($details));

            $q1 = $this->exchangeVaultRepo->receive($details);
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
