<?php


namespace App\Api\V1\Controllers;

use App\Api\V1\Models\AdminAuth;
use App\Api\V1\Models\AdminProfile;
use App\Api\V1\Controllers\BaseController;
use Carbon\Carbon;
use Ixudra\Curl\Facades\Curl;
use App\Api\V1\Models\oAuthClient;
// use App\Libraries\Encryption;
// use GuzzleHttp\Client;
use Illuminate\Http\Request;
// use App\Transformers\AuthorizationTransformer;
// use App\Jobs\SendRegisterEmail;
// use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
// use Dingo\Api\Routing\Helpers;
use App\Http\Controllers\Controller;
use Dingo\Blueprint\Annotation\Transaction;
use Exception;
use Illuminate\Auth\Access\Response;

// use Dingo\Api\Exception\ValidationHttpException;
use Illuminate\Support\Facades\Validator;

class AdminAuthController extends BaseController
{


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

            //Log neccessary status detail(s) for debugging purpose.
            Log::info("logging error" . $validator);

            //send nicer error to the user
            $response_message = $this->customHttpResponse(401, 'Incorrect login details.');
            return response()->json($response_message);
        }

        $username = $request->get('username');
        $password = $request->get('password');

        $user = AdminAuth::from('admin_auth as a')
            ->select(['a.id', 'a.username', 'a.password', 'b.*'])
            ->leftJoin('admin_profile as b', 'a.id', '=', 'b.user_id')
            ->where('a.username', '=', $username)
            ->limit(1)
            ->get();
        Log::info("user returned data" . $user);
        if (count($user) > 0) {
            $user = $user->first();
            // Log::info("check password " . Hash::check($password, $user->password));
            if (Hash::check($password, $user->password)) {
                $userID = $user->id;
                $username = $user->username;
                $password = $user->password;
                $phone = $user->password;

                try {
                    $TokenResponse = $this->getTokenByCurl($userID, $username, $password);
                    //Log neccessary status detail(s) for debugging purpose.
                    Log::info("user does not exist");
                    Log::info($TokenResponse);

                    //send nicer data to the user
                    $response_message = $this->customHttpResponse(200, 'Login successful. Token generated.', $TokenResponse);
                    return response()->json($response_message);
                } catch (Exception $th) {
                    //Log neccessary status detail(s) for debugging purpose.
                    Log::info("user oauth authentication error");
                    Log::info($th);

                    //send nicer data to the user
                    $response_message = $this->customHttpResponse(401, 'Client authentication failed.');
                    return response()->json($response_message);
                }
            } else {

                //Log neccessary status detail(s) for debugging purpose.
                Log::info("user does not exist" . $user);

                //send nicer error to the user
                $response_message = $this->customHttpResponse(401, 'User does not Exist.');
                return response()->json($response_message);
            }
        }
    }

    public function index()
    {
        $store = AdminAuth::from('admin_auth as a')
            ->select(['a.username', 'b.*'])
            ->leftJoin('admin_profile as b', 'a.id', '=', 'b.user_id')
            ->limit(30)
            ->get();
        return $store;
    }


    public function show(Request $request, $userId)
    {
        $store = AdminAuth::from('admin_auth as a')
            ->select(['a.username', 'b.*'])
            ->leftJoin('admin_profile as b', 'a.id', '=', 'b.user_id')
            ->where('a.id', '=', $userId)
            ->limit(3)
            ->get();
        return $store;
    }

    public function store(Request $request)
    {
        $validator = Validator::make(
            $request->input(),
            [
                'username' => 'required',
                'surname' => 'required',
                'firstname' => 'required',
                'email' => 'required'
            ]
        );


        if ($validator->fails()) {

            //Log neccessary status detail(s) for debugging purpose.
            Log::info("logging error" . $validator);

            //send nicer error to the user
            $response_message = $this->customHttpResponse(401, 'Incorrect Details. All fields are required.');
            return response()->json($response_message);
        }

        $username = $request->get('username');
        $password = $request->get('password');
        $surname = $request->get('surname');
        $firstname = $request->get('firstname');
        $email = $request->get('email');
        $phone = $request->get('phone');

        DB::beginTransaction();
        try {
            $auth = AdminAuth::create([
                'username' => $username,
                'password' => Hash::make($password),
                'role' => 3,
            ]);

            $profile = AdminProfile::create([
                'user_id' => $auth->id,
                'surname' => $surname,
                'firstname' => $firstname,
                'phone' => $phone,
                'email' => $email
            ]);
            Log::info("phone: " . $phone);
            $oauth_client = new oAuthClient();
            $oauth_client->user_id = $auth->id;
            // $oauth_client->id = $phone;
            $oauth_client->name = $username;
            $oauth_client->secret = base64_encode(hash_hmac('sha256', $password, 'secret', true));
            $oauth_client->password_client = 1;
            $oauth_client->personal_access_client = 0;
            $oauth_client->redirect = '';
            $oauth_client->revoked = 0;
            $oauth_client->save();

            $message =  "User client for OAuth successfully created";
            Log::info(Carbon::now()->toDateTimeString() . " => " .  $message);


            /**
             *   If the floww can reach here, then everything is fine
             *   just commit and send success response back 
             */
            DB::commit();
            //send nicer data to the user
            $response_message = $this->customHttpResponse(200, 'Registration successful.');
            return response()->json($response_message);
        } catch (\Throwable $th) {

            DB::rollBack();

            //Log neccessary status detail(s) for debugging purpose.
            Log::info("One of the DB statements failed. Error: " . $th);

            //send nicer data to the user
            $response_message = $this->customHttpResponse(500, 'Transaction Error.');
            return response()->json($response_message);
        }
    }

    public function getTokenByCurl($userID, $username, $password)
    {
        Log::info('Fass' . json_encode([$userID, $username, $password]));

        $BaseEndPoint =  url('/'); // Base Url , basically.
        Log::info('url' . url('/'));
        $CurrentEndpoint = "/oauth/token";

        $FullEndPoint =  $BaseEndPoint . $CurrentEndpoint;
        Log::info('urlfull' . url($FullEndPoint));
        $TokenResponse  = Curl::to($FullEndPoint)
            ->withData([
                "client_id" =>   1,
                "client_secret" => '8XerBsl/FlskG13nu1NVqbdGuGO6gzrXTvQssZMEIfk=',
                "grant_type" => "password",
                "username" =>   'mpa',
                "password" =>    'nelson'
            ])
            ->asJson()
            ->enableDebug('/xDuraLog.txt')
            ->post();

        Log::info('token: ' . json_encode($TokenResponse));

        // if (property_exists($TokenResponse, "access_token")) {
        if ($TokenResponse) {
            return ['response' =>  $TokenResponse];
        } else {
            throw new Exception("Client does not exist", 1);
        }
    }
}
