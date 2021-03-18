<?php


namespace App\Api\V1\Controllers;


use App\Api\V1\Models\User;
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


class UserController extends BaseController
{

    private $userRepo;

    public function __construct(IUserRepository $userRepo)
    {
        $this->userRepo = $userRepo;
    }


    public function findAll()
    {
        $result = $this->userRepo->findAll();
        return $result;
    }


    public function find($id)
    {
        $result = $this->userRepo->find($id);
        return $result;
    }

    public function logout(Request $request)
    {
        $token = $request->user('api')->token();
        $token->revoke();

        //send nicer data to the user
        $response_message = $this->customHttpResponse(200, 'Logged out successful.');
        return response()->json($response_message);
    }


    public function login(Request $request)
    {
        $validator = Validator::make(
            $request->input(),
            [
                'username' => 'required',
                'password' => 'required'
            ]
        );


        if ($validator->fails()) {

            //send nicer error to the user
            $response_message = $this->customHttpResponse(401, 'Incorrect login details.');
            return response()->json($response_message);
        }

        $username = $request->get('username');
        $passwordPlain = $request->get('password');

        $user = $this->userRepo->showByUsername($username);
        if (count($user) > 0) {
            $user = $user->first();

            if (Hash::check($passwordPlain, $user->password)) {
                $userID = $user->id;
                $username = $user->username;
                $password = $user->password;
                $phone = $user->password;

                try {
                    $scope = UserScope::get($user->role);
                    $TokenResponse = $this->getTokenByCurl($userID, $username, $passwordPlain, $scope);

                    // $accessToken = $user->createToken("Personal Access Client")->accessToken;
                    $result = [
                        'business' => [
                            'id' => $user->biz_id
                        ],
                        'token' => $TokenResponse->access_token,
                        'current_user' => $this->pruneSensitive($user)
                    ];
                    //send nicer data to the user
                    $response_message = $this->customHttpResponse(200, 'Login successful. Token generated.', $result);
                    return response()->json($response_message);
                } catch (Exception $th) {
                    Log::info("aa");
                    Log::info($th->getMessage());
                    //send nicer data to the user
                    $response_message = $this->customHttpResponse(401, 'aClient authentication failed.');
                    return response()->json($response_message);
                }
            } else {
                Log::info("aa");

                //send nicer error to the user
                $response_message = $this->customHttpResponse(401, 'User does not Exist.');
                return response()->json($response_message);
            }
        } else {

            //send nicer error to the user
            $response_message = $this->customHttpResponse(401, ' User detail does not Exist.');
            return response()->json($response_message);
        }
    }



    public function showAll()
    {
        $result = $this->userRepo->showAll();
        return $result;
    }


    public function show($userId)
    {
        // Log::info($userId);
        $result = $this->userRepo->show($userId);
        return $result;
    }

    public function register(Request $request)
    {

        $validator = Validator::make(
            $request->input(),
            [
                'username' => 'required',
                'firstname' => 'required',
                'email' => 'required'
            ]
        );

        $user = $request->user('api');

        if ($validator->fails()) {

            //Log neccessary status detail(s) for debugging purpose.
            // Log::info("logging error" . json_encode($validator->errors()) );

            //send nicer error to the user
            $response_message = $this->customHttpResponse(401, 'Incorrect Details. All fields are required.');
            return response()->json($response_message);
        }


        try {


            $detail = $request->input();
            $user = $request->user('api');

            $detail['business_id'] = $user->business_id;
            $password = Shortid::generate(10, "0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ@&");

            Log::info("logging passport {$password}");
            $detail['password'] = Hash::make($password);
            $detail['plain_password'] = $password;


            //check email and full name exist
            $check = $this->userRepo->nameByEmailExist($detail);

            if (!is_null($check) && !empty($check)) {

                $response_message = $this->customHttpResponse(405, 'User already exist.');
                return response()->json($response_message);
            }


            //check username exist
            $checkUsername = $this->userRepo->nameByUsernameExist($detail);
            if (!is_null($checkUsername) && !empty($checkUsername)) {

                $response_message = $this->customHttpResponse(405, 'User already exist.');
                return response()->json($response_message);
            }



            $q1 = $this->userRepo->register($detail);
            $resonse = json_decode($q1->getContent());

            if ($resonse->status_code === 200) {
                $thisUser = $resonse->data->user;

                //Send mail here

                // $mailRes = $mailer->init($detail);
                // $mailRes = (object) $mailRes;
                // // Log::info("logging response after mail");
                // // Log::info(json_encode($mailRes));
                // //send nicer data to the user
                // if ($mailRes->status == 200) {
                //     $response_message = $this->customHttpResponse(200, 'Entity added successfully with Email.');
                //     return response()->json($response_message);
                // }



                try {
                    //Send SMS here

                    // $sms_gateway = new  SMSGatewayController();
                    // $sms_gateway->triggerSMS( $user->phone, "SMS Verification Code: ". $sms_code  );


                } catch (\Exception $e) {
                    // Log::info("Error in SMS Code send via Email for $full_name , phone number , email =====> ".     $user->email . " $user->phone ====>  " . $e->getMessage()    );
                }

                $response_message = $this->customHttpResponse(200, 'Entity added successfully but Email was not sent due to error. Check api Log for details.');
                return response()->json($response_message);
            } else {
                //Log neccessary status detail(s) for debugging purpose.
                Log::info("aaOne of the DB statements failed. Error: " . $resonse->message);

                //send nicer data to the user
                $response_message = $this->customHttpResponse(500, 'Transaction Error.');
                return response()->json($response_message);
            }
        } catch (\Throwable $th) {



            //Log neccessary status detail(s) for debugging purpose.
            Log::info("One of the DB statements failed. Error: " . $th);

            //send nicer data to the user
            $response_message = $this->customHttpResponse(500, 'Transaction Error.');
            return response()->json($response_message);
        }
    }


    // helper functions

    public function pruneSensitive($arr)
    {
        unset($arr['password']);
        return $arr;
    }

    public function getTokenByCurl($userID, $username, $password, $scope)
    {

        $BaseEndPoint =  url('/'); // Base Url , basically.
        $CurrentEndpoint = "/oauth/token";
        $FullEndPoint =  $BaseEndPoint . $CurrentEndpoint;

        try {
            $TokenResponse  = Curl::to($FullEndPoint)
                ->withData([
                    "client_id" =>   $userID,
                    "client_secret" => base64_encode(hash_hmac('sha256', $password, 'secret', true)),
                    "grant_type" => 'password',
                    "scope" => $scope,
                    "username" =>   $username,
                    "password" =>    $password
                ])
                ->asJson()
                ->post();

            if ($TokenResponse and property_exists($TokenResponse, "access_token")) {
                return $TokenResponse;
            } else {
                throw new Exception("Client does not exist", 1);
            }
        } catch (\Throwable $th) {
            Log::info("TokenResponse catch " . $th->getMessage());
            throw new Exception("Client does not exist2", 1);
        }
    }

    public function create_random_number($lenght = 0)
    {
        $length = empty($lenght) ? 10 : $lenght;
        $characters = '123456789';
        $string = '';
        for ($p = 0; $p < $length; $p++) {
            $string .= $characters[mt_rand(0, strlen($characters) - 1)];
        }
        return $string;
    }
}
