<?php
namespace App\Http\Controllers\Api\V1;

//use App\Models\Answer;
use App\Http\Controllers\FundTransfer;
use App\Libraries\Authenticate;
use App\Models\AccessToken;
use App\Models\ActivityLog;
use App\Models\BankCode;
use App\Models\BotAction;
use App\Models\Game;
use App\Models\GameDetail;
use App\Models\GameStatusTracker;
use App\Models\PayStack;
use App\Models\Purchase;
use App\Models\Question;
use App\Models\RegistrationChannel;
use App\Models\Reward;
use App\Models\Score;
use App\Models\Wallet;
use App\Models\WebPay;
use App\Models\Withdrawal;
use App\Notifications\RegistrationSuccessful;
use App\oAuthClient;
use Carbon\Carbon;
use App\Libraries\Encryption;
use Curl;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use App\Models\Authorization;
use App\Models\User;
use App\Transformers\AuthorizationTransformer;
use App\Jobs\SendRegisterEmail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
//use Ixudra\Curl;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

class DashBoardController extends BaseController
{

    public function login(Request $request)
    {

//        return response()->json( ["response" =>  Hash::make("prewinaccess")]  ); // Petty response
        $validator = Validator::make($request->input(),
            [
//              'email' => 'required|email|unique:users',
//              'name' => 'required|string',
                'phone' => 'required',
                'password' => 'required',
            ]);

        if ($validator->fails())
        {
            $response_message =
                [
                    'error' => $validator,
                    'errorText' => "Validation Error",
                    'data' => [
                        'message' => 'error in validation.'
                    ],
                    'resultCode' => "55", // 55 means error due to validation
                    'resultText' => "failure",
                    'resultStatus' => false
                ];

            return response()->json($response_message);
        }


        // Make Attempt to ensure Login
        //Check Oauth_client Table that phone number does exist
        //if exists , issue token straight
        // if not exist, Create as Oau_client first ( if phone number and password match ) , then issue token next

        $password = $request->get('password');
        $phone=  $request->get('phone');
        $phone = $this->fmtphone($phone); // Convert to international format


        $checking_existing_oauth_client  =  oAuthClient::where('id',  '=' , $phone )->get();

        if(count($checking_existing_oauth_client) > 0 )
        {
            // Run token issuance straight
            $APITokenResponse  = $this->getTokenByCurl($phone, $password);

//            return response()->json( ["response" =>  $APITokenResponse  ]  ); // Petty response

            if( array_key_exists('response', $APITokenResponse )  and !is_null($APITokenResponse['response']))
            {
                $token = property_exists($APITokenResponse['response'], "access_token") ? $APITokenResponse['response']->access_token : null ;

                $refresh = property_exists($APITokenResponse['response'], "refresh_token") ? $APITokenResponse['response']->refresh_token : null ;


                $TokenResult =  [
                                    "token" =>   $token ,
                                    "refresh" => $refresh
                                ];

            $response_message =
                                [
                                    'error' => null,
                                    'errorText' => "",
                                    'data' => [
                                                $TokenResult
                                              ],
                                    'resultCode' => "10", // 10 means positive success
                                    'resultText' => "Login token generated successfully ",
                                    'resultStatus' => true

                                ];

            return response()->json($response_message);

            }
            else
            {
                // Token Issuance is not null

                $TokenResult =  [
                                    "token" =>   null ,
                                    "refresh" => null
                                ];

                $response_message =
                    [
                        'error' => null,
                        'errorText' => "",
                        'data' => [
                                     $TokenResult
                                  ],
                        'resultCode' => "53", // 53 means login Error
                        'resultText' => "Error, incorrect phone and password. Please try again.",
                        'resultStatus' => false

                    ];

                return response()->json($response_message);

            }

        }
        else
        {
            // create oAuthClient First and make attempt to obtain token


            $oAuthResponse  = $this->createOAuthClient($phone, $password);


            if( array_key_exists("status",  $oAuthResponse  ) and ( $oAuthResponse["status"] === true ) )
            {
                // if response return true, then run getTokenByCurl and parse accordingly
                // Run token issuance straight
                $APITokenResponse  = $this->getTokenByCurl($phone, $password);

                if( array_key_exists('response', $APITokenResponse )  and !is_null($APITokenResponse['response']))
                {
                    $token = property_exists($APITokenResponse['response'], "access_token") ? $APITokenResponse['response']->access_token : null ;

                    $refresh = property_exists($APITokenResponse['response'], "refresh_token") ? $APITokenResponse['response']->refresh_token : null ;

//                return response()->json( ["response" =>  $token ]  ); // Petty response

                    $TokenResult =  [
                        "token" =>   $token ,
                        "refresh" => $refresh
                    ];

                    $response_message =
                        [
                            'error' => null,
                            'errorText' => "",
                            'data' => [
                                $TokenResult
                            ],
                            'resultCode' => "10", // 10 means positive success
                            'resultText' => "Login token generated successfully ",
                            'resultStatus' => true

                        ];

                    return response()->json($response_message);

                }
                else
                {
                    // Token Issuance is null

                    $TokenResult =  [
                        "token" =>   null ,
                        "refresh" => null
                    ];

                    $response_message =
                        [
                            'error' => null,
                            'errorText' => "",
                            'data' => [
                                $TokenResult
                            ],
                            'resultCode' => "53", // 53 means login Error
                            'resultText' => "Error, incorrect phone and password. Please try again.",
                            'resultStatus' => false

                        ];

                    return response()->json($response_message);

                }
            }
            else
            {
                //if response return false , then return response accordingly


                $TokenResult =  [
                    "token" =>   null ,
                    "refresh" => null
                ];

                $response_message =
                    [
                        'error' => null,
                        'errorText' => "",
                        'data' => [
                            $TokenResult
                        ],
                        'resultCode' => "53", // 53 means login Error
                        'resultText' => "Error, incorrect phone and password. Please try again.",
                        'resultStatus' => false
                    ];

                return response()->json($response_message);

            }

        }
    }


}