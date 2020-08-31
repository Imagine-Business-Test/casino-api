<?php
namespace App\Http\Controllers\Api\V1;

use App\Models\ApplicationParameter;
use App\Models\User;
use App\Models\Wallet;
use Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Validator;

class UserController extends BaseController
{

    private $userModel;

    public function __construct()
    {
        $this->middleware('auth:api');

        $this->userModel = collect ( Auth::guard('api')->user() );


    }

    /**
     * @api {get} /users user list
     * @apiDescription user list
     * @apiGroup user
     * @apiPermission none
     * @apiVersion 0.1.0
     * @apiSuccessExample {json} Success-Response:
     *     HTTP/1.1 200 OK
     *     {
     *       "data": [
     *         {
     *           "id": 2,
     *           "email": "490554191@qq.com",
     *           "name": "fff",
     *           "created_at": "2015-11-12 10:37:14",
     *           "updated_at": "2015-11-13 02:26:36",
     *           "deleted_at": null
     *         }
     *       ],
     *       "meta": {
     *         "pagination": {
     *           "total": 1,
     *           "count": 1,
     *           "per_page": 15,
     *           "current_page": 1,
     *           "total_pages": 1,
     *           "links": []
     *         }
     *       }
     *     }
     */
    public function index(User $user)
    {
        if ($this->user()->role == 'admin')
        {
            $users = User::whereIn('role', ['user', 'admin'])->paginate();
        }

        else if ($this->user()->role == 'superadmin')
        {
            $users = User::paginate();
        }
        else
        {
            $users = User::paginate();
            //return $this->response->errorUnauthorized();
        }

        return $this->response->paginator($users, new UserTransformer());
    }

    /**
     * @api {get} /users/{id} user's info
     * @apiDescription user's info
     * @apiGroup user
     * @apiPermission none
     * @apiVersion 0.1.0
     * @apiSuccessExample {json} Success-Response:
     *     HTTP/1.1 200 OK
     *     {
     *       "data": {
     *         "id": 2,
     *         "email": "490554191@qq.com",
     *         "name": "fff",
     *         "created_at": "2015-11-12 10:37:14",
     *         "updated_at": "2015-11-13 02:26:36",
     *         "deleted_at": null
     *       }
     *     }
     */
    public function show($id)
    {
        $user = User::findOrFail($id);
        return $this->response->item($user, new UserTransformer());
    }
    /**
     * @api {get} /user current user info
     * @apiDescription current user info
     * @apiGroup user
     * @apiPermission JWT
     * @apiVersion 0.1.0
     * @apiSuccessExample {json} Success-Response:
     *     HTTP/1.1 200 OK
     *     {
     *       "data": {
     *         "id": 2,
     *         "email": 'user@gmail.com',
     *         "name": "foobar",
     *         "created_at": "2015-09-08 09:13:57",
     *         "updated_at": "2015-09-08 09:13:57",
     *         "deleted_at": null
     *       }
     *     }
     */
    public function userShow(Request $request)
    {
//        return Auth::guard('api')->user();
       // Get User from from auth guard or passport guard
        // Get games played
        // Get Total Wins
        // Send User name and phone


        $userModel =     $this->userModel;

//                    return response()->json( ["response" => $userModel  ]   ); // Petty response

        if($userModel->isNotEmpty())
        {


            // Get User Wallet balance also, in case there is need to compute app side

            $username = $userModel->has("name") ? $userModel->get("name") : " ";
            $user_phone = $userModel->has("phone") ? $userModel->get("phone") : " ";
            $user_id = $userModel->has("id") ? $userModel->get("id") : " ";
            $first_name = $userModel->has("othernames") ? $userModel->get("othernames") : " ";
            $last_name = $userModel->has("surname") ? $userModel->get("surname") : " ";
            $date_joined = $userModel->has("created_at") ? $userModel->get("created_at") : " ";

            $user_wallet = new Wallet();

            $user_wallet->init($user_phone);

            $balance = $user_wallet->balance;

            $userDetailsArray =
            [
                "user_id" => $user_id,
                "phone" => $user_phone,
                "username" => $username,
                "first_name" => $first_name,
                "last_name" => $last_name,
                "date_joined" => $date_joined
            ];

            // Get Game Played by User
            $user_games =   DB::table("games as g")
                            ->where(['g.user_id' =>  $user_id] ) // where user has played
                            ->selectRaw('*')
                            ->get();

            //Get Games Won by Player
            $winned_games = DB::table("games as g")
                                ->join('games_status_tracker as gst', 'gst.game_id', '=', 'g.id')
                                ->join('registration_channels as rc', 'rc.id', '=', 'g.registration_channel_id')
                                ->join('game_types as gt', 'gt.id', '=', 'g.game_type_id')
                                ->join('game_status as gs', 'gs.id', '=', 'gst.game_status_id')
                //                          ->where('gst.game_status_id' , 1 ) // get all games that got started
                                ->whereRaw(' gst.created_at in (select MAX(games_status_tracker.created_at) from games_status_tracker WHERE game_status_id = 4  group by game_id )' )
                                ->where(['g.user_id' =>  $user_id ]) // when the game is a win , user id is this user
                                ->groupBy(['gst.game_id' ])
                                ->selectRaw('g.*,  gst.*,  rc.channel_name , gt.name as game_name, gs.status')
                                ->get();

            $application_game_parameters = ApplicationParameter::where("parameter_slug", "game_play_parameters" )->get();
        Log::info("Application Parameters ======> " . json_encode($application_game_parameters ));

            $quick_play_parameters_array = [];
        if( count($application_game_parameters) > 0 )
        {
            $application_game_parameter = $application_game_parameters->first();
            $application_game_parameter_json = $application_game_parameter->application_parameter_json;
            $application_game_parameter_array = json_decode($application_game_parameter_json, true);

//            Log::info("Application Array ======> " . json_encode($application_game_parameter_array["general"]));

            $quick_play_parameters_array  = array_key_exists("general", $application_game_parameter_array ) ?                     $application_game_parameter_array["general"] : null ;

//            return response()->json(["response" => $quick_play_parameters_array]); // Petty response
        }
        else
        {
            $quick_play_parameters_array = null;
        }



            $userProfileResult =  [
                                    "user" =>   $userDetailsArray ,
                                    "game_count" => count($user_games),
                                    "win_count" => count($winned_games),
                                    "balance" => $balance,
                                    "quick_play_parameters" =>  $quick_play_parameters_array
                                  ];

            $response_message =
                                [
                                    'error' => null,
                                    'errorText' => "",
                                    'data' => $userProfileResult,
                                    'resultCode' => "10", //10 means positive success
                                    'resultText' => "User Profile successful",
                                    'resultStatus' => true

                                ];

            return response()->json($response_message);


        }
        else
        {
            // User Profile not found or collcetion is empty
            $userProfileResult =  [
                                    "user" =>   null ,
                                    "game_count" => (int) null,
                                    "win_count" => (int) null
                                ];

            $response_message =
                [
                    'error' => null,
                    'errorText' => "",
                    'data' => $userProfileResult,
                    'resultCode' => "40", // 40 means user not found
                    'resultText' => "Error, user profile not found.",
                    'resultStatus' => false
                ];

            return response()->json($response_message);

        }

    }
    
    /**
     * @api {post} create a user
     * @apiDescription create a user
     * @apiGroup user
     * @apiPermission none
     * @apiVersion 0.1.0
     * @apiParam {Email}  email   email[unique]
     * @apiParam {String} password   password
     * @apiParam {String} name      name
     * @apiParam {Date}  birthdate  birthdate
     * @apiParam {String} role   role
     * @apiParam {String} active      active
     * @apiSuccessExample {json} Success-Response:
     *     HTTP/1.1 200 OK
     *     {
     *         token: eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJzdWIiOjEsImlzcyI6Imh0dHA6XC9cL21vYmlsZS5kZWZhcmEuY29tXC9hdXRoXC90b2tlbiIsImlhdCI6IjE0NDU0MjY0MTAiLCJleHAiOiIxNDQ1NjQyNDIxIiwibmJmIjoiMTQ0NTQyNjQyMSIsImp0aSI6Ijk3OTRjMTljYTk1NTdkNDQyYzBiMzk0ZjI2N2QzMTMxIn0.9UPMTxo3_PudxTWldsf4ag0PHq1rK8yO9e5vqdwRZLY
     *     }
     * @apiErrorExample {json} Error-Response:
     *     HTTP/1.1 400 Bad Request
     *     {
     *         "email": [
     *             "Email has been registered by others"
     *         ],
     *     }
     */
    public function store(Request $request)
    {
        // forbidden
        if ($this->user()->role == 'user') {
            return $this->response->errorForbidden();
        }

        $validator = Validator::make($request->all(), [
            'email'       => 'required|email|unique:users',
            'name'        => 'required|min:3',
            'password'    => 'required|confirmed|min:3',
            'birthdate'   => 'nullable|date',
            'role'        => 'required|string',
            'active'      => 'required'
        ]);

        if ($validator->fails()) {
            return $this->errorBadRequest($validator);
        }

        $active = (int)($request->active === 'true');

        $attributes = [
            'email' => $request->get('email'),
            'name' => $request->get('name'),
            'password' => app('hash')->make($request->get('password')),
            'created_at' => \Carbon\Carbon::now('Asia/Jakarta'),
            'updated_at' => \Carbon\Carbon::now('Asia/Jakarta'),
            'birthdate' => $request->birthdate,
            'role' => $request->role,
            'active' => $active
        ];
        $user = User::create($attributes);

        return $this->response->item($user, new UserTransformer());
    }

    /**
     * @api {put} /user/id update user
     * @apiDescription update user
     * @apiGroup user
     * @apiPermission JWT
     * @apiVersion 0.1.0
     * @apiParam {String} old_password          
     * @apiParam {String} password              
     * @apiParam {String} password_confirmation 
     * @apiSuccessExample {json} Success-Response:
     *     HTTP/1.1 204 No Content
     * @apiErrorExample {json} Error-Response:
     *     HTTP/1.1 400 Bad Request
     *     {
     *         "password": [
     *             "The password entered twice is inconsistent",
     *             "Old and new passwords can not be the same"
     *         ],
     *         "password_confirmation": [
     *             "The password entered twice is inconsistent"
     *         ],
     *         "old_password": [
     *             "wrong password"
     *         ]
     *     }
     */
    public function update($id, Request $request)
    {
        // forbidden
        if ($this->user()->role == 'user') {
            return $this->response->errorForbidden();
        }
        $user = User::find($id);
        if (! $user) {
            return $this->response->errorNotFound();
        }
        if ($request->password != "") {
          $validator = \Validator::make($request->input(), [
              'email'                 => 'required|min:3|email|unique:users,email,'. $id,
              'name'                  => 'required|min:3|max:100',
              'password'              => 'required|confirmed|min:3',
              'role'                  => 'required|string',
              'birthdate'             => 'nullable|date',
              'active'                => 'required'
          ]);
        } 
        else {
          $validator = \Validator::make($request->all(), [
              'email'                 => 'required|min:3|email|unique:users,email,'. $id,
              'name'                  => 'required|min:3|max:100',
              'role'                  => 'required|string',
              'birthdate'             => 'nullable|date',
              'active'                => 'required'
          ]);
        }
        if ($validator->fails()) {
            return $this->errorBadRequest($validator);
        }

        $active = (int)($request->active === 'true');

        $user->name = $request->name;
        $user->email = $request->email;
        $user->role = $request->role;
        $user->birthdate = $request->birthdate;
        $user->active = $active;
        
        $user->updated_at = \Carbon\Carbon::now('Asia/Jakarta');
        if ($request->password != "") {
            $user->password = app('hash')->make($request->password);
        }
        $user->save();
        return $this->response->item($user, new UserTransformer());
    }

    /**
     * @api {put} /user/password update password
     * @apiDescription update password
     * @apiGroup user
     * @apiPermission JWT
     * @apiVersion 0.1.0
     * @apiParam {String} old_password          
     * @apiParam {String} password              
     * @apiParam {String} password_confirmation 
     * @apiSuccessExample {json} Success-Response:
     *     HTTP/1.1 204 No Content
     * @apiErrorExample {json} Error-Response:
     *     HTTP/1.1 400 Bad Request
     *     {
     *         "password": [
     *             "The password entered twice is inconsistent",
     *             "Old and new passwords can not be the same"
     *         ],
     *         "password_confirmation": [
     *             "The password entered twice is inconsistent"
     *         ],
     *         "old_password": [
     *             "wrong password"
     *         ]
     *     }
     */
    public function updatePassword(Request $request)
    {
        $validator = \Validator::make($request->all(), [
            'old_password' => 'required',
            //'password' => 'required|confirmed|different:old_password',
            'password' => 'required|confirmed',
            'password_confirmation' => 'required|same:password',
        ]);
        if ($validator->fails()) {
            return $this->errorBadRequest($validator);
        }
        $user = $this->user();

        $auth = Auth::once([
            'email' => $user->email,
            'password' => $request->get('old_password'),
        ]);
        
        if (! $auth) {
            return $this->response->errorUnauthorized();
        }
        
        $password = app('hash')->make($request->get('password'));
        $user->update(['password' => $password]);
        return $this->response->noContent();
    }

    /**
     * @api {put} /user/password update profile
     * @apiDescription update profile
     * @apiGroup user
     * @apiPermission JWT
     * @apiVersion 0.1.0
     * @apiParam {String} name          
     * @apiParam {Date} birthdate              
     * @apiSuccessExample {json} Success-Response:
     *     HTTP/1.1 204 No Content
     * @apiErrorExample {json} Error-Response:
     *     HTTP/1.1 400 Bad Request
     *     {
     *         "name": [
     *             "Name is not valid"
     *         ],
     *         "birthdate": [
     *             "Birthdate is not valid"
     *         ]
     *     }
     */
    public function updateProfile(Request $request)
    {
        $validator = \Validator::make($request->all(), [
            'name' => 'required|string|min:3|max:50',
            'birthdate'   => 'nullable|date'
        ]);

        if ($validator->fails()) {
            return $this->errorBadRequest($validator);
        }

        $user = $this->user();
        $user->updated_at = \Carbon\Carbon::now('Asia/Jakarta');
        $user->name = $request->name;
        $user->birthdate = $request->birthdate;
        $user->save();

        return $this->response->item($user, new UserTransformer());
    }

    public function destroy($id)
    {
        // forbidden
        if ($this->user()->role == 'user') {
            return $this->response->errorForbidden();
        }
        $user = User::find($id);
        if (! $user) {
            return $this->response->errorNotFound();
        }
        // $user->delete();
        $user->forceDelete();
        return $this->response->item($user, new UserTransformer());
    }
    
}
