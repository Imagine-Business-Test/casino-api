<?php
namespace App\Http\Controllers\Api\V1;

//use App\Models\Answer;
use App\Libraries\Authenticate;
use App\Models\AccessToken;
use App\Models\ActivityLog;
use App\Models\BotAction;
use App\Models\PayStack;
use App\Models\Purchase;
use App\Models\Question;
use App\Models\RegistrationChannel;
use App\Models\Reward;
use App\Models\Score;
use App\Models\Wallet;
use App\Models\WebPay;
use App\Notifications\RegistrationSuccessful;
use Carbon\Carbon;
use App\Libraries\Encryption;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use App\Models\Authorization;
use App\Models\User;
use App\Transformers\AuthorizationTransformer;
use App\Jobs\SendRegisterEmail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Ixudra\Curl;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

class SMSGatewayController  extends BaseController
{

    //Parameter for SMS Gateway Controller

    /***
     *
     *
     *
     *
     *
     * receiver : 2347037409283
     * request_id : kldhduyejsahdftjgntjgntrhjngjggjtgj
     * message : Your 6-digit number is : 723454
     *
     *
     *
     *
     **/
    //This Controller will deal with all external calls relating to Mcash

    public $sms_gateway_sender = "PREWIN";
    private $sms_gateway_username = "prewin";
    private $sms_gateway_password = "ppswe9898";
    private $sms_gateway_base_url = "104.236.225.252/telnet/response.php";

    public function create_random_number($lenght = 0)
    {
        $length = empty($lenght) ? 10 : $lenght;
        $characters = '123456789';
        $string = '';
        for ($p = 0; $p < $length; $p++) {
            $string.=$characters[mt_rand(0, strlen($characters) - 1)];
        }
        return $string;
    }

    public function sendSMS($sms_gateway_receiver, $sms_gateway_request_id , $sms_gateway_message)
    {
        $BaseEndPoint = $this->sms_gateway_base_url;
        $CurrentEndpoint = "";
        $FullEndPoint =  $BaseEndPoint . $CurrentEndpoint;

        try
        {
            $PageResponse = Curl\Facades\Curl::to($FullEndPoint)
                ->withData([
                    "sender" => $this->sms_gateway_sender,
                    "username" => $this->sms_gateway_username,
                    "password" => $this->sms_gateway_password ,
                    "receiver" => $sms_gateway_receiver,
                    "request_id" => $sms_gateway_request_id ,
                    "message" => $sms_gateway_message,
                ] )
//                ->asJson() // In order to send x-www-form-urlencoded
                ->post();

            Log::info( $PageResponse );// This is a string
            if(!empty($PageResponse) and !is_null($PageResponse) and (trim($PageResponse) === "Success" ) )
            {
                return ["status" => true ];

            }
            else
            {
                return ["status" => false ];
            }
        }
        catch(\Exception $e)
        {
            return ["status" => false ];
        }


    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function testSMSSend(Request $request)
    {

        $sms_receiver = $this->fmtphone( $request->input('sms_receiver') );
        $sms_request_id = $this->create_random_number(20);
        $sms_message = "Your 6-digit number is : " .  $this->create_random_number(6);

//        Log::info(json_encode(["From testSMSSend", $sms_receiver, $sms_request_id , $sms_message ]));

        try
        {
            $response  = $this->sendSMS($sms_receiver,$sms_request_id, $sms_message  );
            Log::info(json_encode(["From testSMSSend2", $response  ]));
            if($response["status"])
            {
                //Give positive response
                return response()->json(["status" => "Message Successfully Sent" ], 200 );
            }
            else
            {
                //Give Negative response
                return response()->json(["status" => "Message not Sent" ], 200 );
            }
        }
        catch(\Exception $e)
        {
            return response()->json(["status" => "Message not Sent" ], 200 );
        }
    }

    public function triggerSMS($sms_receiver, $sms_message )
    {

        $sms_receiver = $this->fmtphone( $sms_receiver );
        $sms_request_id = $this->create_random_number(20);

//        Log::info(json_encode(["From testSMSSend", $sms_receiver, $sms_request_id , $sms_message ]));

        try
        {
            $response  = $this->sendSMS($sms_receiver,$sms_request_id, $sms_message  );
            Log::info(json_encode(["From testSMSSend2", $response  ]));
            if($response["status"])
            {
                //Give positive response
                return response()->json(["status" => "Message Successfully Sent" ], 200 );
            }
            else
            {
                //Give Negative response
                return response()->json(["status" => "Message not Sent" ], 200 );
            }
        }
        catch(\Exception $e)
        {
            return response()->json(["status" => "Message not Sent" ], 200 );
        }
    }

    function fmtphone($phone)
    {

        $phoneUtil = \libphonenumber\PhoneNumberUtil::getInstance();
        try {
            $swissNumberProto = $phoneUtil->parse($phone, "NG");
            //var_dump($swissNumberProto);

            $num=$phoneUtil->format($swissNumberProto, \libphonenumber\PhoneNumberFormat::INTERNATIONAL);

            $num=str_replace(array('+',' '),'',$num);

            return($num);

        } catch (\libphonenumber\NumberParseException $e)
        {
            return null;
        }
    }


}