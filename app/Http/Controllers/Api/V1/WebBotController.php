<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\FundTransfer;
use App\Http\Controllers\ZenithFundTransfer;
use App\Libraries\Authenticate;
use App\Libraries\TransRef;
use App\Libraries\Utilities;
use App\Models\AccessToken;
use App\Models\ActivityLog;
use App\Models\BankCode;
use App\Models\BotAction;
use App\Models\Game;
use App\Models\GameDetail;
use App\Models\GameStatusTracker;
use App\Models\PayStack;
use App\Models\Question;
use App\Models\Reward;
use App\Models\Score;
use App\Models\Wallet;
use App\Models\WebPayOnline;
use App\Models\Withdrawal;
use Carbon\Carbon;
use App\Libraries\Encryption;
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
use Ixudra\Curl;

class WebBotController extends BaseController
{

    private  $registration_channel_id;

    private  $internal_trigger_data;
    
    /**
     * @api {post} /authorizations (create a token)
     * @apiDescription create a token
     * @apiGroup Auth
     * @apiPermission none
     * @apiParam {Email} email     
     * @apiParam {String} password  
     * @apiVersion 0.2.0
     * @apiSuccessExample {json} Success-Response:
     *     HTTP/1.1 201 Created
     *     {
     *         "data": {
     *             "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJodHRwOi8vbHVtZW4tYXBpLWRlbW8uZGV1L2FwaS9hdXRob3JpemF0aW9ucyIsImlhdCI6MTQ4Mzk3NTY5MywiZXhwIjoxNDg5MTU5NjkzLCJuYmYiOjE0ODM5NzU2OTMsImp0aSI6ImViNzAwZDM1MGIxNzM5Y2E5ZjhhNDk4NGMzODcxMWZjIiwic3ViIjo1M30.hdny6T031vVmyWlmnd2aUr4IVM9rm2Wchxg5RX_SDpM",
     *             "expired_at": "2017-03-10 15:28:13",
     *             "refresh_expired_at": "2017-01-23 15:28:13"
     *         }
     *     }
     * @apiErrorExample {json} Error-Response:
     *     HTTP/1.1 401
     *     {
     *       "error": "User credential is not match"
     *     }
     */
    public function login(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required',
        ]);
        
        if ($validator->fails()) {
            return $this->errorBadRequest($validator);
        }

        // check user is active or not
        $user = User::where([
                    ['email', '=', $request->email],
                    ['active', '=', 1]
                ])->first();

        if (!$user) {
          return $this->response->errorUnauthorized();
        }
        
        $result['data'] = [
            'user' => $user
        ];

        return $this->response->array($result)->setStatusCode(201);
    }

    /**
     * @api {post} /authorizations register new user
     * @apiDescription register new user
     * @apiGroup Auth
     * @apiPermission none
     * @apiParam {String} name     
     * @apiParam {Email} email     
     * @apiParam {String} password  
     * @apiVersion 0.2.0
     * @apiSuccessExample {json} Success-Response:
     *     HTTP/1.1 201 Created
     *     {
     *         "data": {
     *             "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJodHRwOi8vbHVtZW4tYXBpLWRlbW8uZGV1L2FwaS9hdXRob3JpemF0aW9ucyIsImlhdCI6MTQ4Mzk3NTY5MywiZXhwIjoxNDg5MTU5NjkzLCJuYmYiOjE0ODM5NzU2OTMsImp0aSI6ImViNzAwZDM1MGIxNzM5Y2E5ZjhhNDk4NGMzODcxMWZjIiwic3ViIjo1M30.hdny6T031vVmyWlmnd2aUr4IVM9rm2Wchxg5RX_SDpM",
     *             "expired_at": "2017-03-10 15:28:13",
     *             "refresh_expired_at": "2017-01-23 15:28:13"
     *         }
     *     }
     * @apiErrorExample {json} Error-Response:
     *     HTTP/1.1 401
     *     {
     *       "error": "Register failed"
     *     }
     */
    public function register(Request $request)
    {
         // \Log::info(json_encode($request->all()));
         // \Log::info($request);

        $validator = Validator::make($request->input(), [
                                'email' => 'required|email|unique:users',
                                'name' => 'required|string',
                                'password' => 'required|confirmed',
                                'password_confirmation' => 'min:6|same:password',
                                "userType" =>  "nullable|",
                                "status"   =>  "nullable|",
                                "state"    => "nullable|"
        ]);

        if ($validator->fails()) {
            return $this->errorBadRequest($validator);
        }

        $password = $request->get('password');
        $email=  $request->get('email');

        $check_duplicate = Authenticate::checkDuplicateUser($email);
        if($check_duplicate)
        {
            $status = false;
            $msg = 'This email is already in use!';
//            $user = [$status, $msg];
//            $this->response->errorUnauthorized(trans('auth.incorrect'));
//            $result['data'] = [
//                'user' => $user,
//                'message' => $msg,
//                'token' => "",
//                'expired_at' => Carbon::now('Africa/Lagos')->addMinutes(config('jwt.ttl'))->toDateTimeString(),
//                'refresh_expired_at' => Carbon::now('Africa/Lagos')->addMinutes(config('jwt.refresh_ttl'))->toDateTimeString(),
//            ];
            $result['data'] = [
                                'message' => $msg,
                                'status' => $status,
                              ];
            return $this->response->array($result)->setStatusCode(404);
        }
        else
        {
            $email = $request->email;
            $password = $request->password;
            $hashed_password = Encryption::encryptString($password);

            $attributes = [
                'email' => $request->get('email'),
                'name' => $request->get('name'),
                'password' => Hash::make(Encryption::encryptString($request->password)),
                'encrypted_password' => Encryption::encryptString($request->password), //app('hash')->make($password),
//                'role' => 'user',
                'role_id' => 1,
                'status' => 1,
                //'active' => 1 // for test, lets activate automatically
            ];
            $user = User::create($attributes);

            $credentials = $request->only('email', 'password');



            // Validation failed will return 401
            if (! $token = Auth::attempt(['email' => $email, 'password' => $hashed_password])) {
                $this->response->errorUnauthorized(trans('auth.incorrect'));
            }

            // Send the email after the user has successfully registered
            dispatch(new SendRegisterEmail($user));

//            $user
            $user->userType = (string)$user->role_id;
            $user->name = $user->name;
            $user->status = (bool)( $user->status == 1 ? true : false);
            $user->createdAt =  !is_null($user->created_at)  ? $user->created_at->toDateTimeString() : "1970-01-01 00:00:00";
            $user->updatedAt = !is_null($user->updated_at) ? $user->updated_at->toDateTimeString() : "1970-01-01 00:00:00";


            $result['data'] = [
                'user' => $user,
                'token' => $token,
                'message' => 'User created successfully!',
                'expired_at' => Carbon::now('Africa/Lagos')->addMinutes(config('jwt.ttl'))->toDateTimeString(),
                'refresh_expired_at' => Carbon::now('Africa/Lagos')->addMinutes(config('jwt.refresh_ttl'))->toDateTimeString(),
            ];

            return $this->response->array($result)->setStatusCode(200);
        }




    }

    /**
     * @api {put} /authorizations/current refresh token
     * @apiDescription refresh token
     * @apiGroup Auth
     * @apiPermission JWT
     * @apiVersion 0.2.0
     * @apiHeader {String} Authorization The user's old jwt-token, value has started with Bearer
     * @apiHeaderExample {json} Header-Example:
     *     {
     *       "Authorization": "Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJzdWIiOjEsImlzcyI6Imh0dHA6XC9cL21vYmlsZS5kZWZhcmEuY29tXC9hdXRoXC90b2tlbiIsImlhdCI6IjE0NDU0MjY0MTAiLCJleHAiOiIxNDQ1NjQyNDIxIiwibmJmIjoiMTQ0NTQyNjQyMSIsImp0aSI6Ijk3OTRjMTljYTk1NTdkNDQyYzBiMzk0ZjI2N2QzMTMxIn0.9UPMTxo3_PudxTWldsf4ag0PHq1rK8yO9e5vqdwRZLY"
     *     }
     * @apiSuccessExample {json} Success-Response:
     *     HTTP/1.1 200 OK
     *     {
     *         "data": {
     *             "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJodHRwOi8vbHVtZW4tYXBpLWRlbW8uZGV1L2FwaS9hdXRob3JpemF0aW9ucyIsImlhdCI6MTQ4Mzk3NTY5MywiZXhwIjoxNDg5MTU5NjkzLCJuYmYiOjE0ODM5NzU2OTMsImp0aSI6ImViNzAwZDM1MGIxNzM5Y2E5ZjhhNDk4NGMzODcxMWZjIiwic3ViIjo1M30.hdny6T031vVmyWlmnd2aUr4IVM9rm2Wchxg5RX_SDpM",
     *             "expired_at": "2017-03-10 15:28:13",
     *             "refresh_expired_at": "2017-01-23 15:28:13"
     *         }
     *     }
     */
    public function update()
    {
        $authorization = new Authorization(Auth::refresh());
        return $this->response->item($authorization, new AuthorizationTransformer());
    }
    /**
     * @api {delete} /authorizations/current delete current token
     * @apiDescription delete current token
     * @apiGroup Auth
     * @apiPermission jwt
     * @apiVersion 0.1.0
     * @apiSuccessExample {json} Success-Response:
     *     HTTP/1.1 204 No Content
     */
    public function destroy()
    {
        Auth::logout();
        return $this->response->noContent();
    }

    /**
     * @api {post} /authorizations create a token
     * @apiDescription create a token
     * @apiGroup Auth
     * @apiPermission none
     * @apiParam {Email} email     
     * @apiParam {String} password  
     * @apiVersion 0.2.0
     * @apiSuccessExample {json} Success-Response:
     *     HTTP/1.1 201 Created
     *     {
     *         "data": {
     *             "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJodHRwOi8vbHVtZW4tYXBpLWRlbW8uZGV1L2FwaS9hdXRob3JpemF0aW9ucyIsImlhdCI6MTQ4Mzk3NTY5MywiZXhwIjoxNDg5MTU5NjkzLCJuYmYiOjE0ODM5NzU2OTMsImp0aSI6ImViNzAwZDM1MGIxNzM5Y2E5ZjhhNDk4NGMzODcxMWZjIiwic3ViIjo1M30.hdny6T031vVmyWlmnd2aUr4IVM9rm2Wchxg5RX_SDpM",
     *             "expired_at": "2017-03-10 15:28:13",
     *             "refresh_expired_at": "2017-01-23 15:28:13"
     *         }
     *     }
     * @apiErrorExample {json} Error-Response:
     *     HTTP/1.1 401
     *     {
     *       "error": "Create token failed"
     *     }
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required',
        ]);
        if ($validator->fails()) {
            return $this->errorBadRequest($validator);
        }
        $credentials = $request->only('email', 'password'); // dont use this

        $email = $request->email;
        $password = $request->password;
        $hashed_password = Encryption::encryptString($password);

        // Validation failed to return 401
        if (! $token = Auth::attempt(['email' => $email, 'password' => $hashed_password, 'status' => 1])) {
            $this->response->errorUnauthorized(trans('auth.incorrect'));
        }
        $authorization = new Authorization($token);
        $this->save_token(Auth::user()->id, $token );
//        return response()->json(Auth::user()->id);
        return $this->response->item($authorization, new AuthorizationTransformer())
            ->setStatusCode(201);
    }

    public function save_token($user_id, $token)
    {
        AccessToken::firstOrCreate([
            'user_id' => $user_id,
            'token' => $token,
            'active' => 1
        ]);
    }

    public function webCallBack(Request $request)
    {
        Log::info( "======================================= Post Attempt From Web/Mobile  =======================================" );

        $time_controller = Carbon::now()->toDateTimeString(); // Today
        $time_controller_timestamp = +Carbon::now()->timestamp; // Today
        $text_controller = "Yes"; // Today
        $response_dummy_text = '{"event":"message","token":"c848db1935f79fbbaba7ae927d8fd6fb5bfd081c968ef","uid":"2349061198650","contact":{"uid":"2347038257962","name":"Gbenga AKINBAMI","type":"user"},"message":{"dtm": "' . $time_controller_timestamp . '","uid":"9B43D0F0586253A32ACDC9DA58A49E54","cuid":"","dir":"i","type":"chat","body":{"text":"' . $text_controller . '"},"ack":"3"}}';

        $PageResponse = json_decode($response_dummy_text);
        $PageResponse = json_decode(json_encode($PageResponse), true);


        $request_callback = $request->all();


        $receiver  = $request_callback["contact"]["uid"];


        if(array_key_exists("message",  $request_callback))
        {

            Log::info( "======================================= Response from Web And Mobile =======================================" );
            $receiver  = $request_callback["contact"]["uid"];
            $receiver_name  = $request_callback["contact"]["name"];
            $response_text  = $request_callback["message"]["body"]["text"];
            $received_timestamp =  $request_callback["message"]["dtm"];
            $message_direction =  $request_callback["message"]["dir"];
            $message_type =  $request_callback["message"]["type"];
            $registration_channel_id = ( array_key_exists("registration_channel_id", $request_callback ) and is_numeric($request_callback["registration_channel_id"]) ) ?  $request_callback["registration_channel_id"] : 2   ;
            $this->registration_channel_id = $registration_channel_id;

            $this->internal_trigger_data = (   isset($request_callback["internal_trigger_data"]) and !is_null($request_callback["internal_trigger_data"]) )  ? $request_callback["internal_trigger_data"] :   [];

            Log::info( "internal_trigger_data " .  json_encode($this->internal_trigger_data) );


            $t = Carbon::now(); // Today

            $r = Carbon::createFromTimestamp($received_timestamp);//Next Monday

            $diff_in_minutes = $t->diffInMinutes($r);

            if($message_direction === "i" /*Meaning There was a message INPUT from user */)
            {


                Log::info($message_type . "MessageType "  . $r->toDateString(). " ".  $diff_in_minutes . " Diff in Minutes  " .$received_timestamp . " Timestap " . $response_text . " Text Response " .$receiver . " Phone received  " . json_encode($request->all()) );

                //Check User Table Column phone for match
                //if phone exists, check ActivityLog for last time of contact, if diffreence in time  less that 12 minutes , get last activity Action and respond. else show information about PreWin or prompt
                // If phone number does not exist, save phone number to user database and record user action to activity log
                //Move on to next Task

                //Check that User table phone for match

                $existing_user =  User::where('phone',  '=' , $receiver )->get();



                if(count($existing_user) > 0)
                {
                    $this_existing_user = $existing_user->first();

                    //check ActivityLog for last time of contact,  else show information about PreWin or prompt

                    $activity_logs =  ActivityLog::where('user_id',  '=' , $this_existing_user->id )->orderBy('created_at','desc')->get();


                    if(count($activity_logs) > 0)
                    {

                        $last_activity =  $activity_logs->first(); // Last Activity Log
                        $next_bot_action = $last_activity->next_bot_action_id;// Next Action to be taken by bot
                        $current_bot_action = $last_activity->current_bot_action_id;// Current Bot Action to be taken by bot
                        $last_activity_timestamp = $last_activity->time_initiated; // Timestamp of last Activity
                        $last_activity_date_time = Carbon::createFromTimestamp($last_activity_timestamp);// TimeStamp of last Activity converted to DateTime
                        $server_hit_datetime =  Carbon::createFromTimestamp($received_timestamp); //Timestamp whenpost request was made to server
                        $server_hit_time_difference = $last_activity_date_time ->diffInMinutes($server_hit_datetime);//
                        //Difference between the last time an activity was logged to the server and when a new request from that phone  numbr hit the server
                        $last_activity_continuation_token = $last_activity->continuation_token;
                        //continuation token to be checked all the time to ensure


                        //Write an if else gate that check that continuation_token is null
                        //if true - run the code below
                        //if false - process the continuation Token and respond accordingly

                        if(!is_null($last_activity_continuation_token))
                        {


                            //Process the continuation token and act accordingly

                            //Check that the text sent back to the server is equal to the continuation token
                            //if true, compute and continue the last activity of the user using an function or code snippet already done
                            //if false,  two things can happen :
                            //a.) Pass the user text and current_bot_action_id to the Word Processor and let the wordprocessor return positive response
                            // b) Else complain that the token does not match nor the text 'New' was send back for processing and encourage them to resend the SAME token back to the server for them to continue their last activity.



                            //Check that the text sent back to the server is equal to the continuation token
                            //if true, compute and continue the last activity of the user using an function or code snippet already done

                            if($response_text == $last_activity->continuation_token )
                            {
                                //if true, compute and continue the last activity of the user using an function or code snippet already done
                                $next_bot_action_details_array = $this->processNextBotActivity($last_activity, $response_text, $receiver_name, $this_existing_user);


                                //build text
                                $text =  $next_bot_action_details_array['response_text'];

//                                                            return response()->json(['response' => "Less than 12", "next_bot_action_detail" => $next_bot_action_details_array, 'activity_logged' => $text ]);



//                                $PR =  $this->sendWhatsappTextMessage( $request, $receiver, $text ); /*Remember to uncomment code */
                                return response()->json(['response_text' => $text ]);


                            }
                            else
                            {

                                //if false,  two things can happen :
                                //a.) If Text New is pass throughPass the user text and current_bot_action_id to the Word Processor and let the wordprocessor return positive response
                                // b) Else complain that the token does not match nor the text 'New' was send back for processing and encourage them to resend the SAME token back to the server for them to continue their last activity.

                                if(strtolower($response_text)  == "new")
                                {
                                    $text = $this->processNewConversation($this_existing_user, $receiver_name);
//                                    $PR =  $this->sendWhatsappTextMessage( $request, $receiver, $text ); /*Remember to uncomment code */
                                    return response()->json(['response_text' => $text  ]);

                                }


                            }
                            return response()->json(['status' => "Continuation Token was issued. Continuation token is $last_activity_continuation_token", "continuation_token" =>  $last_activity_continuation_token, 'response_text' => "Continuation Token was issued. Continuation token is $last_activity_continuation_token"  ]);

                        }
                        else
                        {
                            //Word Processor method acts on the text received from the user
                            //The work of the word processor is to take the next bot action and check that the user response text matches one of the expected responses for that next_bot_action as provided by the last activity log Model
                            //if the text from the user is among, then normal processing as below will continue -
                            // allow code to pass on process further

                            //However, if the text is unexpected or does not match any of the expected_responses from the bot_action tables, word processor will issue a response and will not push to the activity log

                            $response_text_result = $this->processUserResponseText($next_bot_action, $response_text, $receiver_name, $this_existing_user->id);

//                            return response()->json(['activity_logged' => $response_text, "Push_to_user" =>  $response_text_result ]);


                            //The response from processUserResponseText method will be used to decide whether code should pass on
                            //or a complain will be generated and sent to the user

                            if($response_text_result['can_proceed'])
                            {
                                //if the text from the user is among, then normal processing as below will continue - allow code to pass on process further

                                //Allow what had been happening before to  occur
                                if($server_hit_time_difference < 12)
                                {
                                    //Send the next task to user
                                    //Create Task Activity Log
                                    //Send Next Task to User. ( Not First Task )
                                    $next_bot_action_details_array = $this->processNextBotActivity($last_activity, $response_text, $receiver_name, $this_existing_user);


//                                    return  $next_bot_action_details_array ;
                                    //build text
                                    $text =  $next_bot_action_details_array['response_text'];

//                                                                return response()->json(['response' => "Less than 12", "next_bot_action_detail" => $next_bot_action_details_array, 'activity_logged' => $text ]);



//                                    $PR =  $this->sendWhatsappTextMessage( $request, $receiver, $text ); /*Remember to uncomment code */
                                    return response()->json(['response_text' => $text ]);

                                }
                                else
                                {
                                    //Send a continuation Message to user
                                    //a. create activity log that sends continuation token and options
                                    //b. call api endpoint that sends message to user to

                                    $new_activity_continuation = new ActivityLog();
                                    $new_activity_continuation->user_id  =  $this_existing_user->id;
                                    $new_activity_continuation->time_initiated  = Carbon::now()->timestamp; // Timestamp for now
                                    $new_activity_continuation->time_received  = null;
                                    $new_activity_continuation->registration_channel_id = $this->registration_channel_id;
                                    $new_activity_continuation->current_bot_action_id  = $current_bot_action;
                                    $new_activity_continuation->next_bot_action_id  = $next_bot_action;
                                    $new_activity_continuation->bot_action_parameter  = $last_activity->bot_action_parameter;
                                    $new_activity_continuation->continuation_token  = $this->create_random_number(5);
                                    $new_activity_continuation->created_at  = Carbon::now()->toDateTimeString();

                                    $last_time_human_readable = $this->minutesToTime($server_hit_time_difference);

                                    $new_activity_continuation->save();

                                    //call api endpoint that send message to user

                                    $text = "Your last response was ". $last_time_human_readable . " ago. Please send this code " .  $new_activity_continuation->continuation_token . " back to us so that you can continue from where you left or you can just type 'New' so that you start afresh.";

//                                    $PR =  $this->sendWhatsappTextMessage( $request, $receiver, $text ); /*Remember to uncomment code */
                                    return response()->json(['response_text' => $text ]);



                                }
                            }
                            else
                            {
                                //or else if the text is not among the expected responses, then a complain/warning/plus correction will be issued to the user for response

//                                $PR =  $this->sendWhatsappTextMessage( $request, $receiver, $response_text_result['text_processor_response_text'] ); /*Remember to uncomment code */

                                return response()->json(['response_text' => $response_text_result['text_processor_response_text']  ]);
                            }


                        }
                    }
                    else
                    {
                        $text = $this->processNewConversation($this_existing_user, $receiver_name);
//                        $PR =  $this->sendWhatsappTextMessage( $request, $receiver, $text ); /*Remember to uncomment code */
                        return response()->json(['response_text' => $text  ]);
                    }

                }
                else
                {
                    $text = $this->processNewConversationForNewUser($receiver, $receiver_name);
//                    $PR =  $this->sendWhatsappTextMessage( $request, $receiver, $text ); /*Remember to uncomment code */
                    return response()->json(['response_text' => $text  ]);
                }

            }

        }
    }

    /**
     * @param $this_existing_user
     * @param $receiver_name
     * @return string
     */
    public function processNewConversation($this_existing_user, $receiver_name): string
    {
//Start New Conversation.
        //Create Activity for New User
        $new_activity = new ActivityLog();
        $new_activity->user_id = $this_existing_user->id;
        $new_activity->time_initiated = Carbon::now()->timestamp; // Timestamp for now
        $new_activity->time_received = null;
        $new_activity->registration_channel_id = $this->registration_channel_id;
        $new_activity->current_bot_action_id = 1;
        $new_activity->next_bot_action_id = 2;
        $new_activity->bot_action_parameter = json_encode(['has_parameters' => false]);
        // Default Parameter value, like a null
        $new_activity->created_at = Carbon::now()->toDateTimeString();
        $new_activity->save();

        //Do first Task of Bot

        $receiver_name_without_number = preg_replace('/[0-9]+/', '', $receiver_name);

        $text = "Hello, " . $receiver_name_without_number . " ☺️! \nWelcome to PreWin Games! 🌟. \n\n\n Prewin Games allows you to provide answers to a pool of questions over a period of time. User will be answering 10 questions in 5 minutes\n\n\n You can proceed to: \n\n Demo\n fund account \n play game\n reward\nreset password\ncheck balance ";
        return $text;

    }

    /**
     * @param $receiver
     * @param $receiver_name
     * @return string
     */
    public function processNewConversationForNewUser($receiver, $receiver_name): string
    {

        $bot_parameter_array = [];
        $text_array = [];


        $new_user = new User();
        $new_user->phone = $receiver; //  $this->create_random_name(11);
        $new_user->othernames = $receiver_name;
        $new_user->registration_channel = 1;/* I know its Whatsapp */

        $new_user->save();


        $text = "Hi ☺️! \n\nWelcome to PreWin Games! 🌟.\n\nPREWIN is a unique, simple life style social game that entertains, educate and test knowledge around sports, fashion, politics,entertainment e.t.c\nSpeed and accuracy are rewarded as you are expected to answer 10 questions in 5 minutes.\n\n *Are you 18 years and above?*\n\n Please reply with: \n *Yes* \n or \n *No*";


        $bot_parameter_array['progress_tracking'] = false;
        $bot_parameter_array['current_operation'] = "process age restriction";
        $bot_parameter_array['current_sub_operation'] = "process age restriction";
        $bot_parameter_array['has_parameters'] = true;
        $bot_parameter_array['bot_response_text'] = json_encode($text);


        //Start New Conversation.
        //Create Activity for New User
        $new_activity = new ActivityLog();
        $new_activity->user_id = $new_user->id;
        $new_activity->time_initiated = Carbon::now()->timestamp; // Timestamp for now
        $new_activity->time_received = null;
        $new_activity->registration_channel_id = $this->registration_channel_id;
        $new_activity->current_bot_action_id = 12;
        $new_activity->next_bot_action_id = null;
        $new_activity->bot_action_parameter = json_encode($bot_parameter_array); // Default Parameter value, like a null
        $new_activity->created_at = Carbon::now()->toDateTimeString();
        $new_activity->save();

        //Do first Task of Bot

//        $receiver_name_without_number = preg_replace('/[0-9]+/', '', $receiver_name);
//
//        $text = "Hello, " . $receiver_name_without_number . " ☺️! \nWelcome to PreWin Games! 🌟. \n\n\n Prewin Games allows you to provide answers to a pool of questions over a period of time. User will be answering 10 questions in 5 minutes\n\n\n You can proceed to: \n\n Demo\n fund account \n play game\n reward\nreset password\ncheck balance ";
        return $text;
    }

    /**
     * @param $last_activity
     * @param $response_text
     * @param $receiver_name
     * @param $this_existing_user
     * @return array
     */
    public function processNextBotActivity($last_activity, $response_text, $receiver_name, $this_existing_user): array
    {
                    $next_bot_action_details_array = $this->computeNextBotAction($last_activity, $response_text, $receiver_name);

//                    return $next_bot_action_details_array;
//                    return response()->json(['response' => "Less than 12", "next_bot_action_detail" => $next_bot_action_details_array]);

                    //Create Task Activity Log
                    $new_activity = new ActivityLog();
                    $new_activity->user_id = $this_existing_user->id;
                    $new_activity->time_initiated = Carbon::now()->timestamp; // Timestamp for now
                    $new_activity->time_received = null;
                    $new_activity->registration_channel_id = $this->registration_channel_id;
                    $new_activity->current_bot_action_id = $next_bot_action_details_array['next_bot_action']['id'];
                    $new_activity->next_bot_action_id = $next_bot_action_details_array['next_bot_action']['next_action_id'];
                    $new_activity->bot_action_parameter = $next_bot_action_details_array['bot_action_parameter'];

                    $new_activity->created_at = Carbon::now()->toDateTimeString();

                    $new_activity->save();
                    return $next_bot_action_details_array;
    }

    /**
     * @param $last_activity
     * @param $response_text
     * @param $receiver_name
     * @return array
     */
    private function computeNextBotAction($last_activity, $response_text, $receiver_name)
    {
        //Find the current bot action first so you know what to do
        $this_current_bot_action = BotAction::find($last_activity->current_bot_action_id);

        $bot_action_detail_array  = [];
        if(!is_null($this_current_bot_action))
        {
//            $next_bot_action = $this_current_bot_action->next_action_id;
            $bot_action_parameter =  trim ( $this_current_bot_action->bot_action_parameter_template);

//            $bot_action_detail_array["next_bot_action"] = $next_bot_action;

            //Decode Parameter Json - default  json parameter is {'has_parameters' : "false"}
            //Find out if parameter json has_parameter property is true.
            //If true, check that the count is not greater than parameter property total_question_count
            // and that difference in starting_time and now is not more than time to be spent

            //IF false check the next bot action on the bot action template on the bot action table
            //If the bot action exists , get the bot action parameter and fill according to question
            // If the bot action dos not exist, default to the first Bot action
            //return

            //Decode bot action parameter from the botaction template
            $decoded_bot_parameter = json_decode($bot_action_parameter, true);

//            return $decoded_bot_parameter;

//            return $decoded_bot_parameter['has_parameters'];

            //If true, check that the count is not greater than parameter property total_question_count

            if($decoded_bot_parameter['has_parameters'])
            {
                //Find out if parameter json has_parameter property is true.
                //If true, check that the count is not greater than parameter property total_question_count
                // and that difference in starting_time and now is not more than time spent in current activity log

//                {"has_parameters":true, "total_questions": 10, "time_allowed": 10, "score" : 0,
// "time_left" : 10, "Question": "What is a Noun?", OptionA: "Stew", OptionB: "Soup", OptionC: "Fish", OptionD: "Garri", CorrectOption: "A", "questions_count": 0}

                $activity_log_decoded_bot_parameter = json_decode($last_activity->bot_action_parameter, true);

//                return $activity_log_decoded_bot_parameter;

                //Use the Last Activity log to check the current bot_action then act accordingly
                //based on the switch case that determines which of the code to be run

                //Extract the current_bot_action

                $current_bot_action_id = $this_current_bot_action->id;

                switch ($current_bot_action_id)
                {
                    case 1:
                       //
                        $returned_bot_action_parameter = $this->computeBotParameterJson($last_activity, $response_text, $receiver_name);
                        $bot_action_detail_array["bot_action_parameter"] = json_encode($returned_bot_action_parameter ) ;
                        $bot_action_detail_array["next_bot_action"] = $last_activity->next_bot_action_id; ;

                        $bot_action_detail_array["response_text"] = $returned_bot_action_parameter['bot_response_text']; ;

                        break;
                    case 2:
                       //
                        $returned_bot_action_parameter = $this->computeBotParameterJson($last_activity, $response_text, $receiver_name);
                        $bot_action_detail_array["bot_action_parameter"] = json_encode($returned_bot_action_parameter ) ;
                        $bot_action_detail_array["next_bot_action"] = $last_activity->next_bot_action_id; ;

                        $bot_action_detail_array["response_text"] = $returned_bot_action_parameter['bot_response_text']; ;

                        break;
                    case 3:

                        if(($activity_log_decoded_bot_parameter['total_questions'] <= $activity_log_decoded_bot_parameter['questions_count'] ) or ($activity_log_decoded_bot_parameter['time_left'] <=  0 ) )
                        {
                            /* Finalize and don't send New Questions to players */

                            //Find Next Bot Action and save  nex_bot_id and save parameter to be equal to it template parameter

                            $next_bot_action = BotAction::find($last_activity->next_bot_action_id);

                            if(!is_null($next_bot_action))
                            {
                                $bot_action_detail_array["bot_action_parameter"] = $next_bot_action->bot_action_parameter_template;
                                $bot_action_detail_array["next_bot_action"] = $next_bot_action ;

                                //HardCode What Happens When The Player finishes  the questions
                                //Show the Score, use emojies to beautify the response, add some 'funny reply'
                                //Save Score in score tables
                                //Compute possible wiinings based on score /*  This may not be done yet */

                                $receiver_name_without_number =  preg_replace('/[0-9]+/', '', $receiver_name);

                                $text = "☺️☺️Congrats!" . $receiver_name_without_number . ",  you have finished the question game! ⭐⭐ \n\n Your total score is  ". $activity_log_decoded_bot_parameter['score'] . ". \n 🏆 Congratulations!!\n\n You can proceed to the  login . \n\n Just reply with 'Login' ";

                                //Save Score in score tables

                                $saved_score  = new Score();
                                $saved_score->user_id = $last_activity->user_id;
                                $saved_score->score = $activity_log_decoded_bot_parameter['score'];
                                $saved_score->save();


                                $bot_action_detail_array["response_text"] = $text ;

                                return $bot_action_detail_array;
                            }

                            else
                            {
                                // If the bot action does not exist, default to the first bot action
                                //HardCode What Happens When Bot cannot Choose
                                $first_bot_action_id = 1;
                                $next_bot_action = BotAction::find($first_bot_action_id);
                                $bot_action_detail_array["bot_action_parameter"] = $next_bot_action->bot_action_parameter_template; // get the both action parameters
                                $bot_action_detail_array["next_bot_action"] = $next_bot_action;


                                $receiver_name_without_number =  preg_replace('/[0-9]+/', '', $receiver_name);

                                $text = "☺️☺️Congrats!" . $receiver_name_without_number . ",  you have finished the question game! ⭐⭐ \n\n Your total score is  ". $activity_log_decoded_bot_parameter['score'] . ". \n 🏆 Congratulations!!\n\n Please s";
                                $bot_action_detail_array["response_text"] = $text ;

                                return $bot_action_detail_array;
                            }



//                    $returned_bot_action_parameter = $this->computeBotParameterJson($last_activity, $response_text);


                        }
                        else /* Continue to Serve Questions to Player */
                        {

                            //Get one randomized Question and the answers and set it to the "Question" property and Option A, B, C and D as answers
                            //Compute the "score" by checking if answer is correct, increment by one or else leave score has is
                            //Set The Time Left for Question Session.
                            // Write the above three function in  a separate function

                            $returned_bot_action_parameter = $this->computeBotParameterJson($last_activity, $response_text, $receiver_name);

//                    return  $returned_bot_action_parameter;

                            $bot_action_detail_array["bot_action_parameter"] = json_encode($returned_bot_action_parameter ) ;

                            //Decision about activating next bot action or maintaining current bot action
                            $bot_action_detail_array["next_bot_action"] = $this_current_bot_action; ;

                            $bot_action_detail_array["response_text"] = $returned_bot_action_parameter['bot_response_text']; ;
                            //Bot Response Text must be computed dynamically

                            return $bot_action_detail_array;
                        }

                        //Done
                        break;
                    case 4:
                        //
                        $returned_bot_action_parameter = $this->computeBotParameterJson($last_activity, $response_text, $receiver_name);
                        $bot_action_detail_array["bot_action_parameter"] = json_encode($returned_bot_action_parameter ) ;
                        $bot_action_detail_array["next_bot_action"] = $last_activity->next_bot_action_id; ;

                        $bot_action_detail_array["response_text"] = $returned_bot_action_parameter['bot_response_text']; ;

                        break;
                    case 5:

                        // Create JSon Parameter
                        //Tracking Registeration Progress
                        //1. Take email from user after asking
                        //json parameter for taking email --
                        // { 'progress_tracking': 'uncompleted',
                        //   'current_operation' : 'register',
                        //   'current_sub_operation' : 'ask for email',
                        //   'validation_status': true}
                        //process a. ask for email from user
                        //b validate email and send validation token 5 digits as Prewin Pin
                        // { 'progress_tracking': 'uncompleted',
                        //   'current_operation' : 'register',
                        //   'current_sub_operation' : 'provide pin number',
                        //   'validation_status': true or false}
                        //c. Advice that Prewin is the login code for logging, Prompt for
                        // { 'progress_tracking': 'uncompleted',
                        //   'current_operation' : 'register',
                        //   'current_sub_operation' : 'prompt for login',
                        //   'validation_status': true}
                        //2. Login Attempt should be done
                        // a. Ask for Prewin pin and validate against phone number and  Prewin pin
                        // { 'progress_tracking': 'uncompleted',
                        //   'current_operation' : 'login',
                        //   'current_sub_operation' : 'validate prewin token',
                        //   'validation_status': true or false
                        //}
                        // b Announce that user is logged in and present then with options and move to next bot_action number 6
                        //{ 'progress_tracking': 'completed',
                        //   'current_operation' : 'login',
                        //   'current_sub_operation' : 'successfully logged in ',
                        //   'validation_status': true
                        //}
                        /*
                                Check that  'progress_tracking is  'completed',
                                if true, move to the next bot action
                                if false, stay in current bot action and pass code to computeNextBotAction
                         */

                        if($activity_log_decoded_bot_parameter['progress_tracking'] === "completed")
                        {/*   If complete move to next bot_action ( e.g bot_action_id =  6 )  */

                            $next_bot_action = BotAction::find($last_activity->next_bot_action_id);

                            if(!is_null($next_bot_action))
                            {
                                $returned_bot_action_parameter = $this->initiateBotParameter($last_activity->next_bot_action_id , $receiver_name, $response_text,  $last_activity->user_id );
                                $bot_action_detail_array["bot_action_parameter"] = json_encode($returned_bot_action_parameter);

                                $bot_action_detail_array["next_bot_action"] = $next_bot_action;

                                $bot_action_detail_array["response_text"] = $returned_bot_action_parameter['bot_response_text'];
                            }
                            else
                            {
                                // If the bot action does not exist, default to the first bot action
                                //HardCode What Happens When Bot cannot Choose
                                $first_bot_action_id = 1;
                                $next_bot_action = BotAction::find($first_bot_action_id);
                                $bot_action_detail_array["bot_action_parameter"] = $next_bot_action->bot_action_parameter_template; // get the both action parameters
                                $bot_action_detail_array["next_bot_action"] = $next_bot_action;


                                $receiver_name_without_number =  preg_replace('/[0-9]+/', '', $receiver_name);
                                $text = "Hello, " . $receiver_name_without_number . " ☺️! \nWelcome to PreWin Games! 🌟. \n\n\n Prewin Games allows you to provide answers to a pool of questions over a period of time. User will be answering 10 questions in 5 minutes\n\n\n You can proceed to: \n\n Demo\n fund account \n play game\n reward\nreset password\ncheck balance ";

                                $bot_action_detail_array["response_text"] = $text ;

                                return $bot_action_detail_array;
                            }
                        }
                        else
                        {/*   or else if let computeBotParameterJson act on this current bot action */

                            $returned_bot_action_parameter = $this->computeBotParameterJson($last_activity, $response_text, $receiver_name);

//                    return  $returned_bot_action_parameter;

                            $bot_action_detail_array["bot_action_parameter"] = json_encode($returned_bot_action_parameter ) ;

                            //Decision about activating next bot action or maintaining current bot action
                            $bot_action_detail_array["next_bot_action"] = $this_current_bot_action; ;

                            $bot_action_detail_array["response_text"] = $returned_bot_action_parameter['bot_response_text']; ;
                            //Bot Response Text must be computed dynamically

                            return $bot_action_detail_array;


                        }
                        break;
                    case 6:
                        /*
                                Check that  'progress_tracking is  'completed',
                                if true, move to the next bot action
                                if false, stay in current bot action and pass code to computeNextBotAction
                         */

                        if($activity_log_decoded_bot_parameter['progress_tracking'] === "completed")
                        {/*   If complete move to next bot_action ( e.g bot_action_id =  6 )  */

                            $next_bot_action = BotAction::find($last_activity->next_bot_action_id);

                            if(!is_null($next_bot_action))
                            {
                                $returned_bot_action_parameter = $this->initiateBotParameter($last_activity->next_bot_action_id , $receiver_name, $response_text,  $last_activity->user_id );
                                $bot_action_detail_array["bot_action_parameter"] = json_encode($returned_bot_action_parameter);

                                $bot_action_detail_array["next_bot_action"] = $next_bot_action;

                                $bot_action_detail_array["response_text"] = $returned_bot_action_parameter['bot_response_text'];
                            }
                            else
                            {
                                // If the bot action does not exist, default to the first bot action
                                //HardCode What Happens When Bot cannot Choose
                                $first_bot_action_id = 1;
                                $next_bot_action = BotAction::find($first_bot_action_id);
                                $bot_action_detail_array["bot_action_parameter"] = $next_bot_action->bot_action_parameter_template; // get the both action parameters
                                $bot_action_detail_array["next_bot_action"] = $next_bot_action;


                                $receiver_name_without_number =  preg_replace('/[0-9]+/', '', $receiver_name);

                                $text = "Hello, " . $receiver_name_without_number . " ☺️! \nWelcome to PreWin Games! 🌟. \n\n\n Prewin Games allows you to provide answers to a pool of questions over a period of time. User will be answering 10 questions in 5 minutes\n\n\n You can proceed to: \n\n Demo\n fund account \n play game\n reward\nreset password\ncheck balance ";

                                $bot_action_detail_array["response_text"] = $text ;

                                return $bot_action_detail_array;
                            }
                        }
                        else
                        {/*   or else if let computeBotParameterJson act on this current bot action */

                            $returned_bot_action_parameter = $this->computeBotParameterJson($last_activity, $response_text, $receiver_name);

//                    return  $returned_bot_action_parameter;

                            $bot_action_detail_array["bot_action_parameter"] = json_encode($returned_bot_action_parameter ) ;

                            //Decision about activating next bot action or maintaining current bot action
                            $bot_action_detail_array["next_bot_action"] = $this_current_bot_action; ;

                            $bot_action_detail_array["response_text"] = $returned_bot_action_parameter['bot_response_text']; ;
                            //Bot Response Text must be computed dynamically

                            return $bot_action_detail_array;


                        }
                        break;
                    case 7:

                        if($activity_log_decoded_bot_parameter['progress_tracking'] === "completed")
                        {/*   If complete move to next bot_action */
                            $reward_type =  $activity_log_decoded_bot_parameter['reward_type'];
                            $next_bot_action = BotAction::find($last_activity->next_bot_action_id);

                            if(!is_null($next_bot_action))
                            {
                                $game_unique_identifier =  sprintf('%04X%04X-%04X-%04X-%04X-%04X%04X%04X', mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(16384, 20479), mt_rand(32768, 49151), mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(0, 65535));
                                $start_time =  Carbon::now()->toDateTimeString();
                                $previous_game_status_id =  3;// this Signifies pending status

                                $user_game_play_amount = $activity_log_decoded_bot_parameter['user_game_play_amount'] ;

                                $game_play_option = ["reward_type" => $reward_type , 'game_unique_identifier' => $game_unique_identifier,
                                    'start_time' => $start_time, 'total_actual_winning' => 0, 'user_game_play_amount' =>  $user_game_play_amount, 'previous_game_status_id' => $previous_game_status_id  ];



                                $returned_bot_action_parameter = $this->initiateBotParameter($last_activity->next_bot_action_id , $receiver_name, $response_text,  $last_activity->user_id );
                                $bot_action_detail_array["bot_action_parameter"] = json_encode(array_merge($game_play_option, $returned_bot_action_parameter));

                                $bot_action_detail_array["next_bot_action"] = $next_bot_action;

                                $bot_action_detail_array["response_text"] = $returned_bot_action_parameter['bot_response_text'];

                                $get_user = User::find( $last_activity->user_id);

                                $user_wallet = new Wallet();

                                $user_wallet->init($get_user->phone);

                                // Populate Games table row first and the games_status_tracker is also populated

                                $game_data = [
//                                                'phone' => $get_user->phone,
                                                'registration_channel_id' => $this->registration_channel_id, /* For facebook */
                                                'user_id' => $get_user->id,
                                                'game_unique_identifier' =>  $game_unique_identifier ,
                                                'game_type_id' => strtolower($reward_type)  == "other pay" ?  1  : 2  ,  /* This is the id of Mega pay on the game type tabl */
//                                                'game_status_id' => 1,  /* This is the id of started on the game type tabl */
                                                'expected_winning' => ( strtolower($reward_type)  == "other pay" )  ? ( (int)$user_game_play_amount * 5 ) : ( (int)$user_game_play_amount * 10 ) , /* Amount paid ( $user_game_play_amount ) * winning factor (10) for mega pay and a5 for other pay  This value are static for now, they will dynamic later */
//                                                'actual_winning' => 0,
//                                                'score' => 0, /* We just started playing */
//                                                'wallet_balance' => $user_wallet->balance,
//                                                'total_actual_winning' =>  0,
//                                                'start_time' => $start_time,
//                                                'end_time' => null,
                                                'amount_staked' => $user_game_play_amount,  /* Amount the player used for playing the game  */
//                                                'previous_game_status_id' => $previous_game_status_id,  /* the game_status_id now  will be 0 at start of the game  */
                                                'game_state_changed' => 0,  /* The state change will e 0 at the start of the game and will change to 1 once a change occurs  */

                                ];


                                $game_id =  DB::table('games')->insertGetId($game_data);

                                $game_status_tracker_data = [
                                    'game_id' => $game_id, /* For facebook */
                                    'game_status_id' => 3,  /* This is the id of started on the game type table */
                                    'expected_winning' => ( strtolower($reward_type)  == "other pay" )  ? ( (int)$user_game_play_amount * 5 ) : ( (int)$user_game_play_amount * 10 ) , /* Amount paid ( $user_game_play_amount ) * winning factor (10) for mega pay and a5 for other pay  This value are static for now, they will dynamic later */
                                    'actual_winning' => 0,
                                    'score' => 0, /* We just started playing */
                                    'wallet_balance' => $user_wallet->balance,
                                    'total_actual_winning' =>  0,
                                    'start_time' => $start_time,
                                    'end_time' => null,
                                    'amount_staked' => $user_game_play_amount,  /* Amount the player used for playing the game  */
                                    'previous_game_status_id' => $previous_game_status_id,  /* the game_status_id now  will be 0 at start of the game  */
                                ];

                                GameStatusTracker::create($game_status_tracker_data);
                            }
                            else
                            {
                                // If the bot action does not exist, default to the first bot action
                                //HardCode What Happens When Bot cannot Choose
                                $first_bot_action_id = 1;
                                $next_bot_action = BotAction::find($first_bot_action_id);
                                $bot_action_detail_array["bot_action_parameter"] = $next_bot_action->bot_action_parameter_template; // get the both action parameters
                                $bot_action_detail_array["next_bot_action"] = $next_bot_action;


                                $receiver_name_without_number =  preg_replace('/[0-9]+/', '', $receiver_name);

                                $text = "Hello, " . $receiver_name_without_number . " ☺️! \nWelcome to PreWin Games! 🌟. \n\n\n Prewin Games allows you to provide answers to a pool of questions over a period of time. User will be answering 10 questions in 5 minutes\n\n\n You can proceed to: \n\n Demo\n Fund Account \n play game\n reward\nreset password\ncheck balance ";

                                $bot_action_detail_array["response_text"] = $text ;

                                return $bot_action_detail_array;
                            }
                        }
                        else
                        {
                            /* If process is not complete, the process will process response further */

                            $returned_bot_action_parameter = $this->computeBotParameterJson($last_activity, $response_text, $receiver_name);

//                    return  $returned_bot_action_parameter;

//                            $game_play_option  = ["reward_type" => $activity_log_decoded_bot_parameter['reward_type'] ];

                            $bot_action_detail_array["bot_action_parameter"] = json_encode(array_merge( $returned_bot_action_parameter) ) ;

                            //Decision about activating next bot action or maintaining current bot action
                            $bot_action_detail_array["next_bot_action"] = $this_current_bot_action;

                            $bot_action_detail_array["response_text"] = $returned_bot_action_parameter['bot_response_text']; ;
                            //Bot Response Text must be computed dynamically

                            return $bot_action_detail_array;

                        }
                        break;
                    case 8:

                        if(($activity_log_decoded_bot_parameter['total_questions'] <= $activity_log_decoded_bot_parameter['questions_count'] ) or ($activity_log_decoded_bot_parameter['time_left'] <=  0 ) )
                        {
                            /* Finalize and don't send New Questions to players */

                            //Find Next Bot Action and save  nex_bot_id and save parameter to be equal to it template paramete
                            $next_bot_action = BotAction::find($last_activity->next_bot_action_id);

                            if(!is_null($next_bot_action))
                            {
                                $bot_action_detail_array["bot_action_parameter"] = $next_bot_action->bot_action_parameter_template;
                                $bot_action_detail_array["next_bot_action"] = $next_bot_action ;

                                $game_details_array['game_unique_identifier'] = $activity_log_decoded_bot_parameter['game_unique_identifier'];
                                $game_details_array['question_id'] = $activity_log_decoded_bot_parameter['question_id'];
                                $game_details_array['question_number'] = $activity_log_decoded_bot_parameter['questions_count'];
                                $game_details_array['game_start_time'] = $activity_log_decoded_bot_parameter['start_time'];
                                $correct_option_activity = $activity_log_decoded_bot_parameter['correctOption'];
                                $game_details_array['correct_answer_id'] = $activity_log_decoded_bot_parameter['answer_array'][$correct_option_activity];
                                $game_details_array['chosen_answer_id'] =  $activity_log_decoded_bot_parameter['answer_array'][strtolower($response_text)];


                                //Show the Score, use emojies to beautify the response, add some 'funny reply'
                                //Save Score in score tables

                                //Compute the reward appropriate to the player based on the score and reward type
                                //'win all' reward type will be done first
                                //For 'win all' reward type, check that score is 10/10 , then take 100 naira unit and multiple by 10 and add it to the player wallet amount. Return text that such amount has been added to the the user winner. Then prompt to play the game again or not
                                //'win small small' reward: check when the the question gets to number 5,
                                // check that user scored 4 out of 5, then reward with 2.5 * 100 ( amount played for game ), when user  get to this place ( 10 questions answered or timeout ) ,
                                // then check score  of question 6 to 10, if user gets 4 out 5 also, reward user with
                                // another 2.5 * 100 ( game play amount ) is credited to the user account

                                //get Reward type from json parameter
                                //Do switch to treat each reward type
                                $game_play_option =  $activity_log_decoded_bot_parameter['reward_type'];
                                $current_user_wallet_amount = 0;// i don't even know this variable, i am calling the cops
                                $this_user = User::find($last_activity->user_id);
                                $retrieved_game_model = null; // retrieved game model

                                switch (strtolower($game_play_option))
                                {
                                    case 'mega pay':

                                        $score =  $activity_log_decoded_bot_parameter['score'];
                                        $game_unique_identifier = $activity_log_decoded_bot_parameter['game_unique_identifier'];
                                        $start_time = $activity_log_decoded_bot_parameter['start_time'];
                                        $total_actual_winning =   $activity_log_decoded_bot_parameter['total_actual_winning'];
                                        $previous_game_status_id =   $activity_log_decoded_bot_parameter['previous_game_status_id'];
                                        $user_game_play_amount = (float)$activity_log_decoded_bot_parameter['user_game_play_amount'];

                                        // Retrieve Game Model using $game_unique_identifier and get game_id
                                        $retrieved_game_model = null; // retrieved game model
                                        $game_models =  Game::where('game_unique_identifier',  '=' , $game_unique_identifier )->get();
                                        if(count($game_models) > 0)
                                        {
                                            $retrieved_game_model = $game_models->first();
                                        }

                                        // Compute to determine if player won in this last stage or not

                                        if($correct_option_activity == strtolower($response_text) )
                                        {
                                            // if answer is correct add 1 to the score and store,
                                            $score += 1;
                                            //If correct option is same as text returned ,increase score by one
                                        }

                                        // However, if score is above 10, just reset score to 10

                                        if($score >= 10)
                                        {
                                            $score = 10; //reset score to 10 if it above  10
                                        }

                                        if( $score == 10 )
                                        {
                                            /* If the user scored 10 out of 10, added 100 * 100 to the user wallet     */
                                            $amount_rewarded  = (int)$user_game_play_amount * 10;
                                            $reward_data = ['phone' => $this_user->phone, 'amount' => $amount_rewarded, 'info' => "Game Play" , 'registration_channel_id' =>  $this->registration_channel_id  ];

                                            //Create a Reward Model and Store reward
                                            Reward::create($reward_data);

                                            //Make Attempt to compute User Wallet
                                            $user_wallet = new Wallet();

                                            $user_wallet->init($this_user->phone);

                                            $receiver_name_without_number =  preg_replace('/[0-9]+/', '', $receiver_name);
                                            $text = "*☺️☺️Congrats!*\n\n *" . $receiver_name_without_number . ",   You Won!!*  \n\n Your total score is  ". $score  . " out of " . $activity_log_decoded_bot_parameter['total_questions'] . " \n 🏆 Congratulations!!\n\n You have won " . $amount_rewarded . "\n Your new wallet balance is " . $user_wallet->balance . "  You can proceed to play a new game again.\n\n Just reply with 'Play Game' ";
                                            $bot_action_detail_array["response_text"] = $text ;

                                            $total_actual_winning = $total_actual_winning +  ( $user_game_play_amount * 10);


                                            if(!is_null($retrieved_game_model))
                                            {
                                                $game_status_tracker_data =
                                                    [
                                                        'game_id' => $retrieved_game_model->id, /* For facebook */
                                                        'game_status_id' => 4,  /* Game was won  */
                                                        'expected_winning' => $user_game_play_amount * 10, /* Amount paid ( $user_game_play_amount ) * winning factor (10) This value are static for now, they will be dynamic later */
                                                        'actual_winning' => $user_game_play_amount * 10,
                                                        'score' => $score, /* all score or  $first_half_score + $second_half_score*/
                                                        'wallet_balance' => $user_wallet->balance,
                                                        'total_actual_winning' =>  $total_actual_winning,
                                                        'start_time' => $start_time,
                                                        'end_time' => Carbon::now(),
                                                        'amount_staked' => $user_game_play_amount,  /* Amount the player used for playing the game  */
                                                        'previous_game_status_id' => $previous_game_status_id,  /* the game_status_id now  will be 0 at start of the game  */
                                                    ];

                                                GameStatusTracker::create($game_status_tracker_data);
                                                //Update games table according here : game_state_changed and updated_at

                                                $retrieved_game_model->updated_at = Carbon::now();
                                                $retrieved_game_model->game_state_changed = 1;
                                                $retrieved_game_model->save();


                                            }
                                        }
                                        else
                                        {/* If the user didnt score 10 out of 10 scores   */

                                            $receiver_name_without_number =  preg_replace('/[0-9]+/', '', $receiver_name);

//                                            $text = "☺️☺️Congrats!" . $receiver_name_without_number . ",  you have finished the question game! ⭐⭐ \n\n Your total score is  ". $score . " out of " . $activity_log_decoded_bot_parameter['total_questions'] . " \n You have not won any reward. You must score ". $activity_log_decoded_bot_parameter['total_questions'] . " out of " .$activity_log_decoded_bot_parameter['total_questions'] . "\n   You can proceed to play a new game again.\n\n Just reply with 'Play Game' ";

                                            // GLI specification to capture events where player didnot finish their game
                                            if( ($activity_log_decoded_bot_parameter['time_left'] <=  0 ) )
                                            {
                                                $text = $receiver_name_without_number . ", *your time is up!*  ⭐⭐ \n\n Your total score is  ". $score. " out of " . $activity_log_decoded_bot_parameter['total_questions'] . " \n You have not won any reward. You must score ". $activity_log_decoded_bot_parameter['total_questions'] . " out of " .$activity_log_decoded_bot_parameter['total_questions'] . "\n   You can proceed to play a new game again.\n\n Just reply with 'Play Game' ";
                                            }
                                            else
                                            {
                                                $text =  "*Game Over! \n\nYou have finished answering  10 questions!!*\n\n" . $receiver_name_without_number . ", your total score is  ". $score. " out of " . $activity_log_decoded_bot_parameter['total_questions'] . " \n You have not won any reward. You must score ". $activity_log_decoded_bot_parameter['total_questions'] . " out of " .$activity_log_decoded_bot_parameter['total_questions'] . "\n   You can proceed to play a new game again.\n\n Just reply with 'Play Game' ";
                                            }


                                            $bot_action_detail_array["response_text"] = $text ;

                                            $user_wallet = new Wallet();

                                            $user_wallet->init($this_user->phone);

                                            $bot_action_detail_array["response_text"] = $text ;



                                            if(!is_null($retrieved_game_model))
                                            {
                                                $game_status_tracker_data =
                                                                            [
                                                                                'game_id' => $retrieved_game_model->id, /* For facebook */
                                                                                'game_status_id' => 5,  /* Game was lost  */
                                                                                'expected_winning' => $user_game_play_amount * 10,
                                                                                /* Amount paid ( $user_game_play_amount ) * winning factor (10) This value are static for now, they will be dynamic later */
                                                                                'actual_winning' => 0,
                                                                                'score' => $score,
                                                                                /* all score or  $first_half_score + $second_half_score*/
                                                                                'wallet_balance' => $user_wallet->balance,
                                                                                'total_actual_winning' =>  $total_actual_winning,
                                                                                'start_time' => $start_time,
                                                                                'end_time' => Carbon::now(),
                                                                                'amount_staked' => $user_game_play_amount,
                                                                                /* Amount the player used for playing the game  */
                                                                                'previous_game_status_id' => $previous_game_status_id,
                                                                                /* the game_status_id now  will be 0 at start of the game  */
                                                                            ];

                                                GameStatusTracker::create($game_status_tracker_data);
                                                //Update games table according here : game_state_changed and updated_at

                                                $retrieved_game_model->updated_at = Carbon::now();
                                                $retrieved_game_model->game_state_changed = 1;
                                                $retrieved_game_model->save();
                                            }
                                        }

                                    break;
                                    case 'other pay':
                                        $game_unique_identifier = $activity_log_decoded_bot_parameter['game_unique_identifier'];
                                        $score = $activity_log_decoded_bot_parameter['score'];
                                        $total_actual_winning =   $activity_log_decoded_bot_parameter['total_actual_winning'];
                                        $start_time = $activity_log_decoded_bot_parameter['start_time'];
                                        $previous_game_status_id =   $activity_log_decoded_bot_parameter['previous_game_status_id'];
                                        $user_game_play_amount = (float)$activity_log_decoded_bot_parameter['user_game_play_amount'];

                                        $second_half_score =  $activity_log_decoded_bot_parameter['second_half_score']; // We will use the latest 'first_half_score'


                                        // Retrieve Game Model using $game_unique_identifier and get game_id
                                        $game_models =  Game::where('game_unique_identifier',  '=' , $game_unique_identifier )->get();
                                        if(count($game_models) > 0)
                                        {
                                            $retrieved_game_model = $game_models->first();
                                        }

                                        //compute to add 1 to correct score
                                        if($correct_option_activity == strtolower($response_text) )
                                        {
                                            // if answer is correct add 1 to the score and store,
                                            $score += 1;
                                            $second_half_score += 1;
                                            //If correct option is same as text returned ,increase score by one
                                        }

                                        // However, if score is above 10, just reset score to 10

                                        if($score >= 10)
                                        {
                                            $score = 10; //reset score to 10 if it above  10
                                        }

                                        if($second_half_score >= 5)
                                        {
                                            $second_half_score = 5; //reset score to 10 if it above  10
                                        }

                                        if($second_half_score >= 4 )
                                        {
                                            /* If the user scored 4 or more out of 5, added 2.5 * 100 ( game play amount ) to the user wallet     */
                                            $amount_rewarded  = $user_game_play_amount * 2.5;

                                            $this_user = User::find($last_activity->user_id);
                                            $reward_data = ['phone' => $this_user->phone, 'amount' => $amount_rewarded, 'info' => "Game Play" , 'registration_channel_id' =>  $this->registration_channel_id  ];
                                            //Create a Reward Model and Store reward
                                            Reward::create($reward_data);

                                            //Make Attempt to compute User Wallet
                                            $user_wallet = new Wallet();

                                            $user_wallet->init($this_user->phone);

                                                $receiver_name_without_number =  preg_replace('/[0-9]+/', '', $receiver_name);
                                                $text = "☺️☺️ Congrats!" . $receiver_name_without_number . ",  you have been rewarded " . $amount_rewarded . " naira. \n\n Your wallet balance is: " . $user_wallet->balance . "  \n\n  You scored " .  $score  . " out of " . ( $activity_log_decoded_bot_parameter['total_questions']) . "\n\n You can proceed to play a new game again.\n\n Just reply with 'Play Game' ";
                                                $bot_action_detail_array['response_text'] = $text;

                                            $total_actual_winning = $total_actual_winning + ($user_game_play_amount * 2.5);



                                            if(!is_null($retrieved_game_model))
                                            {
                                                $game_status_tracker_data =
                                                    [
                                                        'game_id' => $retrieved_game_model->id, /* For facebook */
                                                        'game_status_id' => 4,  /* This is the id of ended on the game type table */
                                                        'expected_winning' => $user_game_play_amount * 5, /* Amount paid ( $user_game_play_amount ) * winning factor (5) This */
                                                        'actual_winning' => $user_game_play_amount * 2.5,
                                                        'score' => $score, /* all score or  $first_half_score + $second_half_score*/
                                                        'wallet_balance' => $user_wallet->balance,
                                                        'total_actual_winning' =>  $total_actual_winning,
                                                        'start_time' => $start_time,
                                                        'end_time' => Carbon::now(),
                                                        'amount_staked' => $user_game_play_amount,
                                                        /* Amount the player used for playing the game  */
                                                        'previous_game_status_id' => $previous_game_status_id,
                                                        /* the game_status_id now  will be 0 at start of the game  */
                                                    ];

                                                GameStatusTracker::create($game_status_tracker_data);
                                                //Update games table according here : game_state_changed and updated_at

                                                $retrieved_game_model->updated_at = Carbon::now();
                                                $retrieved_game_model->game_state_changed = 1;
                                                $retrieved_game_model->save();
                                            }

                                        }
                                        else
                                        {/* If the user didnt score 4 out of 5 scores in the second half of the question session   */

                                            $receiver_name_without_number =  preg_replace('/[0-9]+/', '', $receiver_name);

                                            $text = "☺️☺️Congrats!" . $receiver_name_without_number . ",  you have finished the question game! ⭐⭐ \n\n Your total score is  ". $score  . " out of " . $activity_log_decoded_bot_parameter['total_questions'] . " \n You have not won any reward for the last part of the question session. You must score 4 questions out of 5 in the second half of the question  \n   You can proceed to play a new game again.\n\n Just reply with 'Play Game' ";
                                            $bot_action_detail_array["response_text"] = $text ;

                                            $user_wallet = new Wallet();

                                            $user_wallet->init($this_user->phone);


                                            if(!is_null($retrieved_game_model))
                                            {
                                                $game_status_tracker_data =
                                                    [
                                                            'game_id' => $retrieved_game_model->id, /* For facebook */
                                                            'game_status_id' => 5,  /* Game was lost  */
                                                            'expected_winning' => $user_game_play_amount * 5, /* Amount paid ( $user_game_play_amount ) * winning factor (5) This */
                                                            'actual_winning' => 0,
                                                            'score' => $score, /* all score or  $first_half_score + $second_half_score*/
                                                            'wallet_balance' => $user_wallet->balance,
                                                            'total_actual_winning' =>  $total_actual_winning,
                                                            'start_time' => $start_time,
                                                            'end_time' => Carbon::now(),
                                                            'amount_staked' => $user_game_play_amount,
                                                            /* Amount the player used for playing the game  */
                                                            'previous_game_status_id' => $previous_game_status_id
                                                    ];

                                                GameStatusTracker::create($game_status_tracker_data);
                                                //Update games table according here : game_state_changed and updated_at

                                                $retrieved_game_model->updated_at = Carbon::now();
                                                $retrieved_game_model->game_state_changed = 1;
                                                $retrieved_game_model->save();
                                            }
                                        }
                                        break;
                                    default:
                                        break;

                                }

                                //Save Score in score tables
                                $saved_score  = new Score();
                                $saved_score->user_id = $last_activity->user_id;
                                $saved_score->score = $score;
                                $saved_score->save();

                                $game_details_array['current_game_score'] = $score;
                                $game_details_array['current_game_status_id'] = 5;// Game has ended
                                $game_details_array['game_id'] = ( isset($retrieved_game_model) and !is_null($retrieved_game_model) ) ? $retrieved_game_model->id : 0;

                                GameDetail::create($game_details_array);
                                Log::info(  json_encode($game_details_array). "=======Gbenga======Akinbami=======". json_encode($activity_log_decoded_bot_parameter) );

                                return $bot_action_detail_array;
                            }
                            else
                            {
                                // If the bot action does not exist, default to the first bot action
                                //HardCode What Happens When Bot cannot Choose
                                $first_bot_action_id = 1;
                                $next_bot_action = BotAction::find($first_bot_action_id);
                                $bot_action_detail_array["bot_action_parameter"] = $next_bot_action->bot_action_parameter_template; // get the both action parameters
                                $bot_action_detail_array["next_bot_action"] = $next_bot_action;


                                $receiver_name_without_number =  preg_replace('/[0-9]+/', '', $receiver_name);

                                $text = "☺️☺️Congrats!" . $receiver_name_without_number . ",  you have finished the question game! ⭐⭐ \n\n Your total score is  ". $activity_log_decoded_bot_parameter['score'] . ". \n 🏆 Congratulations!!\n\n You can proceed to play the game again.\n\n Just reply with 'Play Game Again ";

                                $bot_action_detail_array["response_text"] = $text ;

                                return $bot_action_detail_array;
                            }

                        }
                        else /* Continue to Serve Questions to Player */
                        {

                            //Due to the requirement of the 'win small small', then two new score subsets properties or variables will be introduced into the json parameter. The properties will be called  first_half_score and second_half_score




                            //Get one randomized Question and the answers and set it to the "Question" property and Option A, B, C and D as answers
                            //Compute the "score" by checking if answer is correct, increment by one or else leave score has is
                            //Set The Time Left for Question Session.
                            // Write the above three function in  a separate function

                            $returned_bot_action_parameter = $this->computeBotParameterJson($last_activity, $response_text, $receiver_name);

//                    return  $returned_bot_action_parameter;

                            $game_play_option  = ["reward_type" => $activity_log_decoded_bot_parameter['reward_type'] ];

                            $bot_action_detail_array["bot_action_parameter"] = json_encode(array_merge($game_play_option, $returned_bot_action_parameter) ) ;

                            //Decision about activating next bot action or maintaining current bot action
                            $bot_action_detail_array["next_bot_action"] = $this_current_bot_action; ;

                            $bot_action_detail_array["response_text"] = $returned_bot_action_parameter['bot_response_text']; ;
                            //Bot Response Text must be computed dynamically

                            return $bot_action_detail_array;
                        }

                        break;
                    case 12:

                        if($activity_log_decoded_bot_parameter['progress_tracking'] === "completed")
                        {/*   If complete move to next bot_action ( e.g bot_action_id =  6 )  */

                            $next_bot_action = BotAction::find($last_activity->next_bot_action_id);

                            if(!is_null($next_bot_action))
                            {
                                $returned_bot_action_parameter = $this->initiateBotParameter($last_activity->next_bot_action_id , $receiver_name, $response_text,  $last_activity->user_id );
                                $bot_action_detail_array["bot_action_parameter"] = json_encode($returned_bot_action_parameter);

                                $bot_action_detail_array["next_bot_action"] = $next_bot_action;

                                $bot_action_detail_array["response_text"] = $returned_bot_action_parameter['bot_response_text'];
                            }
                            else
                            {
                                // If the bot action does not exist, default to the first bot action
                                //HardCode What Happens When Bot cannot Choose
                                $first_bot_action_id = 1;
                                $next_bot_action = BotAction::find($first_bot_action_id);
                                $bot_action_detail_array["bot_action_parameter"] = $next_bot_action->bot_action_parameter_template; // get the both action parameters
                                $bot_action_detail_array["next_bot_action"] = $next_bot_action;


                                $receiver_name_without_number =  preg_replace('/[0-9]+/', '', $receiver_name);
                                $text = "Hello, " . $receiver_name_without_number . " ☺️! \nWelcome to PreWin Games! 🌟. \n\n\n PREWIN is a unique, simple life style social game that entertains, educate and test knowledge around sports, fashion, politics,entertainment e.t.c \n\n
Speed and accuracy are rewarded as you are expected to answer 10 questions in 5minutes.\n\n\n Choose from below to start. \n\n Demo\n Fund Account \n Play Game\n Reward\nReset Password\nCheck Balance ";

                                $bot_action_detail_array["response_text"] = $text ;
                                $bot_action_detail_array['show_template'] = true;
                                $bot_action_detail_array['template_option_array'] = ["demo", "fund account", "play game", "reward", "reset password", "check balance"  ];

                                return $bot_action_detail_array;
                            }
                        }
                        else
                        {/*   or else if let computeBotParameterJson act on this current bot action */

                            $returned_bot_action_parameter = $this->computeBotParameterJson($last_activity, $response_text, $receiver_name);

//                    return  $returned_bot_action_parameter;

                            $bot_action_detail_array["bot_action_parameter"] = json_encode($returned_bot_action_parameter ) ;

                            //Decision about activating next bot action or maintaining current bot action
                            $bot_action_detail_array["next_bot_action"] = $this_current_bot_action;

                            $bot_action_detail_array["response_text"] = $returned_bot_action_parameter['bot_response_text'];
                            //Bot Response Text must be computed dynamically

                            //Decision to show or not to show template response



                            return $bot_action_detail_array;

                        }

                        break;
                    case 13:

                        if($activity_log_decoded_bot_parameter['progress_tracking'] === "completed")
                        {/*   If complete move to next bot_action ( e.g bot_action_id =  6 )  */

                            $next_bot_action = BotAction::find($last_activity->next_bot_action_id);

                            if(!is_null($next_bot_action))
                            {
                                $returned_bot_action_parameter = $this->initiateBotParameter($last_activity->next_bot_action_id , $receiver_name, $response_text,  $last_activity->user_id );
                                $bot_action_detail_array["bot_action_parameter"] = json_encode($returned_bot_action_parameter);

                                $bot_action_detail_array["next_bot_action"] = $next_bot_action;

                                $bot_action_detail_array["response_text"] = $returned_bot_action_parameter['bot_response_text'];
                            }
                            else
                            {
                                // If the bot action does not exist, default to the first bot action
                                //HardCode What Happens When Bot cannot Choose
                                $first_bot_action_id = 1;
                                $next_bot_action = BotAction::find($first_bot_action_id);
                                $bot_action_detail_array["bot_action_parameter"] = $next_bot_action->bot_action_parameter_template; // get the both action parameters
                                $bot_action_detail_array["next_bot_action"] = $next_bot_action;


                                $receiver_name_without_number =  preg_replace('/[0-9]+/', '', $receiver_name);
                                $text = "Hello, " . $receiver_name_without_number . " ☺️! \nWelcome to PreWin Games! 🌟. \n\n\n PREWIN is a unique, simple life style social game that entertains, educate and test knowledge around sports, fashion, politics,entertainment e.t.c \n\n
Speed and accuracy are rewarded as you are expected to answer 10 questions in 5minutes.\n\n\n Choose from below to start. \n\n Demo\n Fund Account \n Play Game\n Reward\nReset Password\nCheck Balance ";

                                $bot_action_detail_array["response_text"] = $text ;
                                $bot_action_detail_array['show_template'] = true;
                                $bot_action_detail_array['template_option_array'] = ["demo", "fund account", "play game", "reward", "reset password", "check balance"  ];

                                return $bot_action_detail_array;
                            }
                        }
                        else
                        {/*   or else if let computeBotParameterJson act on this current bot action */

//                            $bot_action_detail_array["next_bot_action"]['id'] = 13;
//                            $bot_action_detail_array["bot_action_parameter"] = json_encode($last_activity);
//                            $bot_action_detail_array["next_bot_action"]['next_action_id'] = null;
//                            $bot_action_detail_array["response_text"] = 'trace';
//                            $bot_action_detail_array["progress_tracking"] = 'trace';
//                            $bot_action_detail_array['trace'] = $last_activity;
//                            return $bot_action_detail_array;
                            // For diagnosis purpose
                            $returned_bot_action_parameter = $this->computeBotParameterJson($last_activity, $response_text, $receiver_name);

//                    return  $returned_bot_action_parameter;

                            $bot_action_detail_array["bot_action_parameter"] = json_encode($returned_bot_action_parameter ) ;

                            //Decision about activating next bot action or maintaining current bot action
                            $bot_action_detail_array["next_bot_action"] = $this_current_bot_action;

                            $bot_action_detail_array["response_text"] = $returned_bot_action_parameter['bot_response_text'];
                            //Bot Response Text must be computed dynamically

                            //Decision to show or not to show template response
                            $bot_action_detail_array["show_template"] = array_key_exists('show_template', $returned_bot_action_parameter) ? $returned_bot_action_parameter['show_template'] : false;
                            $bot_action_detail_array["template_option_array"] = array_key_exists('template_option_array', $returned_bot_action_parameter) ? $returned_bot_action_parameter['template_option_array'] : ['Check Balance'];

                            return $bot_action_detail_array;

                        }

                        break;
                    default:
                        //
                        $first_bot_action_id = 1;
                        $next_bot_action = BotAction::find($first_bot_action_id);
                        $returned_bot_action_parameter = $this->computeBotParameterJson($last_activity, $response_text, $receiver_name);
                        $bot_action_detail_array["bot_action_parameter"] = json_encode($returned_bot_action_parameter ) ;
                        $bot_action_detail_array["next_bot_action"] = $next_bot_action;

                        $receiver_name_without_number =  preg_replace('/[0-9]+/', '', $receiver_name);

                        $text = "Hello, " . $receiver_name_without_number . " ☺️! \nWelcome to PreWin Games! 🌟. \n\n\n Prewin Games allows you to provide answers to a pool of questions over a period of time. User will be answering 10 questions in 5 minutes\n\n\n You can proceed to: \n\n Demo\n fund account \n play game\n reward\nreset password\ncheck balance ";

                        $bot_action_detail_array["response_text"] =  $text;
                }

                return  $bot_action_detail_array;

            }
            else
            {
                //IF false check the next bot action on the bot action template on the bot action table
                //If the bot action exists , get the bot action parameter and fill according to question
                // If the bot action dos not exist, default to the first Bot action
                //return


                //IF false check the next bot action on the bot action template on the bot action table
                $next_bot_action = BotAction::find($last_activity->next_bot_action_id);
//                return $next_bot_action;
                if(!is_null($next_bot_action))
                {

                    //Next Bot Action Exist, we can populate accordingly
                    //Use the initiateBotParameter function  to check and initiate the appropriate value and json structure of the json parameter based on the parameter template as found in the database

//                    return $this->initiateBotParameter($next_bot_action->id);
                    $bot_initiate_array =  $this->initiateBotParameter($next_bot_action->id, $receiver_name, $response_text, $last_activity->user_id);
                    $bot_action_detail_array["bot_action_parameter"] =  json_encode($bot_initiate_array);
                    //trim($next_bot_action->bot_action_parameter_template);

                    $bot_action_detail_array["next_bot_action"] = $next_bot_action;
                    $bot_action_detail_array["response_text"] = $bot_initiate_array['bot_response_text']; ;
                    return $bot_action_detail_array;
                }
                else
                {
                    // If the bot action does not exist, default to the first bot action
                    $first_bot_action_id = 1;
                    $next_bot_action = BotAction::find($first_bot_action_id);
                    $bot_action_detail_array["bot_action_parameter"] = $next_bot_action->bot_action_parameter_template; // get the both action parameters
                    $bot_action_detail_array["next_bot_action"] = $next_bot_action;

                    $receiver_name_without_number =  preg_replace('/[0-9]+/', '', $receiver_name);

                    $text = "Hello, " . $receiver_name_without_number . " ☺️! \nWelcome to PreWin Games! 🌟. \n\n\n Prewin Games allows you to provide answers to a pool of questions over a period of time. User will be answering 10 questions in 5 minutes\n\n\n You can proceed to: \n\n Demo\n fund account \n play game\n reward\nreset password\ncheck balance ";

                    $bot_action_detail_array["response_text"] = $text;

                    return $bot_action_detail_array;

                }

            }
        }
        else
        {
            // If the bot action does not exist, default to the first bot action
            $first_bot_action_id = 1;
            $next_bot_action = BotAction::find($first_bot_action_id);
            $bot_action_detail_array["bot_action_parameter"] = $next_bot_action->bot_action_parameter_template; // get the both action parameters
            $bot_action_detail_array["next_bot_action"] = $next_bot_action;

            $receiver_name_without_number =  preg_replace('/[0-9]+/', '', $receiver_name);

            $text = "Hello, " . $receiver_name_without_number . " ☺️! \nWelcome to PreWin Games! 🌟. \n\n\n Prewin Games allows you to provide answers to a pool of questions over a period of time. User will be answering 10 questions in 5 minutes\n\n\n You can proceed to: \n\n Demo\n fund account \n play game\n reward\nreset password\ncheck balance ";

            $bot_action_detail_array["response_text"] = $text;

            return $bot_action_detail_array;

        }

    }

    private function computeBotParameterJson($last_activity, $response_text, $receiver_name)
    {
        //Compute bot parameter json for the current_bot_action_id given below by the last_activity
        // We got to this method because the current action is not completed and is to be repeated

        $question_array = []; // to be returned eventually

        $current_bot_action_id = $last_activity->current_bot_action_id;

        switch ($current_bot_action_id)
        {
            case 1:
                //

                $question_array['bot_response_text'] = "This is level 1";
                break;
            case 2:
                //

                $question_array['bot_response_text'] = "This is level 2";
                break;
            case 3:

                //Get one randomized Question and the answers and set it to the "Question" property and Option A, B, C and D as answers
                //Compute the "score" by checking if answer is correct, increment by one or else leave score has is
                //Set The Time Left for Question Sesiion.


                //Get one randomized Question and the answers and set it to the "Question" property and Option A, B, C and D as answers

                $questions = Question::where([ 'is_pretest' => 0])->take(50)->with('answers');
                $questions = $questions->get();
//                $one_randomized_question = $questions->random();

                $random_index = random_int(0, ($questions->count() - 1));

                $one_randomized_question = $questions->get($random_index);


                $answer_option_template = ["a","b","c","d","e", "f", "g", "h" ]; //template for assigning answer


                $question_array['question'] =  preg_replace("/&#?[a-z0-9]{2,8};/i","",strip_tags( $one_randomized_question->statement ));
//            return $one_randomized_question;
//            $optionArray = [0 => "A", 1 => "B", 2 => "C", 3 => "D" ];
                $answer_count = 0;
                $answer_option_count = 0;
                foreach ($one_randomized_question->answers as $answer_key =>  $each_answers)
                {
                    $question_array['option'.$answer_option_template[$answer_count++]] = $each_answers->statement;

                    if($each_answers->correct == "Yes") //
                    {
                        $question_array['correctOption'] = $answer_option_template[$answer_key];
//                    $question_array['answer_count'] = $each_answers->correct;
                    }
//                else
//                {
////                    $question_array['answer_count'] = $each_answers->correct;
////                    $question_array['correctOption'] = "false";
//
//                }

                    $answer_option_count++;
                }

                //Get one randomized Question and the answers and set it to the "Question" property and Option A, B, C and D as answers

                //Compute the "score" by checking if answer is correct, increment by one or else leave score has is

                $activity_log_decoded_bot_parameter = json_decode($last_activity->bot_action_parameter, true);
                $correct_option_activity = $activity_log_decoded_bot_parameter['correctOption'];
                if($correct_option_activity == strtolower($response_text) )
                {
                    $question_array['score'] =  $activity_log_decoded_bot_parameter['score'] + 1 ;//If correct option is same as text returned ,increase score by one
                }
                else
                {
                    $question_array['score'] =  $activity_log_decoded_bot_parameter['score']; //If not  correct option is same as text returned , score remains te same
                }

                //Always increase question count by one always
                $question_array['questions_count'] = $activity_log_decoded_bot_parameter['questions_count'] + 1;

                $question_array['total_questions'] = 10;
                $question_array['time_allowed'] = 5;
                $question_array['has_parameters'] = true;
                $question_array['question_start_timestamp'] = $activity_log_decoded_bot_parameter['question_start_timestamp']; // Dont ever update or touch or manipulate this value here or else you are cheating

                //Compute the "score" by checking if answer is correct, increment by one or else leave score has is


                //Set The Time Left for Question Session.

                $t = Carbon::now(); // The time now

                $r = Carbon::createFromTimestamp($activity_log_decoded_bot_parameter['question_start_timestamp']);// Get the time the last activity log was recorded

                $diff_in_minutes = $t->diffInMinutes($r); // get the difference in minutes

                //Set The Time Left for Question Session.
                $question_array['time_left'] = $activity_log_decoded_bot_parameter['time_left'] - $diff_in_minutes; // Subtract the difference in minutes from the time left

                //Build response Text
                $question_array['bot_response_text'] =

                    "Question No: " . $question_array['questions_count'] . "\n".
                    "Time Remaining in Minutes: ".  $question_array['time_left'] . " out of ".  $question_array['time_allowed'] ."\n".
                    "Your Score: ".  $question_array['score'] . " out of ".  $question_array['total_questions'] ."\n"."------------------------------\n\n". $question_array['question'] . "\n" .
                    "------------------------------\n\n".
                    "Answers ( Reply with  A, B, C or D ) : A. ".  $question_array['optiona'] . "   B. ".  $question_array['optionb']. "  C. ". $question_array['optionc'] . "  D. ". $question_array['optiond'] ."\n";


                //return Json back to caller
                break;
            case 4:
                //
                $question_array['bot_response_text'] = "This is level 4";
                break;
            case 5:
                // Create JSon Parameter
                //Tracking Registeration Progress
                //1. Take email from user after asking
                //json parameter for taking email --
                // { 'progress_tracking': 'uncompleted',
                //   'current_operation' : 'register',
                //   'current_sub_operation' : 'ask for email',
                //   'validation_status': true}
                //process a. ask for email from user
                //b validate email and send validation token 5 digits as Prewin Pin
               // { 'progress_tracking': 'uncompleted',
                //   'current_operation' : 'register',
                //   'current_sub_operation' : 'provide password',
                //   'validation_status': true or false}
                //c. Advice that Prewin is the login code for logging, Prompt for
               // { 'progress_tracking': 'uncompleted',
                //   'current_operation' : 'register',
                //   'current_sub_operation' : 'prompt for login',
                //   'validation_status': true}
                //2. Login Attempt should be done
                // a. Ask for Prewin pin and validate against phone number and  Prewin pin
                // { 'progress_tracking': 'uncompleted',
                //   'current_operation' : 'login',
                //   'current_sub_operation' : 'validate prewin token',
                //   'validation_status': true or false
                //}
                // b Announce that user is logged in and present then with options and move to next bot_action number 6
                //{ 'progress_tracking': 'completed',
                //   'current_operation' : 'login',
                //   'current_sub_operation' : 'successfully logged in ',
                //   'validation_status': true
                //}

                $activity_log_decoded_bot_parameter = json_decode($last_activity->bot_action_parameter, true);

                /* Do a switch that analyzes the current_operation parameter  */

                switch (strtolower($activity_log_decoded_bot_parameter['current_sub_operation']))/* Get Decoded Bot Parameter current_operation */
                {
                    case 'ask for email':

                        $response_text;
                            /* 1. We asked for email, we want to validate email and check that email does not exist
                            if email is not validated, then send back response remain at same level as alast time
                               2. Then we want tosave email against phone number
                               3. We want to issue 5 digit pin to email provided
                               4. We want to provide a response saying we have save email successfully and that Prewin pin is sent to the email provided
                            */


                        //{ 'progress_tracking': 'uncompleted',
                //   'current_operation' : 'register',
                //   'current_sub_operation' : 'ask for email',
                //   'validation_status': true}

                        $validator = Validator::make(['email' => $response_text ], [
                            'email' => 'required|string|email|max:255|unique:users',
                        ]);

                        if ($validator->fails())
                        { /**  Validation  Failed for some reason */

                            $ValidatorErr =  $validator->getMessageBag()->toArray();
                            $question_array['progress_tracking'] = 'uncompleted';
                            $question_array['current_operation'] = 'register';
                            $question_array['current_sub_operation'] = 'ask for email';
                            $question_array['validation_status'] =  false;
//                            $question_array['ValidatorErr'] =  $ValidatorErr;

                            $receiver_name_without_number =  preg_replace('/[0-9]+/', '', $receiver_name);

                            $text = "⛔ Sorry, " .  $receiver_name_without_number . " ☺️! \n\n⛔ " . $ValidatorErr['email'][0]. " \n\n Please, can you provide me with a valid email or you can login . \n\n ' Just Reply with a valid email or reply 'login'. ";

                            $question_array['bot_response_text'] = $text ;
                        }
                        else
                        {
                            //Save Email against user_id of last activity
                            //Send Reply back and increment to next step

                            $get_user = User::find( $last_activity->user_id);
                            // Its sure that this user model will exist

                            //Check that user is not trying to send different email, if email already  exists (Future code )

                            $get_user->email = $response_text;
                            $get_user->updated_at = Carbon::now();
                            $get_user->pin_code = $this->create_random_number(5);
                            $get_user->active = 0;/** Login will actively watch out that active is 1. active will be change to 1 when a pin is provided from the email message */
                            $get_user->save();

//                            try {
//                                $get_user->notify(new RegistrationSuccessful($get_user->pin_code, $receiver_name, $get_user->email));
//                                $question_array['mail_error'] =  "No error";
//                            } catch (\Exception $e) {
//                                $question_array['mail_error'] =  $e;
//
//                            }

                            $question_array['progress_tracking'] = 'uncompleted';
                            $question_array['current_operation'] = 'register';
                            $question_array['current_sub_operation'] = 'provide pin number';
                            $question_array['validation_status'] =  true;

                            $receiver_name_without_number =  preg_replace('/[0-9]+/', '', $receiver_name);

                            $text = "Excellent, " .  $receiver_name_without_number . " ☺️! \n\n I have sent your pin code to " . $get_user->email . ". Please, copy and reply with your pin number. ";

                            $question_array['bot_response_text'] = $text ;
                        }


                        break;
                    case 'provide password':
                        //take password and store password
                        //Announce user is successfully authenticated
                        //Show all possible menus in the response text to be sent to user

                        $get_user = User::find( $last_activity->user_id);

                        $get_user->password = app('hash')->make($response_text); // bcrypt($response_text);
                        $get_user->updated_at = Carbon::now();
                        $get_user->pin_code = $this->create_random_number(5);
                        $get_user->active = 1;/** Login will actively watch out that active is 1. active has bee changed to one here   **/
                        $get_user->save();

                        //Create AccessToken Model that will be used to check for user is authenticated

                        $user_access_token = new AccessToken();
                        $user_access_token->user_id = $last_activity->user_id;
                        $user_access_token->token =  $this->create_random_number(10);
                        $user_access_token->created_at = Carbon::now();
                        $user_access_token->expired =  null;
                        $user_access_token->active  = 1;
                        $user_access_token->save();


                        $receiver_name_without_number =  preg_replace('/[0-9]+/', '', $receiver_name);


                        $text = "⭐⭐ Congratulation! You have set up your password successfully.\n\n" .  $receiver_name_without_number . " you are now logged in and you can perform the various tasks below.\n\n Reply with: \n  'Play Game' or 'fund account' or  'Reward' or 'Check Balance' ";

                        $question_array['progress_tracking'] = 'completed';
                        $question_array['current_operation'] = 'login';
                        $question_array['current_sub_operation'] = 'authentication completed';
                        $question_array['validation_status'] =  true;

                        $question_array['bot_response_text'] = $text ;


                    break;
                    case 'prompt for login':

                        /**
                        // Send text that password should be provided for login to happen
                        // initialize parameter properly to allow form apassword to be passed and credentials authenticated
                        $receiver_name_without_number =  preg_replace('/[0-9]+/', '', $receiver_name);
                        $text = "☺️ ☺️ Hey, " . $receiver_name_without_number . " , please reply with your prewin password, so that ou can continue.";

                        $question_array['progress_tracking'] = 'uncompleted';
                        $question_array['current_operation'] = 'register';
                        $question_array['current_sub_operation'] = 'provide pin number';
                        $question_array['validation_status'] =  true;


                        $question_array['bot_response_text'] = $text ;

                        **/

                        // Attempt to login by validating login credentials ( Phone number against hashed password )
                        // Present authenticated options

                        $get_user = User::find( $last_activity->user_id);
                        $password = $response_text;
//                        $hashed_password =  app('hash')->make($password);
                        $checked_hashed_password =  Hash::check($password ,  $get_user->password);
                        $authenticaton_attempt = User::where(['phone' => $get_user->phone, 'active' => 1])->get();

                        if($checked_hashed_password and  (count($authenticaton_attempt) > 0))
                        {
                            //
                            $user_access_token = new AccessToken();
                            $user_access_token->user_id = $last_activity->user_id;
                            $user_access_token->token =  $this->create_random_number(10);
                            $user_access_token->created_at = Carbon::now();
                            $user_access_token->expired =  null;
                            $user_access_token->active  = 1;
                            $user_access_token->save();



                            // respond and authenticate, login is complete
                            $receiver_name_without_number =  preg_replace('/[0-9]+/', '', $receiver_name);

                            $text = "⭐⭐ Congratulation! Your password is correct.\n\n" .  $receiver_name_without_number . " you are now logged in and you can perform the various tasks below.\n\n Reply with: \n  'Play Game' or 'fund account' ";

                            $question_array['progress_tracking'] = 'completed';
                            $question_array['current_operation'] = 'login';
                            $question_array['current_sub_operation'] = 'authentication completed';
                            $question_array['validation_status'] =  true;

                            $question_array['bot_response_text'] = $text ;
//                            $question_array['authenticaton_attempt'] = $authenticaton_attempt ;
//                            $question_array['checked_hashed_password'] = $checked_hashed_password ;

                        }
                        else
                        {
                            //The password is not correct.
                            //respond and do not authenticate, login/register is uncompleted

                            $receiver_name_without_number =  preg_replace('/[0-9]+/', '', $receiver_name);

                            $text = "⛔ Sorry, " . $receiver_name_without_number . " the password you provided is not correct.\n\n  Please try:\n Re-typing with the correct password (try again) or \n Resetting your password ( reply with 'reset password' ) ";

                            $question_array['progress_tracking'] = 'uncompleted';
                            $question_array['current_operation'] = 'login';
                            $question_array['current_sub_operation'] = 'prompt for login';
                            $question_array['validation_status'] =  true;
                            $question_array['bot_response_text'] = $text ;
//                            $question_array['authenticaton_attempt'] = $authenticaton_attempt ;
//                            $question_array['checked_hashed_password'] = $checked_hashed_password ;
                        }

                        break;
                    case 'reset password':

                        // Attempt to get new password
                        // Present authenticated options
                            $receiver_name_without_number =  preg_replace('/[0-9]+/', '', $receiver_name);

                        $text = "Hey, " . $receiver_name_without_number . " , please can you reply with your password so that you can update your password?.  \n\n Just Reply with your prefered prewin password.";

                            $question_array['progress_tracking'] = 'uncompleted';
                            $question_array['current_operation'] = 'login';
                            $question_array['current_sub_operation'] = 'provide password';
                            $question_array['validation_status'] =  true;
                            $question_array['bot_response_text'] = $text ;

                        break;
                    case 4:

                        break;
                    case 5:

                        //Done
                        break;
                    default:


                }

                break;
            case 6:

                $activity_log_decoded_bot_parameter = json_decode($last_activity->bot_action_parameter, true);

                /* Do a switch that analyzes the current_operation parameter  */

                $this_user = User::find($last_activity->user_id);

                switch (strtolower($activity_log_decoded_bot_parameter['current_sub_operation']))
                    /* Get Decoded Bot Parameter current_operation */
                {
                    case 'get fund amount':
                        /*
                            We will take the amount sent and will use it to initiate a paystack  authorization
                            And we will send the authorization url to the user and wait for a call back url which
                            we will hit the server and the activity log is populated.
                        */
                        //Get User phone number

                        $this_user = User::find($last_activity->user_id);

                        //Get Amount to be paid from the response_text

                        $webPay = new WebPayOnline();

                        $payment_amount =  (int)$response_text;
                        $webPay->authorize($this_user->phone); // construct the paystack email for the paystack payment
                        $paystack_initiation = $webPay->pay($payment_amount); //initialize paystack transaction and save to the purchase table

                    if(!is_null($paystack_initiation))
                    {
                        $question_array['progress_tracking'] = "uncompleted";
                        $question_array['current_operation'] = "fund wallet";
                        $question_array['current_sub_operation'] = "validate paystack payment";
                        $question_array['validation_status'] = true;
                        $question_array['stack_trace'] = $webPay;

                        $receiver_name_without_number =  preg_replace('/[0-9]+/', '', $receiver_name);

                        $text = "Hello, " .  $receiver_name_without_number . " ☺️! \n\n You have initiated a payment transaction. Please click the authorization url below in order to proceed with payment.\n\n\n Click this link : " . $paystack_initiation['authorization_url'] . "\n\n\n Please, wait for the transaction to be completed.";
                    }
                    else
                    {
                        $question_array['progress_tracking'] = "uncompleted";
                        $question_array['current_operation'] = "fund wallet";
                        $question_array['current_sub_operation'] = "get fund amount";
                        $question_array['validation_status'] = false;
                        $question_array['stack_trace'] = $webPay;

                        $receiver_name_without_number =  preg_replace('/[0-9]+/', '', $receiver_name);

                        $text = "⛔ Sorry, " .  $receiver_name_without_number . " ☺️! \n\n You payment attempt was unsuccessful.\n\n\n Please, reply with the a correct numerical amount that you want to pay to fund your wallet.";

                    }

                        $question_array['bot_response_text'] = $text ;

                        break;
                    case 'get fund amount paystack':
                        /*
                            We will take the amount sent and will use it to initiate a paystack  authorization
                            And we will send the authorization url to the user and wait for a call back url which
                            we will hit the server and the activity log is populated.
                        */
                        //Get User phone number



                        //Get Amount to be paid from the response_text

                        $webPay = new WebPayOnline();

                        $payment_amount =  (int)$response_text;
                        $webPay->authorize($this_user->phone); // construct the paystack email for the paystack payment
                        $paystack_initiation = $webPay->pay($payment_amount); //initialize paystack transaction and save to the purchase table

                        if(!is_null($paystack_initiation))
                        {
                            $question_array['progress_tracking'] = "uncompleted";
                            $question_array['current_operation'] = "fund wallet";
                            $question_array['current_sub_operation'] = "validate paystack payment";
                            $question_array['validation_status'] = true;
                            $question_array['stack_trace'] = $webPay;

                            $receiver_name_without_number =  preg_replace('/[0-9]+/', '', $receiver_name);

                            $text = "Hello, " .  $receiver_name_without_number . " ☺️! \n\n You have initiated a payment transaction. Please click the authorization url below in order to proceed with payment.\n\n\n Click this link : " . $paystack_initiation['authorization_url'] . "\n\n\n Please, wait for the transaction to be completed.";
                        }
                        else
                        {
                            $question_array['progress_tracking'] = "uncompleted";
                            $question_array['current_operation'] = "fund wallet";
                            $question_array['current_sub_operation'] = "get fund amount paystack";
                            $question_array['validation_status'] = false;
                            $question_array['stack_trace'] = $webPay;

                            $receiver_name_without_number =  preg_replace('/[0-9]+/', '', $receiver_name);

                            $text = "⛔ Sorry, " .  $receiver_name_without_number . " ☺️! \n\n You payment attempt was unsuccessful.\n\n\n Please, reply with the a correct numerical amount that you want to pay to fund your wallet.";

                        }

                        $question_array['bot_response_text'] = $text ;

                        break;
                    case 'get fund amount ussd':
                        //We will take amount sent in and populate the paystack table with status = 0
                        // We will call  paystack/charge endpoint and generate a GTB ussd code and show to the user
                        // Once there is successful payment, we will take data from webhook and use it to update the particular paystack record with status equal to 1
                        // The end

                        $this_user = User::find($last_activity->user_id);


                        if(is_numeric($response_text))
                        {

                            $reference = sprintf('%04X%04X-%04X-%04X-%04X-%04X%04X%04X', mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(16384, 20479), mt_rand(32768, 49151), mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(0, 65535));
                            $email = $this_user->phone . "@prewin.com.ng";
                            $USSDController = app('App\Http\Controllers\Api\V1\USSDController');
                            $paystack_charge_response_json =  $USSDController->paystack_ussd_charge($email, $response_text, $reference);

//                            $question_array['progress_tracking'] = "uncompleted";
//                            $question_array['current_operation'] = "fund wallet";
//                            $question_array['current_sub_operation'] = "validate paystack payment";
//                            $question_array['validation_status'] = true;
//                            $question_array['stack_trace'] = $paystack_charge_response_json;
//
//                            $text = "You have initiated a payment" ;

                            $paystack_charge_response = json_decode($paystack_charge_response_json, true);//   $USSDController->paystack_ussd_charge($email, $response_text, $reference);
                            if($paystack_charge_response['status'])
                            {

                                $access_code  =  array_key_exists('reference', $paystack_charge_response['data'] ) ? $paystack_charge_response['data']['reference'] :  null;
                                $ussd_code  =  array_key_exists('ussd_code', $paystack_charge_response['data'] ) ? $paystack_charge_response['data']['ussd_code'] :  null;
                                $paystack_data = [
                                    "phone" =>  $this_user->phone,
                                    "amount" => $response_text ,
                                    "authorization_url" => "The payment code like the authorization url is " . $ussd_code,
                                    'registration_channel_id' => $this->registration_channel_id,
                                    "access_code" => $access_code,
                                    "reference" =>  $reference ,
                                    "status" => 0
                                ];

                                PayStack::create($paystack_data);

                                $text = "You have initiated a payment transaction using USSD code.\n\n\n Please dial the USSD code below on your mobile phone to initiate a  payment of " . (int)$response_text . " naira. \n\n\n USSD Code: " . $ussd_code;

                                $question_array['progress_tracking'] = "uncompleted";
                                $question_array['current_operation'] = "fund wallet";
                                $question_array['current_sub_operation'] = "validate paystack payment";
                                $question_array['validation_status'] = true;
                                $question_array['stack_trace'] = $paystack_data;
                            }
                            else
                            {
                                //The attempt to call charge on paystack , for ussd tranaction failed. Ask user to try reply with amount again.
                                $text = "⛔ Sorry, We were unable to generate a ussd code at the moment.\n\n\n Lets try again. Please, enter an amount you want to pay in numeric digits.";
                                $question_array['progress_tracking'] = "uncompleted";
                                $question_array['current_operation'] = "fund wallet";
                                $question_array['current_sub_operation'] = "get fund amount ussd";
                                $question_array['validation_status'] = true;

                            }

                        }
                        else
                        {
                            //Complain that amount is not numeric and pull out of the process
                            // Ask for the ammount to be numeric number

                            //response_text is not a number, complain and ask that a number is replied
                            $text = "⛔ Sorry, you did not enter a number. Please, enter an amount you want to pay in numeric digits.";
                            $question_array['progress_tracking'] = "uncompleted";
                            $question_array['current_operation'] = "fund wallet";
                            $question_array['current_sub_operation'] = "get fund amount ussd";
                            $question_array['validation_status'] = true;
                        }

                        $question_array['bot_response_text'] = $text ;

                        break;
                    case 'get fund amount mcash':

                        $question_array['progress_tracking'] = "uncompleted";
                        $question_array['current_operation'] = "fund wallet";
                        $question_array['current_sub_operation'] = "validate mcash payment";
                        $question_array['validation_status'] = true;

                        //Generate 6 character alphanumeric  with  instruction and generate a payment Model , then save to paystack table.
                    $payment_amount = $response_text;
                    if(is_numeric($payment_amount) and $payment_amount >  0)
                    {
                        //Proceed with Mcash in instruction
                            $random_alphanum_array = Utilities::getUniqueFixedCharacters(6, ["reference" => "paystack"]);
                            $random_alphanum = $random_alphanum_array["generated_character"];
                            $random_alphanum = !is_null($random_alphanum) ? $random_alphanum : TransRef::getHashedToken(6); // if null is returned by Utilities::getUniqueFixedCharacters(6, ["reference" => "paystack"]), call  TransRef::getHashedToken(6)
                            $mcashCode = "*402*403*96608150*". $payment_amount ."#";
                            $stack_trace_array =
                                [
                                    "random_alphanum" => $random_alphanum,
                                    "mcashCode" => $mcashCode,
                                    "payment_amount" => $payment_amount
                                ];

                            $text = " You are about to pay " . $payment_amount .  " naira through the mCash channel.\n\n Please, dial this mCash code: $mcashCode \n\n  Your orderID is $random_alphanum, please write it down, it will be used during the mCash transaction. \n\n";

                            // Save Details to PayStack table


                            $payment_data['phone'] = $this_user->phone;
                            $payment_data['amount'] = $payment_amount;
                            $payment_data['registration_channel_id'] = $this->registration_channel_id;
                            $payment_data['payment_channel_id'] = 3;// id for Zenith Bank Mcash
                            $payment_data['authorization_url'] = null;
                            $payment_data['access_code'] =  null;
                            $payment_data['reference'] =  $random_alphanum;
                            $payment_data['status'] =  0;
                            $payment_data['created_at'] =  Carbon::now()->toDateTimeString();

                            PayStack::create($payment_data);



                        $question_array['progress_tracking'] = "uncompleted";
                        $question_array['current_operation'] = "fund wallet";
                        $question_array['current_sub_operation'] = "validate paystack payment";
                        $question_array['validation_status'] = true;
                        $question_array['stack_trace'] = $stack_trace_array;

                    }
                    else
                    {
                        //Proceed and give error report and prompt again for correct numerical values
                        $text = "⛔ Sorry! \n\n Your mcash payment initiation  attempt was unsuccessful.\n\n\n Please, reply with the a correct numerical amount that you want to pay to fund your wallet.";

                        $question_array['progress_tracking'] = "uncompleted";
                        $question_array['current_operation'] = "fund wallet";
                        $question_array['current_sub_operation'] = "get fund amount mcash";
                        $question_array['validation_status'] = false;
                    }

                    $question_array['bot_response_text'] = $text ;

                        break;
                    case 'choose payment channel':
                        /*
                            We will check the payment channels selected by the user and append the appropriate sub operation
                        */
                        if(strtolower($response_text) === "card")
                        {

                            $question_array['progress_tracking'] = "uncompleted";
                            $question_array['current_operation'] = "fund wallet";
                            $question_array['current_sub_operation'] = "get fund amount paystack";
                            $question_array['validation_status'] = true;

                            $text = "You are about to initiate a paystack transaction. \n\n Please, can you tell me how much  money to fund your wallet. \n\n Please Reply with the amount in naira.";

                        }
                        elseif (strtolower($response_text) === "ussd")
                        {
                            $question_array['progress_tracking'] = "uncompleted";
                            $question_array['current_operation'] = "fund wallet";
                            $question_array['current_sub_operation'] = "get fund amount ussd";
                            $question_array['validation_status'] = true;

                            $text = "You are about to initiate a USSD transaction. \n\n Please, can you tell me how much  money to fund your wallet. \n\n Please Reply with the amount in naira.";
                        }
                        elseif (strtolower($response_text) === "mcash")
                        {
                            $question_array['progress_tracking'] = "uncompleted";
                            $question_array['current_operation'] = "fund wallet";
                            $question_array['current_sub_operation'] = "get fund amount mcash";
                            $question_array['validation_status'] = true;

                            $text = "You are about to initiate a mCash transaction. \n\n Please, can you tell me how much  money to fund your wallet. \n\n Please Reply with the amount in naira.";

                        }
                        else
                        {
                            $returned_bot_parameter  = [];
                            $question_array = $this->promptToFundWallet($receiver_name, $returned_bot_parameter);
                            $text = $question_array['bot_response_text'];
                        }
                        $question_array['bot_response_text'] = $text ;
                        //$this_user = User::find($last_activity->user_id);

                        break;
                    case 'validate paystack payment':

                        $text = "You have finished the payment process. Please proceed and make payment";

                        $question_array['progress_tracking'] = 'completed';
                        $question_array['current_operation'] = 'fund account';
                        $question_array['current_sub_operation'] = 'authentication completed';
                        $question_array['validation_status'] =  true;
                        $question_array['bot_response_text'] = $text ;


                        break;
                    default:
                        break;
                }

                break;
            case 7:

                $activity_log_decoded_bot_parameter = json_decode($last_activity->bot_action_parameter, true);

                /* Do a switch that analyzes the current_operation parameter  */

                switch (strtolower($activity_log_decoded_bot_parameter['current_sub_operation']))
                    /* Get Decoded Bot Parameter current_operation */
                {
                    case 'get game play amount':

                        //Check that user $user_game_play_amount is actually numeric, then allow to check balance

                        $user_game_play_amount =  $response_text;
                        if(is_numeric($user_game_play_amount))
                        {
                            $question_array['has_parameters'] = true;
                            $question_array['user_game_play_amount'] =  (int) $user_game_play_amount ;
                            $question_array['validation_status'] = true;

                            $receiver_name_without_number =  preg_replace('/[0-9]+/', '', $receiver_name);


                            //Get User Model
                            $this_user = User::find($last_activity->user_id);
                            // Get Wallet information from Purchase, paystack and reward table
                            $user_wallet = new Wallet();

                            //Make Purchase from Wallet
                            $game_play_amount_option = [  "amount" => $user_game_play_amount /* Unit amount to be subtracted for game play */,
                                "phone" => $this_user->phone,
                                "info" => "Game Play",
                                'registration_channel_id' => $this->registration_channel_id
                            ];

                            $game_play_amount =  $user_wallet->buy($game_play_amount_option);

                            //Check if response is true or false
                            //if true sent positive message
                            //if false ask that they return fund wallet
                            if($game_play_amount['response'])
                            {
                                $question_array['progress_tracking'] = "uncompleted";
                                $question_array['current_operation'] = "start game play";
                                $question_array['current_sub_operation'] = "choose reward type";
                                $question_array['bot_response_text'] =

                                    "Oga! " . $receiver_name_without_number . ", you are about to start Prewin games. Please review your payment information below.\n\n You are about play this game with ". $user_game_play_amount . " naira \n\n  Your wallet balance is : " . $user_wallet->balance   . " naira. \n\n\n  Please choose from Games/Rewards options \n\n 'Mega Pay' ( This rewards you " . $user_game_play_amount ." x 10 as soon as you answer all 10 questions correctly )\n\n 'Other Pay' ( This rewards you ".  $user_game_play_amount . " x 2.5 if you score 4/5 and another " . $user_game_play_amount. " x 2.5 when you score 8/10 in the entire questions )";

                            }
                            else
                            {
                                $question_array['progress_tracking'] = "uncompleted";
                                $question_array['current_operation'] = "start game play";
                                $question_array['current_sub_operation'] = "get game play amount";
                                $question_array['user_game_play_amount'] =  null  ;
                                $question_array['validation_status'] = false;
                                $question_array['bot_response_text'] =

                                    $receiver_name_without_number . ".\n There was an error, you have: \n\n ".    $game_play_amount['message'] . " \n\n\nPlease reply with 'Fund Account' in other to fund your wallet.";

                            }

                        }
                        else
                        {
                            $question_array['progress_tracking'] = "uncompleted";
                            $question_array['current_operation'] = "start game play";
                            $question_array['current_sub_operation'] = "get game play amount";
                            $question_array['user_game_play_amount'] =  null  ;
                            $question_array['validation_status'] = false;

                            $receiver_name_without_number =  preg_replace('/[0-9]+/', '', $receiver_name);


                            //Get User Model
                            $this_user = User::find($last_activity->user_id);

                            $question_array['bot_response_text'] =

                                $receiver_name_without_number . ".\n There was an error, please ensure you reply with a numerical amount of money. \n\n\n.";

                        }


                        break;
                    case 'choose reward type':

                        // Pick any one of the options and act accordingly
                        switch (strtolower($response_text))
                        {
                            case "mega pay":
                                $question_array['progress_tracking'] = "completed";
                                $question_array['user_game_play_amount'] = $activity_log_decoded_bot_parameter['user_game_play_amount'] ;
                                $question_array['reward_type'] = "mega pay" ;
                                $text =  "You choose 'Mega Pay' and you are about to start playing for " .  $activity_log_decoded_bot_parameter['user_game_play_amount'] . " naira. You have 5 minutes for all 10 question. Good Luck!! \n\n please reply with 'start game";

                                $question_array['current_operation'] = "start game play";
                                $question_array['current_sub_operation'] = "choose reward type";
                                $question_array['validation_status'] = true;
                                $question_array['has_parameters'] = true;
                                break;
                            case "other pay":
                                $question_array['has_parameters'] = true;
                                $question_array['progress_tracking'] = "completed";
                                $question_array['user_game_play_amount'] = $activity_log_decoded_bot_parameter['user_game_play_amount'] ;
                                $question_array['reward_type'] = "other pay" ;
                                $text =  "You choose 'Other Pay' and you are about to start playing for ". $activity_log_decoded_bot_parameter['user_game_play_amount'] . " naira. You have 5 minutes for all 10 question. Good Luck!! \n\n please reply with 'start game";

                                $question_array['current_operation'] = "start game play";
                                $question_array['current_sub_operation'] = "choose reward type";
                                $question_array['validation_status'] = true;
                                $question_array['has_parameters'] = true;
                                break;
                            default:
                                $question_array['has_parameters'] = true;
                                $question_array['progress_tracking'] = "uncompleted";
                                $question_array['current_operation'] = "start game play";
                                $question_array['current_sub_operation'] = "choose reward type";
                                $question_array['validation_status'] = true;
                                $question_array['user_game_play_amount'] = $activity_log_decoded_bot_parameter['user_game_play_amount'] ;
                                $text =  "You must choose either 'Other pay' or 'Mega pay'!";
                                break;
                        }

                        $question_array['bot_response_text'] = $text;

                        break;
                    default:
                        break;
                }

                break;
            case 8:

                //Due to the requirement of the 'other pay', then two new score subsets properties or variables will be introduced into the json parameter. The properties will be called  first_half_score and second_half_score


                //Get one randomized Question and the answers and set it to the "Question" property and Option A, B, C and D as answers
                //Compute the "score" by checking if answer is correct, increment by one or else leave score has is
                //Set The Time Left for Question Session.


                //Get one randomized Question and the answers and set it to the "Question" property and Option A, B, C and D as answers

                $questions = Question::where([ 'is_pretest' => 0])->take(50)->with('answers');
                $questions = $questions->get();
//                $one_randomized_question = $questions->random();
                $random_index = random_int(0, ($questions->count() - 1));

                $one_randomized_question = $questions->get($random_index);

                $question_array['bot_response_text'] = "";


                $answer_option_template = ["a","b","c","d","e", "f", "g", "h" ]; //template for assigning answer


                $question_array['question'] =  preg_replace("/&#?[a-z0-9]{2,8};/i","",strip_tags( $one_randomized_question->statement ));
                $question_array['question_id'] =  $one_randomized_question->id;
                $answer_count = 0;
                $answer_option_count = 0;
                foreach ($one_randomized_question->answers as $answer_key =>  $each_answers)
                {

                    $each_answer_option = $answer_option_template[$answer_count++];
                    $question_array['answer_array'][$each_answer_option] = $each_answers->id;
                    $question_array['option'.$each_answer_option] = $each_answers->statement;

                    if($each_answers->correct == "Yes") //
                    {
                        $question_array['correctOption'] = $answer_option_template[$answer_key];
                        $question_array['correct_answer_array'][$each_answer_option] = $each_answers->id;
                    }


                    $answer_option_count++;
                }


                Log::info(  json_encode($question_array) );
                //Compute the "score" by checking if answer is correct, increment by one or else leave score has is

                $activity_log_decoded_bot_parameter = json_decode($last_activity->bot_action_parameter, true);
                $correct_option_activity = $activity_log_decoded_bot_parameter['correctOption'];

                $game_details_array['correct_answer_id'] = $activity_log_decoded_bot_parameter['answer_array'][$correct_option_activity];
                $game_details_array['chosen_answer_id'] =  $activity_log_decoded_bot_parameter['answer_array'][strtolower($response_text)];

                if($correct_option_activity == strtolower($response_text) )
                {
                    $question_array['score'] =  $activity_log_decoded_bot_parameter['score'] + 1 ;
                    //If correct option is same as text returned ,increase score by one

                    if($activity_log_decoded_bot_parameter['questions_count'] <= ($activity_log_decoded_bot_parameter['total_questions']/2) )
                    {
                        /* We are at the first half of the question session  */
                        $question_array['first_half_score'] =  $activity_log_decoded_bot_parameter['first_half_score'] + 1;
                        $question_array['second_half_score'] =  $activity_log_decoded_bot_parameter['second_half_score'];
                    }
                    else
                    {
                        /* We are at the second half of the question session  */
                        $question_array['second_half_score'] =  $activity_log_decoded_bot_parameter['second_half_score'] + 1;
                        $question_array['first_half_score'] =  $activity_log_decoded_bot_parameter['first_half_score'];
                    }
                }
                else
                {
                    $question_array['score'] =  $activity_log_decoded_bot_parameter['score'];
                    $question_array['first_half_score'] =  $activity_log_decoded_bot_parameter['first_half_score'];
                    $question_array['second_half_score'] =  $activity_log_decoded_bot_parameter['second_half_score'];
                    //If not  correct option is same as text returned , score remains te same
                }

                $question_array['questions_count'] = $activity_log_decoded_bot_parameter['questions_count'] + 1; // increment the question count
                //After Score is computed, we will analyze the score and allocate score and maybe reward according to rule defined

                $questions_count  = $activity_log_decoded_bot_parameter['questions_count'];
                $total_questions_count  = $activity_log_decoded_bot_parameter['total_questions'];
                $reward_option   = $activity_log_decoded_bot_parameter['reward_type'];
                $first_half_score =  $question_array['first_half_score']; // We will use the latest 'first_half_score'
                $game_unique_identifier = $activity_log_decoded_bot_parameter['game_unique_identifier'];
                $start_time = $activity_log_decoded_bot_parameter['start_time'];
                $total_actual_winning =   $activity_log_decoded_bot_parameter['total_actual_winning'];
                $previous_game_status_id = $activity_log_decoded_bot_parameter['previous_game_status_id'];
                $user_game_play_amount = $activity_log_decoded_bot_parameter['user_game_play_amount'] ;

                $game_details_array['game_unique_identifier'] = $activity_log_decoded_bot_parameter['game_unique_identifier'];
                $game_details_array['question_id'] = $activity_log_decoded_bot_parameter['question_id'];
                $game_details_array['question_number'] = $activity_log_decoded_bot_parameter['questions_count'];
                $game_details_array['game_start_time'] = $activity_log_decoded_bot_parameter['start_time'];
                $game_details_array['current_game_score'] = $question_array['score'];

                // Retrieve Game Model using $game_unique_identifier and get game_id
                $retrieved_game_model = null; // retrieved game model
                $game_models =  Game::where('game_unique_identifier',  '=' , $game_unique_identifier )->get();
                if(count($game_models) > 0)
                {
                    $retrieved_game_model = $game_models->first();
                }


                switch (strtolower($reward_option))
                {
                    case 'other pay':

                        if($questions_count == ($total_questions_count/2))/* we are at half of the question lenght */
                        {
                            /* Make attempt to reward if user scores 4 out of 5 */

                            $get_user = User::find( $last_activity->user_id);

                            $user_wallet = new Wallet();

                            if($first_half_score >= 4 )
                            {
                                /* If the user scored 4 or more out of 5, added 2.5 * 100 ( game play amount ) to the user wallet     */
                                $amount_rewarded  = $user_game_play_amount * 2.5;
                                //Find the user Details
                                $this_user = User::find($last_activity->user_id);
                                $reward_data = ['phone' => $this_user->phone, 'amount' => $amount_rewarded, 'info' => "Game Play" , 'registration_channel_id' =>  $this->registration_channel_id  ];
                                //Create a Reward Model and Store reward
                                Reward::create($reward_data);

                                $user_wallet = new Wallet();

                                $user_wallet->init($this_user->phone);

                                $receiver_name_without_number =  preg_replace('/[0-9]+/', '', $receiver_name);
                                $text = "☺️☺️ Congrats!" . $receiver_name_without_number . ",  you have been rewarded " . $amount_rewarded . " naira.\n\n\n Your wallet balance is : " . $user_wallet->balance . "naira \n\n\n  You scored " . $first_half_score . " out of " . ( $activity_log_decoded_bot_parameter['total_questions'] / 2) . "\n\n\nContinue to answer more questions for a chance to win more prizes.\n\n\n\n ";
                                $question_array['bot_response_text'] .= $text;



                                $user_wallet->init($get_user->phone);
                                $total_actual_winning = $total_actual_winning + ($user_game_play_amount * 2.5);


                                // if retrieved game model exists , create new games_status_tracker using appropriate parameter for this part of the application
                                if(!is_null($retrieved_game_model))
                                {
                                    $game_status_tracker_data =
                                    [
                                                'game_id' => $retrieved_game_model->id, /* For facebook */
                                                'game_status_id' => 4,  /* This is the id of started on the game type table */
                                                'expected_winning' => $user_game_play_amount * 5,
                                                'actual_winning' => $user_game_play_amount * 2.5,
                                                'score' => $first_half_score, /* We just started playing */
                                                'wallet_balance' => $user_wallet->balance,
                                                'total_actual_winning' =>  $total_actual_winning,
                                                'start_time' => $start_time,
                                                'end_time' => null,
                                                'amount_staked' => $user_game_play_amount,  /* Amount the player used for playing the game  */
                                                'previous_game_status_id' => $previous_game_status_id,  /* the game_status_id now  will be 0 at start of the game  */
                                    ];

                                    GameStatusTracker::create($game_status_tracker_data);
                                    //Update games table according here : game_state_changed and updated_at

                                    $retrieved_game_model->updated_at = Carbon::now();
                                    $retrieved_game_model->game_state_changed = 1;
                                    $retrieved_game_model->save();
                                }

                                $previous_game_status_id = 4;
                            }
                            else
                            {
                                /* Negative situations will now be checked in other to record loss status of a game   */

                                if(!is_null($retrieved_game_model))
                                {
                                    $game_status_tracker_data =
                                        [
                                            'game_id' => $retrieved_game_model->id, /* For facebook */
                                            'game_status_id' => 5,  /*  Loss status */
                                            'expected_winning' => $user_game_play_amount * 5,
                                            'actual_winning' => 0,
                                            'score' => $first_half_score, /* We just started playing */
                                            'wallet_balance' => $user_wallet->balance,
                                            'total_actual_winning' =>  $total_actual_winning,
                                            'start_time' => $start_time,
                                            'end_time' => null,
                                            'amount_staked' => $user_game_play_amount,  /* Amount the player used for playing the game  */
                                            'previous_game_status_id' => $previous_game_status_id,  /* the game_status_id now  will be 0 at start of the game  */
                                        ];

                                    GameStatusTracker::create($game_status_tracker_data);
                                    //Update games table according here : game_state_changed and updated_at

                                    $retrieved_game_model->updated_at = Carbon::now();
                                    $retrieved_game_model->game_state_changed = 1;
                                    $retrieved_game_model->save();
                                }

                                $previous_game_status_id = 5;

                            }

                        }

                     break;
                }

                //Always increase question count by one always

                $game_details_array['current_game_status_id'] = $previous_game_status_id;

                $question_array['question_id'] =  $one_randomized_question->id;
                $question_array['total_questions'] = 10;
                $question_array['time_allowed'] = "5:00";
                $question_array['has_parameters'] = true;
                $question_array['game_unique_identifier'] = $game_unique_identifier;
                $question_array['start_time'] = $start_time;
                $question_array['total_actual_winning'] = $total_actual_winning;
                $question_array['previous_game_status_id'] =  $previous_game_status_id; // previous_game_status_id has now changed to in progress/pending
                $question_array['user_game_play_amount'] = $activity_log_decoded_bot_parameter['user_game_play_amount'];
                $question_array['question_start_timestamp'] = $activity_log_decoded_bot_parameter['question_start_timestamp'];
                // Dont ever update or touch or manipulate this value here or else you are cheating

                //Set The Time Left for Question Session.

                $t = Carbon::now(); // The time now

//                $r = Carbon::createFromTimestamp($activity_log_decoded_bot_parameter['question_start_timestamp']);// Get the time the last activity log was recorded
                $r = Carbon::createFromTimestamp($last_activity->time_initiated);// Get the time the last activity log was recorded

//                $diff_in_minutes = $t->diffInMinutes($r); // get the difference in minutes
                $diff_in_seconds = $t->diffInSeconds($r); // get the difference in seconds

//                //Set The Time Left for Question Session.
//                $question_array['time_left'] = $activity_log_decoded_bot_parameter['time_left'] - $diff_in_minutes; // Subtract the difference in minutes from the time left
                // Set the time for Question Session
//                $time_left_in_seconds = 60 *  $activity_log_decoded_bot_parameter['time_left'] ; // Convert time_left to seconds
                $time_left_in_seconds = $this->timeToSeconds($activity_log_decoded_bot_parameter['time_left']); // 60 *   ; // Convert time_left to seconds
                Log::info($activity_log_decoded_bot_parameter['time_left'] ."Sec1" . $time_left_in_seconds );
                $time_left_diff =  $time_left_in_seconds - $diff_in_seconds;// Then, subtract the diff_in_second computed in line 3384 from $time_left_in_seconds

//                $time_left_diif_in_minutes = round($time_left_diff / 60); // Convert time in seconds back to minutes
                $time_left_diif_in_minutes = $this->secondsToTimeFormatted($time_left_diff); // round($time_left_diff / 60); // Convert time in seconds back to minutes


                Log::info($time_left_diff. "Sec2".  $time_left_diff. "and ". $time_left_diif_in_minutes );


                $question_array['time_left'] =  $time_left_diif_in_minutes;

                //Check and ensure that -ve time values are reset to zero
                if(  $time_left_diff <= 0)
                {
                    $question_array['time_left'] = 0;
                }

                //Build response Text
                $question_array['bot_response_text'] .=

                    "Question No: " . $question_array['questions_count'] . "\n".
                    "Time Remaining in Minutes: ".  $question_array['time_left'] . " out of ".  $question_array['time_allowed'] ."\n".
                    "Your Score: ".  $question_array['score'] . " out of ".  $question_array['total_questions'] ."\n"."------------------------------\n\n". $question_array['question'] . "\n" .
                    "------------------------------\n\n".
                    "Answers ( Reply with  A, B, C or D ) : A. ".  $question_array['optiona'] . "   B. ".  $question_array['optionb']. "  C. ". $question_array['optionc'] . "  D. ". $question_array['optiond'] ."\n";


                $game_details_array['game_id'] = ( isset($retrieved_game_model) and !is_null($retrieved_game_model) ) ? $retrieved_game_model->id : 0;

                Log::info(  json_encode($game_details_array));

                GameDetail::create($game_details_array);

                //return Json back to caller
                break;
            case 12/* For Asking for user Name, coming from Asking for user phone number */:
                //Further processing at this botaction id  will be done here
                // //These include saving user name to the database

                $activity_log_decoded_bot_parameter = json_decode($last_activity->bot_action_parameter, true);

                /* Do a switch that analyzes the current_operation parameter  */

                switch (strtolower($activity_log_decoded_bot_parameter['current_sub_operation']))
                    /* Get Decoded Bot Parameter current_operation */
                {
                    case 'sms verification':

                        //Check that response_text is not empty and that it matches value in sms_verification_code
                        //or else send back reply saying sms code those not match then set to sms verification

                        $get_user = User::find( $last_activity->user_id);

                        $first_name = !is_null($get_user->othernames) ? $get_user->othernames : "";
                        $last_name = !is_null($get_user->surname) ? $get_user->surname : "";
                        $full_name =  $first_name . " " . $last_name;

                        if(!empty($response_text) and ($response_text == $get_user->sms_verification_code))
                        {

                            $get_user->sms_verified = 1;
                            $get_user->updated_at = Carbon::now();
                            $get_user->save();

                            $text = "⭐️ ⭐️ Thank you ⭐️ ⭐️, " .  $full_name . " ☺️! \n\n\n You now have full access to the prewin service \n\n choose the option below to get started. \n\n Demo\n fund account \n play game\n reward\nreset password\ncheck balance";


                            $question_array['progress_tracking'] = 'completed';
                            $question_array['current_operation'] = 'sms verification';
                            $question_array['current_sub_operation'] = 'sms verification';
                            $question_array['validation_status'] =  true;
                            $question_array['show_template'] = true;
                            $question_array['template_option_array'] = [ "demo", "fund account", "play game", "reward", "reset password", "check balance"  ];

                        }
                        else
                        {
                            $text = "⛔ ️ Sorry ️, " .  $full_name . " ☺️! \n\n\n The SMS code do not match. Please reply with the sms verification code send to this number: " . $get_user->phone . " Thank you. ";

                            $question_array['progress_tracking'] = 'uncompleted';
                            $question_array['current_operation'] = 'sms verification';
                            $question_array['current_sub_operation'] = 'sms verification';
                            $question_array['validation_status'] =  true;
                            $question_array['show_template'] = false;
                            $question_array['template_option_array'] = ["change name" , "demo", "fund account", "play game", "reward", "reset password", "check balance"  ];


                        }
                        $question_array['bot_response_text'] = $text ;
                        break;
                    case "process age restriction":
                        //If the response text is Yes, we will pass them to the next sub activity which is get phone number
                        //If the response is NO, We will tell them that service cannot be rendered , delete their activity log from the app and
                        // delete their user row based on the facebook



                        if(strtolower($response_text) == "yes")
                        {
                            $this_user = User::find($last_activity->user_id);
                            $sms_code = $this->create_random_number(6);
                            $this_user->sms_verification_code =  $sms_code;
                            $this_user->save();

                            $phone_number = $this_user->phone;

                            $this_user->sms_verification_code =  $sms_code;
                            $first_name = !is_null($this_user->othernames) ? $this_user->othernames : "";
                            $last_name = !is_null($this_user->surname) ? $this_user->surname : "";
                            $full_name =  $first_name . " " . $last_name;


                            Log::info("Send Message from VAS Gateway ===> " . $phone_number  . " and SMS Verification Code: ". $sms_code  );
                            $sms_gateway = new  SMSGatewayController();
                            $sms_gateway->triggerSMS( $phone_number, "SMS Verification Code: ". $sms_code  );

                            $display = \rawurlencode("SMS Verification Code: ". $sms_code );

                            $link="http://www.estoresms.com/smsapi.php?username=Akingbenga&password=7581071m6518&sender=prewin&recipient=" . $phone_number . "&message=" .$display."&dnd=true";

//                            @file_get_contents($link); // Do a GET request and send SMS Verification code to the user


                            $text = "⭐️ ⭐️ Thank you ⭐️ ⭐️, " . $full_name  . " ☺️!. \n\n\n An SMS Verification code has been sent your phone number. Please reply with the 6-digit code. \n\nThank you!";


                            $question_array['progress_tracking'] = "uncompleted";;
                            $question_array['current_operation'] = "sms verification";
                            $question_array['current_sub_operation'] = "sms verification";
                            $question_array['has_parameters'] = true;
                            $question_array['bot_response_text'] = $text;


                        }
                        elseif(strtolower($response_text) == "no")
                        {
                            $get_user = User::find($last_activity->user_id);
                            $text = "⛔ Sorry, You cannot access this service because Prewin Games is an 18 years and above product. Thank you.";

                            ActivityLog::where('user_id',  '=' , $last_activity->user_id  )->delete(); //delete all activity log whith this user id
                            $get_user->forceDelete();// Then delete the user record of this player


                            $question_array['progress_tracking'] = "uncompleted";
                            $question_array['current_operation'] = "process age restriction";
                            $question_array['current_sub_operation'] = "process age restriction - refused";
                            $question_array['has_parameters'] = true;
                            $question_array['bot_response_text'] = $text;


                        }
                        else
                        {

                            $text = "⛔ Sorry, You have to reply with 'Yes' or 'No' so that we confirm that you are 18 years or above. Thank you.\n\n\n *Are you 18 years and above?*\n\n Please reply with: \n *Yes* \n or \n *No*";



                            $question_array['progress_tracking'] = "uncompleted";
                            $question_array['current_operation'] = "process age restriction";
                            $question_array['current_sub_operation'] = "process age restriction - unconfirmed";
                            $question_array['has_parameters'] = true;
                            $question_array['bot_response_text'] = $text;

                        }
                        // there ought to be an else to catch any other word that is not yes or no
                        break;

                    default:

                        $question_array['progress_tracking'] = 'completed';
                        $question_array['current_operation'] = 'user name';
                        $question_array['current_sub_operation'] = 'change name';
                        $question_array['validation_status'] =  true;
                        $question_array['show_template'] = true;
                        $question_array['template_option_array'] = ["demo", "fund account", "play game", "reward", "reset password", "check balance"  ];


                        $text =  $response_text . ", choose the option below to get started. \n\n Demo\n fund account \n play game\n reward\nreset password\ncheck balance";

                        $question_array['bot_response_text'] = $text ;
                }

                break;
            case 13/* For Asking for user Bank Name, User Bank Account Number, initiating Payout*/:
                //Further processing at this botaction id  will be done here
                // //These include asking for user bank name, bank account number , OTP verification

                $activity_log_decoded_bot_parameter = json_decode($last_activity->bot_action_parameter, true);

                /* Do a switch that analyzes the current_operation parameter  */

                switch (strtolower($activity_log_decoded_bot_parameter['current_sub_operation']))
                    /* Get Decoded Bot Parameter current_operation */
                {
                    case 'check user password':
                        //Get User Password and check that its good or else go back and ask for password again

                        $get_user = User::find( $last_activity->user_id);
                        $password = $response_text;
                        $checked_hashed_password =  Hash::check($password ,  $get_user->password);
                        if($checked_hashed_password)
                        {
                            $bank_code_array = BankCode::all()->pluck('name')->toArray();
                            $bank_code_stringy =  implode( $bank_code_array , "\n"  );

                            $text = "*😄 Thank you, your password is correct.* \n\n Please, choose your bank from the supported bank list below and provide your bank account name , bank account number separated by comma. \n\n\n  *For example ( Access Bank, Oluwatomisin Uchendu Mark , 0123456789 )* \n\n\n " .  $bank_code_stringy;
                            $question_array['progress_tracking'] = "uncompleted";
                            $question_array['current_operation'] = "withdraw money";
                            $question_array['current_sub_operation'] = "get all withdrawal details";
                            $question_array['validation_status'] = true;
                            $question_array['bank_name'] = null;
                            $question_array['bank_account_name'] = null;
                            $question_array['bank_account_no'] = null;
                            $question_array['password_checked'] = true;
                            $question_array['bank_sort_code'] = null;
                            $question_array['has_parameters'] = true;
                            $question_array['bot_response_text'] = $text;
                            $question_array['show_template'] = false;
                            $question_array['template_option_array'] = $bank_code_array;
                        }
                        else
                        {
                            $text = "⚠️ Sorry, the password you provided is incorrect. \n\n Please can you reply with your correct password so that you can proceed with fund withdrawal. \n\n Just Reply with your password or reply with 'reset password' to reset your password";

                            $question_array['progress_tracking'] = "uncompleted";
                            $question_array['current_operation'] = "withdraw money";
                            $question_array['current_sub_operation'] = "check user password";
                            $question_array['validation_status'] = true;
                            $question_array['bank_name'] = null;
                            $question_array['bank_account_name'] = null;
                            $question_array['bank_account_no'] = null;
                            $question_array['password_checked'] = false;
                            $question_array['bank_sort_code'] = null;
                            $question_array['has_parameters'] = true;
                            $question_array['bot_response_text'] = $text;
                            $question_array['show_template'] = true;
                            $question_array['template_option_array'] = ['reset password'];
                        }

                        $question_array['bot_response_text'] = $text ;

                        break;
                    case 'check user password backup':
                        //Get User Password and check that its good or else go back and ask for password again

                        $get_user = User::find( $last_activity->user_id);
                        $password = $response_text;
                        $checked_hashed_password =  Hash::check($password ,  $get_user->password);
                        if($checked_hashed_password)
                        {
                            $bank_code_array = BankCode::all()->pluck('name')->toArray();
                            $bank_code_stringy =  implode( $bank_code_array , "\n"  );

                            $text = "*😄 Thank you, your password is correct.* \n\n Please, choose your bank from the supported bank list below. \n\n\n" .  $bank_code_stringy;
                            $question_array['progress_tracking'] = "uncompleted";
                            $question_array['current_operation'] = "withdraw money";
                            $question_array['current_sub_operation'] = "get bank";
                            $question_array['validation_status'] = true;
                            $question_array['bank_name'] = null;
                            $question_array['bank_account_name'] = null;
                            $question_array['bank_account_no'] = null;
                            $question_array['password_checked'] = true;
                            $question_array['bank_sort_code'] = null;
                            $question_array['has_parameters'] = true;
                            $question_array['bot_response_text'] = $text;
                            $question_array['show_template'] = false;
                            $question_array['template_option_array'] = $bank_code_array;
                        }
                        else
                        {
                            $text = "⚠️ Sorry, the password you provided is incorrect. \n\n Please can you reply with your correct password so that you can proceed with fund withdrawal. \n\n Just Reply with your password or reply with 'reset password' to reset your password";

                            $question_array['progress_tracking'] = "uncompleted";
                            $question_array['current_operation'] = "withdraw money";
                            $question_array['current_sub_operation'] = "check user password";
                            $question_array['validation_status'] = true;
                            $question_array['bank_name'] = null;
                            $question_array['bank_account_name'] = null;
                            $question_array['bank_account_no'] = null;
                            $question_array['password_checked'] = false;
                            $question_array['bank_sort_code'] = null;
                            $question_array['has_parameters'] = true;
                            $question_array['bot_response_text'] = $text;
                            $question_array['show_template'] = true;
                            $question_array['template_option_array'] = ['reset password'];
                        }

                        $question_array['bot_response_text'] = $text ;

                        break;
                    case 'get all withdrawal details':

                        $password_check_status = $activity_log_decoded_bot_parameter['password_checked'];
                        if($password_check_status)
                        {
                            $bank_code_array = BankCode::all()->pluck('name')->toArray();
                            $bank_code_stringy =  implode( $bank_code_array , "\n"  );

                            //Split the value by comma
                            $user_response_array = explode(",", $response_text);

                            $user_wallet = new Wallet();
                            $get_user = User::find($last_activity->user_id);

                            $user_wallet->init($get_user->phone);

                            //Check that array is not false and has 3 element exactly
                            //Expected array elements [ 0 => Bank Name, 1=> Bank Account Name, 2 => Bank Account Number ]
                            if ( ($user_response_array === false ) or (count($user_response_array) < 2  ) or   (count($user_response_array) !== 3) )
                            {


                                $text = "⚠️ Sorry, the details supplied is incomplete!\n\n Please, choose your bank from the supported bank list below and provide your bank account name , bank account number separated by comma. \n\n\n  *For example ( Access Bank, Oluwatomisin Uchendu Mark , 0123456789 )* \n\n\n \" .  $bank_code_stringy;";

                                $question_array['progress_tracking'] = "uncompleted";
                                $question_array['current_operation'] = "withdraw money";
                                $question_array['current_sub_operation'] = "get all withdrawal details";
                                $question_array['validation_status'] = true;
                                $question_array['bank_name'] = null;
                                $question_array['bank_account_name'] = null;
                                $question_array['bank_account_no'] = null;
                                $question_array['password_checked'] = true;
                                $question_array['bank_sort_code'] = null;
                                $question_array['has_parameters'] = true;
                                $question_array['bot_response_text'] = $text;
                                $question_array['show_template'] = false;
                                $question_array['template_option_array'] = $bank_code_array;


                            }
                            else if( !in_array($user_response_array[0], $bank_code_array  )  or empty($user_response_array[0])  )
                            {
                                //Cannot proceed - Bank account number must be in numeric, exactly 10 digits and not empty
                                $text = "⚠️ Sorry, your bank , " . $user_response_array[0]. ", is not among the list of supported banks! \n\n Please, choose your bank from the supported bank list below and provide your bank account name , bank account number separated by comma. \n\n\n  *For example ( Access Bank, Oluwatomisin Uchendu Mark , 0123456789 )* \n\n\n \" .  $bank_code_stringy;";

                                $question_array['progress_tracking'] = "uncompleted";
                                $question_array['current_operation'] = "withdraw money";
                                $question_array['current_sub_operation'] = "get all withdrawal details";
                                $question_array['validation_status'] = true;
                                $question_array['bank_name'] = null;
                                $question_array['bank_account_name'] = null;
                                $question_array['bank_account_no'] = null;
                                $question_array['password_checked'] = true;
                                $question_array['bank_sort_code'] = null;
                                $question_array['has_parameters'] = true;
                                $question_array['bot_response_text'] = $text;
                                $question_array['show_template'] = false;
                                $question_array['template_option_array'] = $bank_code_array;

                            }
                            else if (empty($user_response_array[2])  and (strlen($user_response_array[2]) != 10) and !is_numeric($user_response_array[2]))
                            {
                                //Cannot proceed - Bank account number must be in numeric, exactly 10 digits and not empty
                                $text = "⚠️ Sorry, please ensure your bank account number is in 10 digits!\n\n Please, choose your bank from the supported bank list below and provide your bank account name , bank account number separated by comma. \n\n\n  *For example ( Access Bank, Oluwatomisin Uchendu Mark , 0123456789 )* \n\n\n \" .  $bank_code_stringy;";

                                $question_array['progress_tracking'] = "uncompleted";
                                $question_array['current_operation'] = "withdraw money";
                                $question_array['current_sub_operation'] = "get all withdrawal details";
                                $question_array['validation_status'] = true;
                                $question_array['bank_name'] = null;
                                $question_array['bank_account_name'] = null;
                                $question_array['bank_account_no'] = null;
                                $question_array['password_checked'] = true;
                                $question_array['bank_sort_code'] = null;
                                $question_array['has_parameters'] = true;
                                $question_array['bot_response_text'] = $text;
                                $question_array['show_template'] = false;
                                $question_array['template_option_array'] = $bank_code_array;

                            }
                            else
                            {
                                $bank_name =   trim($user_response_array[0]);
                                $bank_account_name =   trim($user_response_array[1]);
                                $bank_account_number =   trim($user_response_array[2]);
                                $user_bank_code = BankCode::where('name', $user_response_array[0])->get()->first()->code;

                                $text = "Your choosen bank is *" . $bank_name . "* \n\n Your bank account name is *" . $bank_account_name .  "* \n\n Your bank account number is *" . $bank_account_number  . "* \n\n. Your wallet balance is *" . $user_wallet->balance . "*\n\n\n Please, tell me how much money you want to withdraw from your wallet?";

                                $question_array['progress_tracking'] = "uncompleted";
                                $question_array['current_operation'] = "withdraw money";
                                $question_array['current_sub_operation'] = "send OTP";
                                $question_array['validation_status'] = true;
                                $question_array['bank_account_name'] = $bank_account_name;
                                $question_array['bank_name'] = $bank_name;
                                $question_array['bank_account_no'] =  $bank_account_number;
                                $question_array['bank_sort_code'] = $user_bank_code;
                                $question_array['password_checked'] = true;
                                $question_array['has_parameters'] = true;
                                $question_array['bot_response_text'] = $text;
                                $question_array['show_template'] = false;
                                $question_array['template_option_array'] = [];

                            }
                        }
                        else
                        {
                            $text = "⚠️ Sorry, the password you provided is incorrect. \n\n Please can you reply with your correct password so that you can proceed with fund withdrawal. \n\n Just Reply with your password or reply with 'reset password' to reset your password";

                            $question_array['progress_tracking'] = "uncompleted";
                            $question_array['current_operation'] = "withdraw money";
                            $question_array['current_sub_operation'] = "check user password";
                            $question_array['validation_status'] = true;
                            $question_array['bank_name'] = null;
                            $question_array['bank_account_name'] = null;
                            $question_array['bank_account_no'] = null;
                            $question_array['password_checked'] = false;
                            $question_array['bank_sort_code'] = null;
                            $question_array['has_parameters'] = true;
                            $question_array['bot_response_text'] = $text;
                            $question_array['show_template'] = true;
                            $question_array['template_option_array'] = ['reset password'];
                        }

                        $question_array['bot_response_text'] = $text ;

                        break;
                    case 'get user bank account name':
                        //Get Bank name and store in the the acivity log, it will end up in the user_banks table for this particular user_id
                        //Check that Bank name chosen is in bank_array, then Echo back bAnk choosen and as for bank account

                        ///Check password_check_sbank_sort_codetatus before doing anything or else ask for password

                        $password_check_status = $activity_log_decoded_bot_parameter['password_checked'];

                        if($password_check_status)
                        {
                            //proceed to echo user details from paytack back to user and ask for amount to be withdrawn
                            //user bank details will be saved to database on next_sub_operation
                            //
                            $computed_bank_details = "";// get fromm paystack api

                            //get user wallet and display

                            $user_wallet = new Wallet();
                            $get_user = User::find($last_activity->user_id);

                            $user_wallet->init($get_user->phone);
//                            $init_text = "Your Bank Name is " . $computed_bank_details. ". Please, tell me how much money you want to withdraw.";
                            $text = "*Your bank account name is " . $response_text. "* \n\n. Your wallet balance is " . $user_wallet->balance . "\n\n\n Please, tell me how much money you want to withdraw from your wallet?";

                            $question_array['progress_tracking'] = "uncompleted";
                            $question_array['current_operation'] = "withdraw money";
                            $question_array['current_sub_operation'] = "send OTP";
                            $question_array['validation_status'] = true;
                            $question_array['bank_account_name'] = $response_text;
                            $question_array['bank_name'] = $activity_log_decoded_bot_parameter["bank_name"];
                            $question_array['bank_account_no'] =  $activity_log_decoded_bot_parameter["bank_account_no"];
                            $question_array['bank_sort_code'] = $activity_log_decoded_bot_parameter["bank_sort_code"];
                            $question_array['password_checked'] = true;
                            $question_array['has_parameters'] = true;
                            $question_array['bot_response_text'] = $text;
                            $question_array['show_template'] = false;
                            $question_array['template_option_array'] = [];

                        }
                        else
                        {
                            $text = "You have not provided a correct password.\n\n Please, provide me with your password before proceeding to fund withdrawal.";

                            $question_array['progress_tracking'] = "uncompleted";
                            $question_array['current_operation'] = "withdraw money";
                            $question_array['current_sub_operation'] = "check user password";
                            $question_array['validation_status'] = true;
                            $question_array['bank_account_name'] = null;
                            $question_array['bank_name'] = null; //$activity_log_decoded_bot_parameter["bank_name"];
                            $question_array['bank_account_no'] =  null; //$activity_log_decoded_bot_parameter["bank_account_no"];;
                            $question_array['bank_sort_code'] = null; //$activity_log_decoded_bot_parameter["bank_sort_code"];;
                            $question_array['has_parameters'] = true;
                            $question_array['bot_response_text'] = $text;
                            $question_array['password_checked'] = false;
                        }

                        $question_array['bot_response_text'] = $text ;

                        break;
                    case 'get bank':
                        //Check password_check_status before doing anything or else ask for password
                        //ensure bank name returned is among the list provided or else reject and ask them to give a bank name that is among the list
                        $password_check_status = $activity_log_decoded_bot_parameter['password_checked'];

                        if($password_check_status)
                        {
                            $bank_code_array = BankCode::all()->pluck('name')->toArray();
                            $bank_code_stringy =  implode( $bank_code_array , "\n"  );
                            //check database, user_banks table for available bank details

                            if( in_array($response_text, $bank_code_array  ) )
                            {
                                $text = "Your bank is " . $response_text. ". Please, tell me your bank account number.";

                                //Get Bank sort code immediately, , this query will be trusted
                                $user_bank_code = BankCode::where('name', $response_text)->get()->first();

                                $question_array['progress_tracking'] = "uncompleted";
                                $question_array['current_operation'] = "withdraw money";
                                $question_array['current_sub_operation'] = "get bank account number";
                                $question_array['validation_status'] = true;
                                $question_array['bank_name'] = $response_text;
                                $question_array['bank_account_name'] = null;
                                $question_array['bank_account_no'] = null;
                                $question_array['password_checked'] = true;
                                $question_array['bank_sort_code'] = $user_bank_code->code;
                                $question_array['has_parameters'] = true;
                                $question_array['bot_response_text'] = $text;
                                $question_array['show_template'] = false;
                                $question_array['template_option_array'] = $bank_code_array;

                            }
                            else
                            {
                                $text = "Your bank , " . $response_text. ", is not among the list of supported banks. Please, select your bank name from the list below \n\n\n" . $bank_code_stringy;

                                $question_array['progress_tracking'] = "uncompleted";
                                $question_array['current_operation'] = "withdraw money";
                                $question_array['current_sub_operation'] = "get bank";
                                $question_array['validation_status'] = true;
                                $question_array['bank_name'] = null;
                                $question_array['bank_account_no'] = null;
                                $question_array['bank_sort_code'] = null;
                                $question_array['password_checked'] = true;
                                $question_array['has_parameters'] = true;
                                $question_array['bot_response_text'] = $text;
                                $question_array['show_template'] = false;
                                $question_array['template_option_array'] = $bank_code_array;
                            }

                        }
                        else
                        {
                            $text = "You have not provided a correct password.\n\n Please, provide me with your password before proceeding to fund withdrawal.";
                            $question_array['progress_tracking'] = "uncompleted";
                            $question_array['current_operation'] = "withdraw money";
                            $question_array['current_sub_operation'] = "check user password";
                            $question_array['validation_status'] = true;
                            $question_array['bank_name'] = null;
                            $question_array['bank_account_no'] = null;
                            $question_array['bank_sort_code'] = null;
                            $question_array['has_parameters'] = true;
                            $question_array['bot_response_text'] = $text;
                            $question_array['password_checked'] = false;
                        }


                        $question_array['bot_response_text'] = $text ;

                        break;
                    case 'get otp':
                        //Check password_check_status before doing anything or else ask for password
                        //ensure bank name returned is among the list provided or else reject and ask them to give a bank name that is among the list
                        $password_check_status = $activity_log_decoded_bot_parameter['password_checked'];
                        $sms_otp = $activity_log_decoded_bot_parameter['sms_verification_code'];
                        $get_user = User::find( $last_activity->user_id);

                        if($password_check_status)
                        {
                            if( $response_text === $sms_otp )
                            {
//                                $bank_name = $activity_log_decoded_bot_parameter["bank_name"];
                                $withdrawal_amount = $activity_log_decoded_bot_parameter["withdrawal_amount"];
//                                $bank_account_no = $activity_log_decoded_bot_parameter["bank_account_no"];
//                                $bank_sort_code = $activity_log_decoded_bot_parameter["bank_sort_code"];

//                                $withdrawal_response = $this->transferFundToUser('paystack', $get_user , $activity_log_decoded_bot_parameter );
                                $withdrawal_response = $this->transferFundToUser('zenith', $get_user , $activity_log_decoded_bot_parameter );
                                //Do api call to paystack to effect fund transfer to user

                                if($withdrawal_response["success"] == true) // If api call from paystack comes back as true
                                {

                                    //Perform fund reduction process
                                    //Give positive response and end process
                                    $text = "Congratulations, " . $get_user->surname . " ". $get_user->othernames. "! \n\n\n *Your bank account has been credited with  " . $withdrawal_amount. " naira*.\n\n\n " . $withdrawal_response["response_text"]. "  Thank you";

                                    $question_array['progress_tracking'] = "completed";
                                    $question_array['current_operation'] = "withdraw money";
                                    $question_array['current_sub_operation'] = "get OTP";
                                    $question_array['validation_status'] = true;
                                    $question_array['bank_name'] = $activity_log_decoded_bot_parameter["bank_name"];
                                    $question_array['bank_account_name'] = $activity_log_decoded_bot_parameter["bank_account_name"];
                                    $question_array['withdrawal_amount'] = $activity_log_decoded_bot_parameter["withdrawal_amount"];
                                    $question_array['bank_account_no'] = $activity_log_decoded_bot_parameter["bank_account_no"];
                                    $question_array['sms_verification_code'] = $activity_log_decoded_bot_parameter["sms_verification_code"];
                                    $question_array['password_checked'] = true;
                                    $question_array['bank_sort_code'] = $activity_log_decoded_bot_parameter["bank_sort_code"];
                                    $question_array['has_parameters'] = true;
                                    $question_array['bot_response_text'] = $text;
                                }
                                else
                                {
                                    //Fund transfer attempt was not successful
                                    //Complain and default to last action, send reply that user re-enter sms code again or send a mail to customer care unit
                                    //Give positive response and end process
                                    $text = "Sorry fund transfer attempt was not successful, please reply with the sms verification code sent to you previously or you can contact our customer service at customercare@prewin.com.ng . We apologize for the inconvenience";

                                    $question_array['progress_tracking'] = "uncompleted";
                                    $question_array['current_operation'] = "withdraw money";
                                    $question_array['current_sub_operation'] = "get OTP";
                                    $question_array['validation_status'] = true;
                                    $question_array['bank_name'] = $activity_log_decoded_bot_parameter["bank_name"];
                                    $question_array['bank_account_name'] = $activity_log_decoded_bot_parameter["bank_account_name"];
                                    $question_array['withdrawal_amount'] = $activity_log_decoded_bot_parameter["withdrawal_amount"];
                                    $question_array['bank_account_no'] = $activity_log_decoded_bot_parameter["bank_account_no"];
                                    $question_array['sms_verification_code'] = $activity_log_decoded_bot_parameter["sms_verification_code"];
                                    $question_array['password_checked'] = true;
                                    $question_array['bank_sort_code'] = $activity_log_decoded_bot_parameter["bank_sort_code"];
                                    $question_array['has_parameters'] = true;
                                    $question_array['bot_response_text'] = $text;

                                }

                            }
                            else
                            {
                                $text = "\n\n\n The SMS code does not match. Please reply with the sms code send to this number: " . $get_user->phone . " Thank you. ";

                                $question_array['progress_tracking'] = "uncompleted";
                                $question_array['current_operation'] = "withdraw money";
                                $question_array['current_sub_operation'] = "get OTP";
                                $question_array['validation_status'] = true;
                                $question_array['bank_name'] = $activity_log_decoded_bot_parameter["bank_name"];
                                $question_array['bank_account_name'] = $activity_log_decoded_bot_parameter["bank_account_name"];
                                $question_array['withdrawal_amount'] = $activity_log_decoded_bot_parameter["withdrawal_amount"];
                                $question_array['bank_account_no'] = $activity_log_decoded_bot_parameter["bank_account_no"];
                                $question_array['sms_verification_code'] = $activity_log_decoded_bot_parameter["sms_verification_code"];
                                $question_array['password_checked'] = true;
                                $question_array['bank_sort_code'] = $activity_log_decoded_bot_parameter["bank_sort_code"];
                                $question_array['has_parameters'] = true;
                                $question_array['bot_response_text'] = $text;
                            }

                        }
                        else
                        {
                            $text = "You have not provided a correct password.\n\n Please, provide me with your password before proceeding to fund withdrawal.";
                            $question_array['progress_tracking'] = "uncompleted";
                            $question_array['current_operation'] = "withdraw money";
                            $question_array['current_sub_operation'] = "check user password";
                            $question_array['validation_status'] = true;
                            $question_array['bank_name'] = null;
                            $question_array['bank_account_name'] = null;
                            $question_array['bank_account_name'] = null;
                            $question_array['bank_account_no'] = null;
                            $question_array['bank_sort_code'] = null;
                            $question_array['has_parameters'] = true;
                            $question_array['bot_response_text'] = $text;
                            $question_array['password_checked'] = false;
                        }


                        $question_array['bot_response_text'] = $text ;

                        break;
                    case 'send otp':
                        //Take Numerical Amount sent by user and ensure it less or equal to user balance

                        $password_check_status = $activity_log_decoded_bot_parameter['password_checked'];

                        if($password_check_status)
                        {
                            $withdrawal_amount = (float)$response_text;
                            $user_wallet = new Wallet();
                            $get_user = User::find($last_activity->user_id);

                            $user_wallet->init($get_user->phone);

                            //Ensure amount sent by user is numeric and lower or equall to balance
                            if( is_numeric($response_text ) and ( $user_wallet->balance >= $withdrawal_amount ) and ( $withdrawal_amount > 0 )  )
                            {

                                $sms_code = $this->create_random_number(6);
                                $display = \rawurlencode("SMS Verification Code for Fund Withdrawal: ". $sms_code );

                                $link="http://www.estoresms.com/smsapi.php?username=Akingbenga&password=7581071m6518&sender=prewin&recipient=" . $get_user->phone . "&message=" .$display."&dnd=true";

//                                @file_get_contents($link); // Do a GET request and send SMS Verification code to the user

                                $sms_gateway = new  SMSGatewayController();
                                $sms_gateway->triggerSMS( $get_user->phone, "SMS Verification Code for Fund Withdrawal: ". $sms_code  );
                                Log::info("Send Message from VAS Gateway ===> " . $get_user->phone  . " SMS Verification Code for Fund Withdrawal: " .  $sms_code  );


                                $text = "A SMS Verification code has been sent to your phone number. Please reply with the 6-digit code in other to proceed with fund withdrawal* \n\nThank you!";

                                Log::info("Send Message from VAS Gateway ===> " . $get_user->phone . " and SMS Verification Code for Fund Withdrawal: ". $sms_code );

                                $question_array['progress_tracking'] = "uncompleted";
                                $question_array['current_operation'] = "withdraw money";
                                $question_array['current_sub_operation'] = "get OTP";
                                $question_array['validation_status'] = true;
                                $question_array['bank_name'] = $activity_log_decoded_bot_parameter["bank_name"];
                                $question_array['bank_account_name'] = $activity_log_decoded_bot_parameter["bank_account_name"];
                                $question_array['withdrawal_amount'] = $withdrawal_amount;
                                $question_array['bank_account_no'] = $activity_log_decoded_bot_parameter["bank_account_no"];
                                $question_array['sms_verification_code'] = $sms_code;
                                $question_array['password_checked'] = true;
                                $question_array['bank_sort_code'] = $activity_log_decoded_bot_parameter["bank_sort_code"];
                                $question_array['has_parameters'] = true;
                                $question_array['bot_response_text'] = $text;

                            }
                            else
                            {
                                $text = "Please ensure the amount is in digits and is less than your wallet balance. Thank you. ";

                                $question_array['progress_tracking'] = "uncompleted";
                                $question_array['current_operation'] = "withdraw money";
                                $question_array['current_sub_operation'] = "send OTP";
                                $question_array['validation_status'] = true;
                                $question_array['bank_name'] = $activity_log_decoded_bot_parameter["bank_name"];
                                $question_array['bank_account_name'] = $activity_log_decoded_bot_parameter["bank_account_name"];
                                $question_array['withdrawal_amount'] = 0;
                                $question_array['bank_account_no'] = $activity_log_decoded_bot_parameter["bank_account_no"];
                                $question_array['sms_verification_code'] = null;
                                $question_array['password_checked'] = true;
                                $question_array['bank_sort_code'] = $activity_log_decoded_bot_parameter["bank_sort_code"];
                                $question_array['has_parameters'] = true;
                                $question_array['bot_response_text'] = $text;
                            }

                        }
                        else
                        {
                            $text = "You have not provided a correct password.\n\n Please, provide me with your password before proceeding to fund withdrawal.";

                            $question_array['progress_tracking'] = "uncompleted";
                            $question_array['current_operation'] = "withdraw money";
                            $question_array['current_sub_operation'] = "check user password";
                            $question_array['validation_status'] = true;
                            $question_array['bank_name'] = null;
                            $question_array['bank_account_name'] = null;
                            $question_array['bank_account_no'] = null;
                            $question_array['bank_sort_code'] = null;
                            $question_array['has_parameters'] = true;
                            $question_array['bot_response_text'] = $text;
                            $question_array['password_checked'] = false;
                        }

                        $question_array['bot_response_text'] = $text ;

                        break;
                    case 'get bank account number':
                        //Check password_check_sbank_sort_codetatus before doing anything or else ask for password
                        // this is where extraction of bank account details of the user from paystack occurs
                        //initialization of paystack fund transfer can start here too

                        $password_check_status = $activity_log_decoded_bot_parameter['password_checked'];

                        if($password_check_status)
                        {
                            $bank_account_number = $response_text;
                            //Get $activity_log_decoded_bot_parameter and ensure that bank_name and  bank_sort_code are not empty/null
                            if(!empty($bank_account_number) and is_numeric($bank_account_number) and (strlen($bank_account_number) == 10) )//for Nigerian Banks
                            {

                                $text = "Please, tell me you bank account name";

                                $question_array['progress_tracking'] = "uncompleted";
                                $question_array['current_operation'] = "withdraw money";
                                $question_array['current_sub_operation'] = "get user bank account name";
                                $question_array['validation_status'] = true;
                                $question_array['bank_name'] = $activity_log_decoded_bot_parameter["bank_name"];;
                                $question_array['bank_account_name'] = null;
                                $question_array['bank_account_no'] = $response_text;
                                $question_array['bank_sort_code'] = $activity_log_decoded_bot_parameter["bank_sort_code"];
                                $question_array['password_checked'] = true;
                                $question_array['has_parameters'] = true;
                                $question_array['bot_response_text'] = $text;
                                $question_array['show_template'] = false;
                                $question_array['template_option_array'] = [];
                            }
                            else
                            {
                                $text = "Please ensure your bank account number is in 10 digits!";

                                $question_array['progress_tracking'] = "uncompleted";
                                $question_array['current_operation'] = "withdraw money";
                                $question_array['current_sub_operation'] = "get bank account number";
                                $question_array['validation_status'] = true;
                                $question_array['bank_name'] = $activity_log_decoded_bot_parameter["bank_name"];;
                                $question_array['bank_account_name'] = null;
                                $question_array['bank_account_no'] = null;
                                $question_array['bank_sort_code'] = $activity_log_decoded_bot_parameter["bank_sort_code"];
                                $question_array['has_parameters'] = true;
                                $question_array['password_checked'] = true;
                                $question_array['bot_response_text'] = $text;
                            }


                        }
                        else
                        {
                            $text = "You have not provided a correct password.\n\n Please, provide me with your password before proceeding to fund withdrawal.";
                            $question_array['progress_tracking'] = "uncompleted";
                            $question_array['current_operation'] = "withdraw money";
                            $question_array['current_sub_operation'] = "check user password";
                            $question_array['validation_status'] = true;
                            $question_array['bank_name'] = null;
                            $question_array['bank_account_no'] = null;
                            $question_array['bank_sort_code'] = null;
                            $question_array['has_parameters'] = true;
                            $question_array['bot_response_text'] = $text;
                            $question_array['password_checked'] = false;
                        }


                        $question_array['bot_response_text'] = $text ;

                        break;
                    case 'get bank account number_backup':
                        //Check password_check_status before doing anything or else ask for password
                        // this is where extraction of bank account details of the user from paystack occurs
                        //initialization of paystack fund transfer can start here too

                        $password_check_status = $activity_log_decoded_bot_parameter['password_checked'];

                        if($password_check_status)
                        {
                            $bank_account_number = $response_text;

                        }
                        else
                        {
                            $text = "You have not provided a correct password.\n\n Please, provide me with your password before proceeding to fund withdrawal.";
                            $question_array['progress_tracking'] = "uncompleted";
                            $question_array['current_operation'] = "withdraw money";
                            $question_array['current_sub_operation'] = "check user password";
                            $question_array['validation_status'] = true;
                            $question_array['bank_name'] = null;
                            $question_array['bank_account_no'] = null;
                            $question_array['bank_sort_code'] = null;
                            $question_array['has_parameters'] = true;
                            $question_array['bot_response_text'] = $text;
                            $question_array['password_checked'] = false;
                        }


                        //Get $activity_log_decoded_bot_parameter and ensure that bank_name and  bank_sort_code are not empty/null
                        if(!is_null($activity_log_decoded_bot_parameter["bank_name"]) and !is_null($activity_log_decoded_bot_parameter["bank_sort_code"]) )
                        {
                            //proceed to echo user details from paytack back to user and ask for amount to be withdrawn
                            //user bank details will be saved to database on next_sub_operation
                            //
                            $computed_bank_details = "";// get from api

                            $text = "Your Bank Name is " . $computed_bank_details. ". Please, tell me how much money you want to withdraw.";

                            $question_array['progress_tracking'] = "uncompleted";
                            $question_array['current_operation'] = "withdraw money";
                            $question_array['current_sub_operation'] = "paystack withdraw money";
                            $question_array['validation_status'] = true;
                            $question_array['bank_name'] = $activity_log_decoded_bot_parameter["bank_name"];
                            $question_array['bank_account_no'] = $response_text;
                            $question_array['bank_sort_code'] = $activity_log_decoded_bot_parameter["bank_sort_code"];
                            $question_array['has_parameters'] = true;
                            $question_array['bot_response_text'] = $text;
                            $question_array['show_template'] = false;
                            $question_array['template_option_array'] = [];
                        }
                        else
                        {
                            $text = "Sorry, you need to supply your bank account number.";

                            $question_array['progress_tracking'] = "uncompleted";
                            $question_array['current_operation'] = "withdraw money";
                            $question_array['current_sub_operation'] = "get bank account number";
                            $question_array['validation_status'] = true;
                            $question_array['bank_name'] = $activity_log_decoded_bot_parameter["bank_name"];
                            $question_array['bank_account_no'] = null;
                            $question_array['bank_sort_code'] = $activity_log_decoded_bot_parameter["bank_sort_code"];
                            $question_array['has_parameters'] = true;
                            $question_array['bot_response_text'] = $text;
                            $question_array['show_template'] = false;
                            $question_array['template_option_array'] = [];
                        }

                        $new_activity = new ActivityLog();
                        $new_activity->user_id = $last_activity->user_id;
                        $new_activity->time_initiated = Carbon::now()->timestamp; // Timestamp for now
                        $new_activity->time_received = null;
                        $new_activity->registration_channel_id =  $this->registration_channel_id;
                        $new_activity->current_bot_action_id = 13;
                        $new_activity->next_bot_action_id = null;
                        $new_activity->bot_action_parameter = json_encode($question_array);

                        $new_activity->created_at = Carbon::now()->toDateTimeString();

                        $new_activity->save();

                        $question_array['bot_response_text'] = $text ;

                        break;
                    case 'change name':


                        $question_array['progress_tracking'] = 'uncompleted';
                        $question_array['current_operation'] = 'user name';
                        $question_array['current_sub_operation'] = 'change my name';
                        $question_array['validation_status'] =  true;
                        $question_array['show_template'] = false;
                        $question_array['template_option_array'] = ["change name" , "demo", "fund account", "play game", "reward", "reset password", "check balance"  ];


                        $text = "Ok, type in your preferred name that you want.";

                        $question_array['bot_response_text'] = $text ;
                        break;
                    default:

                        $question_array['progress_tracking'] = 'completed';
                        $question_array['current_operation'] = 'user name';
                        $question_array['current_sub_operation'] = 'change name';
                        $question_array['validation_status'] =  true;
                        $question_array['show_template'] = true;
                        $question_array['template_option_array'] = ["demo", "fund account", "play game", "reward", "reset password", "check balance"  ];


                        $text =  $response_text . ", choose the option below to get started. \n\n Demo\n fund account \n play game\n reward\nreset password\ncheck balance";

                        $question_array['bot_response_text'] = $text ;

                }

                break;
            default:
                //
                $question_array['bot_response_text'] = "Default to first level";

        }

        return $question_array;
    }

    private function transferFundToUser($string, $get_user, $activity_log_decoded_bot_parameter) : array
    {
        $fund_transfer_function_response = ['success' => false];
        //Check that withdrawal amount is less or equal to than wallet balance.if its more than use the wallet balance anyways. So the system will not be cheated
        // Do api call to paystack and build array based on if paytsack return success or failure

        $bank_name = $activity_log_decoded_bot_parameter["bank_name"];
        $bank_account_name = $activity_log_decoded_bot_parameter["bank_account_name"];
        $withdrawal_amount = (float)$activity_log_decoded_bot_parameter["withdrawal_amount"];
        $bank_account_no = $activity_log_decoded_bot_parameter["bank_account_no"];
        $bank_sort_code = $activity_log_decoded_bot_parameter["bank_sort_code"];

        $user_wallet = new Wallet();
        $user_wallet->init($get_user->phone);

        $amount_withdrawn_from_paystack = 0.0;

        if(!empty($bank_account_name) and !empty($bank_account_no) and !empty($bank_sort_code) )
        {
            if($withdrawal_amount > $user_wallet->balance )
            {
                $fund_transfer_amount = $user_wallet->balance;
            }
            else
            {
                $fund_transfer_amount = $withdrawal_amount;
            }

            switch ($string)
            {
                case 'paystack':
                    //Call Paystack api for fund transfer
                    $data = [
                        'name' => $bank_account_name ,
                        'account_number' => $bank_account_no ,
                        'bank_code' => $bank_sort_code ,
                        'description' =>"User Payout Withdrawal using paystack"
                    ];

                    $withdrawal_reference = $this->create_random_number(15);
                    $FundTransfer = new FundTransfer($data, $withdrawal_reference); // Normal PHP method
                    //initiate
                    $initiate = $FundTransfer->authorize();


                    if($initiate['status'] == true)
                    {
                        //the authorization was correct
                        $TransferResult = $FundTransfer->send($fund_transfer_amount);
                        //amount of money to send to person
                        // A process check has to happen here too on $TransferResult
                        if( $TransferResult['status'] == true )
                        {
                            $fund_transfer_function_response = ['success' => true, 'fund_transfer_response'=> $TransferResult, 'response_text' => $TransferResult['message']];

                            $paystack_data = [
                                "phone" =>  $get_user->phone,
                                "amount" => $fund_transfer_amount ,
                                "info" => "User fund transfer",
                                'registration_channel_id' => $this->registration_channel_id,
                                'reference_code' => $withdrawal_reference,
                                "created_at" => Carbon::now()
                            ];
                            Withdrawal::create($paystack_data);

                        }
                        else
                        {
                            $fund_transfer_function_response = ['success' => false, 'fund_transfer_response'=> $TransferResult, 'response_text' => $TransferResult['message'] ];
                        }


                    }
                    else
                    {
                        //there is an error e.g. invalid bank account
                        $fund_transfer_function_response = ['success' => false, 'fund_transfer_response'=> $initiate, 'response_text' => "Fund transfer was not successful"];
                    }

                    break;
                case 'zenith':
                    //Call Zenith Bank API for Fund Transfer

                    if($bank_name == "Zenith Bank")
                    {
                        $PaymentMethod = "ZENITH/BENEFICIARY"; // This is used for zenith to zenith transaction
                    }
                    else
                    {
                        $PaymentMethod = "INTERSWITCH/BENEFICIARY"; // This is used to transfer to a third party in another bank. But this will give value immediately
                    }


                    $data["BeneficiaryAccount"] =  $bank_account_no ;
                    $data["BeneficiaryBankCode"] = $bank_sort_code ;
                    $data["BeneficiaryName"] = $bank_account_name;
                    $data["PaymentMethod"] = $PaymentMethod ;
                    $data["Amount"] = $fund_transfer_amount ;


                    $withdrawal_reference = $this->create_random_number(15);
                    $FundTransfer = new ZenithFundTransfer($data, $withdrawal_reference); // Normal PHP method
                    Log::info("Feed Data  ". json_encode($data));
                    //initiate
                    $sendRequestResponse  = $FundTransfer->callSendRequest($FundTransfer->data);

                    Log::info("Zenith Bank Soap Call - SendRequest ". json_encode($sendRequestResponse));

                    if(  $sendRequestResponse and  $sendRequestResponse->SendRequestResult->Description == "Uploaded")
                    {
                        // Request was uploaded successfully to Zenith Bank

                        //Call FetchRequest to get status of transactions
                        $fetchRequestResponse = $FundTransfer->callFetchRequest();
                        Log::info("Zenith Bank Soap Call - FetchRequest ". json_encode($fetchRequestResponse));
                        //amount of money to send to person
                        // A process check has to happen here too on $TransferResult
                        if($fetchRequestResponse and  $fetchRequestResponse->FetchRequestResult->Description == "Successful" )
                        {

                            $fund_transfer_function_response = ['success' => true, 'fund_transfer_response'=> $fetchRequestResponse, 'response_text' => "Fund Transfer Successful"];

                            $paystack_data = [
                                "phone" =>  $get_user->phone,
                                "amount" => $fund_transfer_amount ,
                                "info" => "User fund transfer-  Zenith Fund Transfer",
                                'registration_channel_id' => $this->registration_channel_id,
                                'reference_code' => $withdrawal_reference,
                                "created_at" => Carbon::now()
                            ];
                            Withdrawal::create($paystack_data);

                        }
                        else
                        {
                            $fund_transfer_function_response = ['success' => false, 'fund_transfer_response'=> $fetchRequestResponse, 'response_text' => "Fund Transfer was not successful." ];
                        }
                    }
                    else
                    {
                        //there is an error e.g. invalid bank account
                        $fund_transfer_function_response = ['success' => false, 'fund_transfer_response'=> $sendRequestResponse, 'response_text' => "Fund transfer was not successful"];
                    }

                    break;
            }

        }
        else
        {
            //Bank name, bank account and bank sort code cannot be empty
            //Send back , set
            //
            $fund_transfer_function_response = ['success' => false, 'fund_transfer_response'=> null, 'response_text' => "Bank name, bank account and bank sort code cannot be empty. Please reply with 'Withdraw money' to get started."];
        }
        return $fund_transfer_function_response;
    }

    private function initiateBotParameter($bot_parameter_id, $receiver_name, $response_text,  $user_id)
    {
        // Get Bot object with bot id
        // check bot parameter template from Bot Object and initiate according to switch values
        // return initialized bot parameters

        $bot_action_object = BotAction::find($bot_parameter_id);



        $returned_bot_parameter  = [];

        if(!is_null($bot_action_object ))
        {
            switch ($bot_parameter_id)
            {
                case 1:
                    //Build parameter template
                    //For this one, Its the same as saved on the database

//                    $returned_bot_parameter = $bot_action_object->bot_action_parameter_template;
                    $receiver_name_without_number =  preg_replace('/[0-9]+/', '', $receiver_name);

                    $text = "Hello, " . $receiver_name_without_number . " ☺️! \nWelcome to PreWin Games! 🌟. \n\n\n Prewin Games allows you to provide answers to a pool of questions over a period of time. User will be answering 10 questions in 5 minutes\n\n\n You can proceed to: \n\n Demo\n fund account \n play game\n reward\nreset password\ncheck balance ";

                    $returned_bot_parameter['has_parameters'] = false;
                    $returned_bot_parameter['bot_response_text'] = $text;

                    //Done

                    break;
                case 2:
                    //Build parameter template
                    //For this one, Its the same as saved on the database
                    $text = "Welcome to the Demo Play Sesson!. \nYou are about to play the demo for Prewin. \n\n ------------------- \n Just Reply 'Start'. ";
                    $returned_bot_parameter['has_parameters'] = false;
                    $returned_bot_parameter['bot_response_text'] = $text;
//                    $returned_bot_parameter = $bot_action_object->bot_action_parameter_template;
                    //Done
                    break;
                case 3:
                    //Build parameter template
                    //For this one, the parameter template needs to be computed/constructed

                    //Get one randomized Question and the answers and set it to the "Question" property and Option A, B, C and D as answers
//                    return Question::all();
                    $question_array = [];
                    $questions = Question::where([ 'is_pretest' => 0])->take(50)->with('answers');
                    $questions = $questions->get();
                    $random_index = random_int(0, ($questions->count() - 1));

                    $one_randomized_question = $questions->get($random_index);

                    $answer_option_template = ["a","b","c","d","e", "f", "g", "h" ]; //template for assigning answer

                    $question_array['question'] =  preg_replace("/&#?[a-z0-9]{2,8};/i","",strip_tags( $one_randomized_question->statement ));
                    $answer_count = 0;
                    foreach ($one_randomized_question->answers as $answer_key =>  $each_answers)
                    {
                        $question_array['option'.$answer_option_template[$answer_count++]] = $each_answers->statement;
                        if($each_answers->correct == "Yes") //
                        {
                            $question_array['correctOption'] = $answer_option_template[$answer_key];
                            // assign correct option letter to the variable
                        }
                    }

                    $question_array['score'] =  0;
                    $question_array['questions_count'] =  1 ;
                    $question_array['total_questions'] = 10;
                    $question_array['time_allowed'] = 5;
                    $question_array['time_left'] = 5;
                    $question_array['has_parameters'] = true;
                    $question_array['question_start_timestamp'] = Carbon::now()->timestamp;
                    $question_array['bot_response_text'] =

                        "Question No: " . $question_array['questions_count'] . "\n".
                        "Time Remaining in Minutes: ".  $question_array['time_left'] . " out of ".  $question_array['time_allowed'] ."\n".
                        "Your Score: ".  $question_array['score'] . " out of ".  $question_array['total_questions'] ."\n"."------------------------------\n\n". $question_array['question'] . "\n" .
                        "------------------------------\n\n".
                        "Answers ( Reply with  A, B, C or D ) : A. ".  $question_array['optiona'] . "   B. ".  $question_array['optionb']. "  C. ". $question_array['optionc'] . "  D. ". $question_array['optiond'] ."\n";


                    $returned_bot_parameter = $question_array;

                    //Done
                    break;
                case 4:
                    //Build parameter template
                    //For this one, Its the same as saved on the database
//                    $returned_bot_parameter = json_decode( $bot_action_object->bot_action_parameter_template );

                    $text = "You have finished the question game.";
                    $returned_bot_parameter['has_parameters'] = false;
                    $returned_bot_parameter['bot_response_text'] = $text;
                    //Done
                    break;
                case 5:

                    //Create Switch case that process how to start options for
                      //(a) Login (b) Register  (c) Restart


                    // Create JSon Parameter
                    //Tracking Registeration Progress
                    //1. Take email from user after asking
                    //json parameter for taking email --
                    // { 'progress_tracking': 'uncompleted',
                    //   'current_operation' : 'register',
                    //   'current_sub_operation' : 'ask for email',
                    //   'validation_status': true}
                    //process a. ask for email from user
                    //b validate email and send validation token 5 digits as Prewin Pin to email
                    // { 'progress_tracking': 'uncompleted',
                    //   'current_operation' : 'register',
                    //   'current_sub_operation' : 'provide pin number',
                    //   'validation_status': true or false}
                    //c. Advice that Prewin is the login code for logging, Prompt for
                    // { 'progress_tracking': 'uncompleted',
                    //   'current_operation' : 'register',
                    //   'current_sub_operation' : 'prompt for login',
                    //   'validation_status': true}
                    //2. Login Attempt should be done
                    // a. Ask for Prewin pin and validate against phone number and  Prewin pin
                    // { 'progress_tracking': 'uncompleted',
                    //   'current_operation' : 'login',
                    //   'current_sub_operation' : 'validate prewin token',
                    //   'validation_status': true or false
                    //}
                    // b Announce that user is logged in and present then with options and move to next bot_action number 6
                    //{ 'progress_tracking': 'completed',
                    //   'current_operation' : 'login',
                    //   'current_sub_operation' : 'successfully logged in ',
                    //   'validation_status': true
                    //}

                    switch (strtolower($response_text))
                    {
                        case "register":

                            $get_user = User::find( $user_id);
                            // Its sure that this user model will exist

                            //Check that user's password is not empty
                           if(!empty($get_user->password))
                           {
                               //if there is a password set already , then tell them to provide a password
                               $returned_bot_parameter['progress_tracking'] = false;
                               $returned_bot_parameter['current_operation'] = "login";
                               $returned_bot_parameter['current_sub_operation'] = "prompt for login";
                               $returned_bot_parameter['validation_status'] = true;

                               $receiver_name_without_number =  preg_replace('/[0-9]+/', '', $receiver_name);
                               $text = "Hey, " . $receiver_name_without_number . " , please can you reply with your password so that you can proceed.  \n\n Just Reply with your prewin password.";

                               $returned_bot_parameter['has_parameters'] = true;
                               $returned_bot_parameter['bot_response_text'] = $text;

                           }
                           else
                           {
                               $returned_bot_parameter = $this->promptForLogin($receiver_name, $returned_bot_parameter);

                           }
                            break;
                        case "login":
                            //
                            $get_user = User::find($user_id);
                            // Its sure that this user model will exist

                            //Check that user's password is not empty
                            if(!empty($get_user->password))
                            {
                                //if there is a password set already , then tell them to provide a password
                                $returned_bot_parameter['progress_tracking'] = false;
                                $returned_bot_parameter['current_operation'] = "login";
                                $returned_bot_parameter['current_sub_operation'] = "prompt for login";
                                $returned_bot_parameter['validation_status'] = true;

                                $receiver_name_without_number =  preg_replace('/[0-9]+/', '', $receiver_name);
                                $text = "Hey, " . $receiver_name_without_number . " , please can you reply with your password so that you can proceed.  \n\n Just Reply with your prewin password.";

                                $returned_bot_parameter['has_parameters'] = true;
                                $returned_bot_parameter['bot_response_text'] = $text;

                            }
                            else
                            {
                                //if there is no password, then , prompt that a password is set
                                $returned_bot_parameter = $this->promptForPassword($receiver_name, $returned_bot_parameter);
                            }
                            break;
                    }
                    break;
                case 6:
                    // Make Funding of Wallet possible
                    // Add Money to wallet, show the new wallet amount,
                    // Wallet information is to be shown immediately after authentication
                    // Subtract amount from wallet if game play starts
                    //Add Money to Wallet ==> Attempt will be made to add funds to user account, if transaction  fails, issue will be handled. Wallet table will be created. Payment tables will also be used. Wallet row changes anytime there is a wallet addition or subtraction {'progress_tracking' : 'uncompleted', 'amount_added' : 2000, amount_subtracted : 0,  'current_operation' : 'add money', 'current_sub_operation' : 'add money', 'validation_status': true/false, 'transaction_status' : true/false, transaction_reference : 'alphanum', wallet_amount: 45000  }

                    // Show New Wallet amount: Wallet amount will be show after successful authentication : no json
                    // Subtract wallet amount after the start of any game : once a game is started , wallet amount is reduced byy the amount used to play the game, this is to reflect in the code written for playing the serious game
//                    {'progress_tracking' : 'uncompleted', 'amount_added' : 0, amount_subtracted : 4000,  'current_operation' : 'add money', 'current_sub_operation' : 'add money', 'validation_status': true/false, 'transaction_status' : true/false, wallet_amount: 45000  }


//                            $user_wallet = Wallet::where([ 'user_id' => $user_id])->get();
//                            $amount_added = 2000;
//                            $amount_subtracted = 0;
//
//
//                            if(count($user_wallet) > 0)
//                            {
//                                //User has wallet
//                                $first_user_wallet = $user_wallet->first();
//
//                                //Do computation to get amount paid and add to the wallet
//                                $wallet_amount = (int)$first_user_wallet->amount;
//                                $new_wallet_amount = (int) $wallet_amount +  (int) $amount_added;
//
//                                $first_user_wallet->amount = $new_wallet_amount;
//                                $first_user_wallet->updated_at = Carbon::now();
//                                $first_user_wallet->save();
//                            }
//                            else
//                            {
//                                //if user wallet does not exist,  create it
//
//                                $new_user_wallet = new Wallet();
//                                $new_user_wallet->user_id = $user_id;
//                                $new_user_wallet->amount = 2000;
//                                $new_user_wallet->created_at = Carbon::now();
//                                $new_user_wallet->save();
//
//                                $new_wallet_amount =  (int) $amount_added;
//
//                            }
//
//                            $returned_bot_parameter['progress_tracking'] = "completed";
//                            $returned_bot_parameter['amount_added'] = $amount_added;
//                            $returned_bot_parameter['amount_subtracted'] = $amount_subtracted;
//                            $returned_bot_parameter['current_operation'] = "add money";
//                            $returned_bot_parameter['current_sub_operation'] = "add money";
//                            $returned_bot_parameter['validation_status'] = true;
//                            $returned_bot_parameter['wallet_amount'] =  $new_wallet_amount;
//                            $returned_bot_parameter['current_operation'] = "login";
//                            $returned_bot_parameter['current_sub_operation'] = "prompt for login";
//                            $returned_bot_parameter['validation_status'] = true;
//
//                            $receiver_name_without_number =  preg_replace('/[0-9]+/', '', $receiver_name);
//                            $text = "Hey, " . $receiver_name_without_number . " , you have successfully added " .  $amount_added . "  to your wallet. You can now proceed with playing with playing your prewin game. \n\n Just Reply with  'play game'.";
                    $returned_bot_parameter = $this->promptToFundWallet($receiver_name, $returned_bot_parameter);
                    break;
                    // id 7 not available yet
                case 8:

                     $question_array = [];
                     $questions = Question::where([ 'is_pretest' => 0])->take(50)->with('answers');
                     $questions = $questions->get();
//                     $one_randomized_question = $questions->random();

                    $random_index = random_int(0, ($questions->count() - 1));

                    $one_randomized_question = $questions->get($random_index);

                     $answer_option_template = ["a","b","c","d","e", "f", "g", "h" ]; //template for assigning answer

                     $question_array['question'] =  preg_replace("/&#?[a-z0-9]{2,8};/i","",strip_tags( $one_randomized_question->statement ));
                     $answer_count = 0;
                     foreach ($one_randomized_question->answers as $answer_key =>  $each_answers)
                     {
                         $each_answer_option = $answer_option_template[$answer_count++];
                         $question_array['answer_array'][$each_answer_option] = $each_answers->id;
                         $question_array['option'.$each_answer_option] = $each_answers->statement;
                         if($each_answers->correct == "Yes") //
                         {
                             $question_array['correctOption'] = $answer_option_template[$answer_key];
                             $question_array['correct_answer_array'][$each_answer_option] = $each_answers->id;
                             // assign correct option letter to the variable
                         }
                     }

                    $question_array['question_id'] =  $one_randomized_question->id;
                     $question_array['score'] =  0;
                    $question_array['first_half_score'] =  0;
                    $question_array['second_half_score'] =  0;
                     $question_array['questions_count'] =  1 ;
                     $question_array['total_questions'] = 10;
                     $question_array['time_allowed'] = "5:00";
                     $question_array['time_left'] = "5:00";
                     $question_array['has_parameters'] = true;
                     $question_array['question_start_timestamp'] = Carbon::now()->timestamp;
                     $question_array['bot_response_text'] =

                         "Question No: " . $question_array['questions_count'] . "\n".
                         "Time Remaining in Minutes: ".  $question_array['time_left'] . " out of ".  $question_array['time_allowed'] ."\n".
                         "Your Score: ".  $question_array['score'] . " out of ".  $question_array['total_questions'] ."\n"."------------------------------\n\n". $question_array['question'] . "\n" .
                         "------------------------------\n\n".
                         "Answers ( Reply with  A, B, C or D ) : A. ".  $question_array['optiona'] . "   B. ".  $question_array['optionb']. "  C. ". $question_array['optionc'] . "  D. ". $question_array['optiond'] ."\n";


                    $returned_bot_parameter['bot_response_text'] = $question_array['bot_response_text'];

                    $returned_bot_parameter = $question_array;
                    break;
                default:
                    //Build parameter template
                    //For this one, Its the same as saved on the database

//                    $returned_bot_parameter = $bot_action_object->bot_action_parameter_template;
                    $receiver_name_without_number =  preg_replace('/[0-9]+/', '', $receiver_name);

                    $text = "Hello, " . $receiver_name_without_number . " ☺️! \nWelcome to PreWin Games! 🌟. \n\n\n Prewin Games allows you to provide answers to a pool of questions over a period of time. User will be answering 10 questions in 5 minutes\n\n\n You can proceed to: \n\n Demo\n fund account \n play game\n reward\nreset password\ncheck balance ";

                    $returned_bot_parameter['has_parameters'] = false;
                    $returned_bot_parameter['bot_response_text'] = $text;
            }
        }

        return $returned_bot_parameter;
    }

    private function processUserResponseText($next_bot_action, $response_text, $receiver_name, $user_id)
    {

        //Word Processor method acts on the text received from the user
        //The work of the word processor is to take the next bot action and check that the user response text matches one of the expected responses for that next_bot_act as provided by the last activity log Model
        //if the text from the user is among, then normal processing as below will continue -
        // allow code to pass on process further

        //However, if the text is unexpected or does not match any of the expected_responses from the bot_action tables, word processor will issue a response and will not push to the activity log


         //Get Bot Action Model corresponding to the current_bot_id sent
        //Get the the json of all possible words that a bot_action can have
        //Build a response array based on the comparison as described above

        $bot_action_object = BotAction::find($next_bot_action);

        $returned_bot_parameter  = [];

        switch (strtolower($response_text))
        {
            case "play game old":
                //Show a pre-start instruction and prompt for the type of reward
                // Initiation of Game Play Session
                //Set all initialization Parameter and save them to the activity log
                // check that wallet is more than  100
                // if not, return  and complain that there no enough money in acoount

                //Check that user is authenticated by checking that user_id is in access_token and check that expired is null

                $user_access_token = AccessToken::where(["user_id" => $user_id ])->whereNull('expired')->get();
                if(count($user_access_token) > 0)
                {
                    $question_array['has_parameters'] = true;
                    $question_array['progress_tracking'] = "uncompleted";
                    $question_array['current_operation'] = "start game play";
                    $question_array['current_sub_operation'] = "send game play info";
                    $question_array['validation_status'] = true;

                    $receiver_name_without_number =  preg_replace('/[0-9]+/', '', $receiver_name);
                    $user_wallet_amount = 0;

                    //Get User Model
                    $this_user = User::find($user_id);
                    // Get Wallet information from Purchase, paystack and reward table
                    $user_wallet = new Wallet();

                    //Make Purchase from Wallet
                    $game_play_amount_option = [  "amount" => 100 /* Unit amount to be subtracted for game play */,
                                                  "phone" => $this_user->phone,
                                                  "info" => "Game Play",
                                                  'registration_channel_id' => $this->registration_channel_id
                                               ];
                    $game_play_amount =  $user_wallet->buy($game_play_amount_option);

                    //Check if response is true or false
                    //if true sent positive message
                    //if false ask that they return fund wallet
                    if($game_play_amount['response'])
                    {
                        $question_array['bot_response_text'] =

                            "Oga! " . $receiver_name_without_number . ", you are about to start Prewin games. Please review your payment information below.\n\n Your wallet balance is : " . $user_wallet->balance   . " naira. \n\n\n  Please choose from Games/Rewards options \n\n 'Mega Pay' ( This rewards you 100 x 10 as soon as you answer all 10 questions correctly )\n\n 'Other Pay' ( This rewards you 100 x 2.5 if you score 4/5 and another 100 x 2.5 when you score 8/10 in the entire questions )";

                    }
                    else
                    {
                        $question_array['bot_response_text'] =

                            $receiver_name_without_number . ".\n There was an error, you have: \n\n ".    $game_play_amount['message'] . " \n\n\nPlease reply with 'fund account' in other to fund your wallet.";

                    }



//                    $user_wallet = Wallet::where([ 'user_id' => $user_id])
//                        ->get();

//                    if(count($user_wallet) > 0)
//                    {
//                        //User Wallet Exists and We can go forward
//                        $first_user_wallet = $user_wallet->first();
//                        $user_wallet_amount = $first_user_wallet->amount;
//                    }
//                    else
//                    {
//                        //User Wallet Does not  Exists and We can create it first
//
//                        $new_user_wallet = new Wallet();
//                        $new_user_wallet->user_id = $user_id;
//                        $new_user_wallet->amount = 0;
//                        $new_user_wallet->created_at = Carbon::now();
//                        $new_user_wallet->save();
//
//                        $user_wallet_amount = $new_user_wallet->amount;
//
//                    }

                    $new_activity = new ActivityLog();
                    $new_activity->user_id = $user_id;
                    $new_activity->time_initiated = Carbon::now()->timestamp; // Timestamp for now
                    $new_activity->time_received = null;
                    $new_activity->registration_channel_id = $this->registration_channel_id;
                    $new_activity->current_bot_action_id = 7; //We know this is bot action number 7
                    $new_activity->next_bot_action_id = 8; //We know this is bot action number 8 since it is the nextb
                    $new_activity->bot_action_parameter = json_encode($question_array);

                    $new_activity->created_at = Carbon::now()->toDateTimeString();

                    $new_activity->save();


                    $returned_bot_parameter['can_proceed'] = false;


                    $returned_bot_parameter['text_processor_response_text'] = $question_array['bot_response_text'];

                }
                else
                {
                    //When you are not authenticated
                    //Redirect to the Login BotAction and initiate a login request

                    $returned_bot_parameter = $this->promptForLogin($receiver_name, $returned_bot_parameter);
                    $returned_bot_parameter['text_processor_response_text'] = $returned_bot_parameter['bot_response_text'];
                    $returned_bot_parameter['can_proceed'] = false;


                    $new_activity = new ActivityLog();
                    $new_activity->user_id = $user_id;
                    $new_activity->time_initiated = Carbon::now()->timestamp; // Timestamp for now
                    $new_activity->time_received = null;
                    $new_activity->registration_channel_id = $this->registration_channel_id;
                    $new_activity->current_bot_action_id = 5; //We know this is bot action number 7
                    $new_activity->next_bot_action_id = 6; //We know this is bot action number 8 since it is the nextb
                    $new_activity->bot_action_parameter = json_encode($returned_bot_parameter);

                    $new_activity->created_at = Carbon::now()->toDateTimeString();

                    $new_activity->save();

                }

                return $returned_bot_parameter;

                break;
            case "play game":

                // 1. Ask for password
                //2. On successful login, ask for the amount the player is willing to pay
                //3. Then prompt for the type of game play that user intends to play

                $question_array['has_parameters'] = true;
                $question_array['progress_tracking'] = "uncompleted";
                $question_array['current_operation'] = "get game play amount";
                $question_array['current_sub_operation'] = "get game play amount";
                $question_array['validation_status'] = true;

                $receiver_name_without_number =  preg_replace('/[0-9]+/', '', $receiver_name);

                $question_array['bot_response_text'] = "Oga! " . $receiver_name_without_number . ", how much money do you want to play with.";

                $new_activity = new ActivityLog();
                $new_activity->user_id = $user_id;
                $new_activity->time_initiated = Carbon::now()->timestamp; // Timestamp for now
                $new_activity->time_received = null;
                $new_activity->registration_channel_id = $this->registration_channel_id;
                $new_activity->current_bot_action_id = 7; //We know this is bot action number 7
                $new_activity->next_bot_action_id = 8; //We know this is bot action number 8 since it is the nextb
                $new_activity->bot_action_parameter = json_encode($question_array);

                $new_activity->created_at = Carbon::now()->toDateTimeString();

                $new_activity->save();

                $returned_bot_parameter['can_proceed'] = false;


                $returned_bot_parameter['text_processor_response_text'] = $question_array['bot_response_text'];

                return $returned_bot_parameter;

                break;
            case "fund account":

                $returned_bot_parameter = $this->promptToFundWallet($receiver_name, $returned_bot_parameter);


                $new_activity = new ActivityLog();
                $new_activity->user_id = $user_id;
                $new_activity->time_initiated = Carbon::now()->timestamp; // Timestamp for now
                $new_activity->time_received = null;
                $new_activity->registration_channel_id = $this->registration_channel_id;
                $new_activity->current_bot_action_id = 6; //We know this is bot action number 6
                $new_activity->next_bot_action_id = 7; //We know this is bot action number 8 since it is the nextb
                $new_activity->bot_action_parameter = json_encode($returned_bot_parameter);

                $new_activity->created_at = Carbon::now()->toDateTimeString();

                $new_activity->save();


                $returned_bot_parameter['can_proceed'] = false;


                $returned_bot_parameter['text_processor_response_text'] = $returned_bot_parameter['bot_response_text'];

                return $returned_bot_parameter;

                break;
            case 'reset password':

                // Attempt to get new password
                // Present authenticated options
                $receiver_name_without_number =  preg_replace('/[0-9]+/', '', $receiver_name);

                $text = "Hey, " . $receiver_name_without_number . " , please can you reply with your password so that you can update your password?.  \n\n Just Reply with your prefered prewin password.";

                $returned_bot_parameter['progress_tracking'] = 'uncompleted';
                $returned_bot_parameter['current_operation'] = 'login';
                $returned_bot_parameter['current_sub_operation'] = 'provide password';
                $returned_bot_parameter['validation_status'] =  true;
                $returned_bot_parameter['bot_response_text'] = $text ;
                $returned_bot_parameter['can_proceed'] = false;


                $returned_bot_parameter['text_processor_response_text'] = $returned_bot_parameter['bot_response_text'];

                $new_activity = new ActivityLog();
                $new_activity->user_id = $user_id;
                $new_activity->time_initiated = Carbon::now()->timestamp; // Timestamp for now
                $new_activity->time_received = null;
                $new_activity->registration_channel_id = $this->registration_channel_id;
                $new_activity->current_bot_action_id = 5; //We know this is bot action number 6
                $new_activity->next_bot_action_id = 6; //We know this is bot action number 8 since it is the nextb
                $new_activity->bot_action_parameter = json_encode($returned_bot_parameter);

                $new_activity->created_at = Carbon::now()->toDateTimeString();

                $new_activity->save();


                return $returned_bot_parameter;


                break;
            case 'check balance':

                //This keyword will show the monetary balance of the user account
                $user_wallet = new Wallet();
                $get_user = User::find($user_id);

                $user_wallet->init($get_user->phone);
                $receiver_name_without_number =  preg_replace('/[0-9]+/', '', $receiver_name);

                $text = "Hello, " . $receiver_name_without_number . " , your account balance is shown below. \n\n\n Account Balance: ".
                  $user_wallet->balance ." naira. \n\n Amount Rewarded: ". $user_wallet->reward ." naira. \n\n Amount Funded:  ". $user_wallet->topup. " naira. \n\n Game Purchase Amount: " . $user_wallet->purchase. " naira. \n\n\n You can proceed to: \n\n Demo\n fund account \n play game\n reward\nreset password\ncheck balance"   ;

                $returned_bot_parameter['progress_tracking'] = 'completed';
                $returned_bot_parameter['current_operation'] = 'check balance';
                $returned_bot_parameter['current_sub_operation'] = 'send balance statement';
                $returned_bot_parameter['validation_status'] =  true;
                $returned_bot_parameter['bot_response_text'] = $text ;
                $returned_bot_parameter['can_proceed'] = false;


                $returned_bot_parameter['text_processor_response_text'] = $returned_bot_parameter['bot_response_text'];

                $new_activity = new ActivityLog();
                $new_activity->user_id = $user_id;
                $new_activity->time_initiated = Carbon::now()->timestamp; // Timestamp for now
                $new_activity->time_received = null;
                $new_activity->registration_channel_id = $this->registration_channel_id;
                $new_activity->current_bot_action_id = 10; //We know this is bot action number 6
                $new_activity->next_bot_action_id = 11; //We know this is bot action number 8 since it is the nextb
                $new_activity->bot_action_parameter = json_encode($returned_bot_parameter);

                $new_activity->created_at = Carbon::now()->toDateTimeString();

                $new_activity->save();

                return $returned_bot_parameter;

                break;
            case 'reward':
//                $user_wallet = new Wallet();
//                $get_user = User::find($user_id);
//
//                $user_wallet->init($get_user->phone);
                $receiver_name_without_number =  preg_replace('/[0-9]+/', '', $receiver_name);

                $text = "Games/Rewards options \n\n 'Mega Pay' ( This rewards you 100 x 10 as soon as you answer all 10 questions correctly )\n\n 'Other Pay' ( This rewards you 100 x 2.5 if you score 4/5 and another 100 x 2.5 when you score 8/10 in the entire questions ) \n\n\n You can proceed to: \n\n Demo\n fund account \n play game\n reward\nreset password\ncheck balance"   ;

                $returned_bot_parameter['progress_tracking'] = 'completed';
                $returned_bot_parameter['current_operation'] = 'check balance';
                $returned_bot_parameter['current_sub_operation'] = 'send balance statement';
                $returned_bot_parameter['validation_status'] =  true;
                $returned_bot_parameter['bot_response_text'] = $text ;
                $returned_bot_parameter['can_proceed'] = false;


                $returned_bot_parameter['text_processor_response_text'] = $returned_bot_parameter['bot_response_text'];

                $new_activity = new ActivityLog();
                $new_activity->user_id = $user_id;
                $new_activity->time_initiated = Carbon::now()->timestamp; // Timestamp for now
                $new_activity->time_received = null;
                $new_activity->registration_channel_id = $this->registration_channel_id;
                $new_activity->current_bot_action_id = 11; //We know this is bot action number 6
                $new_activity->next_bot_action_id = 12; //We know this is bot action number 8 since it is the nextb
                $new_activity->bot_action_parameter = json_encode($returned_bot_parameter);

                $new_activity->created_at = Carbon::now()->toDateTimeString();

                $new_activity->save();

                return $returned_bot_parameter;

                break;
            case 'game history':

                //Game History Keyword
                // Get all game played. Get the amount played. Get the reward option chosen, Get the time play
                //Pull data from the activity_log , the bot_action_parameter field of the activity log
                //


//                $user_wallet = new Wallet();
//                $get_user = User::find($user_id);
//
//                $user_wallet->init($get_user->phone);
                $receiver_name_without_number =  preg_replace('/[0-9]+/', '', $receiver_name);

                $text = "Games/Rewards options \n\n 'Mega Pay' ( This rewards you 100 x 10 as soon as you answer all 10 questions correctly )\n\n 'Other Pay' ( This rewards you 100 x 2.5 if you score 4/5 and another 100 x 2.5 when you score 8/10 in the entire questions ) \n\n\n You can proceed to: \n\n Demo\n fund account \n play game\n reward\nreset password\ncheck balance"   ;

                $returned_bot_parameter['progress_tracking'] = 'completed';
                $returned_bot_parameter['current_operation'] = 'check balance';
                $returned_bot_parameter['current_sub_operation'] = 'send balance statement';
                $returned_bot_parameter['validation_status'] =  true;
                $returned_bot_parameter['bot_response_text'] = $text ;
                $returned_bot_parameter['can_proceed'] = false;


                $returned_bot_parameter['text_processor_response_text'] = $returned_bot_parameter['bot_response_text'];

                $new_activity = new ActivityLog();
                $new_activity->user_id = $user_id;
                $new_activity->time_initiated = Carbon::now()->timestamp; // Timestamp for now
                $new_activity->time_received = null;
                $new_activity->registration_channel_id = $this->registration_channel_id;
                $new_activity->current_bot_action_id = 11; //We know this is bot action number 6
                $new_activity->next_bot_action_id = 12; //We know this is bot action number 8 since it is the nextb
                $new_activity->bot_action_parameter = json_encode($returned_bot_parameter);

                $new_activity->created_at = Carbon::now()->toDateTimeString();

                $new_activity->save();

                return $returned_bot_parameter;

                break;
            case "withdraw money":


                //Verify Password of User

                //check database, user_banks table for available bank details
                $returned_bot_parameter['progress_tracking'] = "uncompleted";
                $returned_bot_parameter['current_operation'] = "withdraw money";
                $returned_bot_parameter['current_sub_operation'] = "check user password";
                $returned_bot_parameter['validation_status'] = true;
                $returned_bot_parameter['password_checked'] = false;
                $returned_bot_parameter['bank_name'] = null;
                $returned_bot_parameter['bank_account_name'] = null;
                $returned_bot_parameter['bank_account_no'] = null;
                $returned_bot_parameter['bank_sort_code'] = null;

                $text = "Please can you reply with your password so that you can proceed with fund withdrawal.  \n\n Just Reply with your password.";

                $returned_bot_parameter['has_parameters'] = true;
                $returned_bot_parameter['bot_response_text'] = $text;

                $returned_bot_parameter['can_proceed'] = false;



                $new_activity = new ActivityLog();
                $new_activity->user_id = $user_id;
                $new_activity->time_initiated = Carbon::now()->timestamp; // Timestamp for now
                $new_activity->time_received = null;
                $new_activity->registration_channel_id = $this->registration_channel_id;;
                $new_activity->current_bot_action_id = 13;
                $new_activity->next_bot_action_id = null;
                $new_activity->bot_action_parameter = json_encode($returned_bot_parameter);

                $new_activity->created_at = Carbon::now()->toDateTimeString();

                $new_activity->save();

                $returned_bot_parameter['text_processor_response_text'] = $returned_bot_parameter['bot_response_text'];

                return $returned_bot_parameter;

                break;
            case "internal trigger":

                // Test that  $this->internal_trigger_data is an array and that it count is greater than 0
                // if true, proceed to notify and save to activity log
                // if false don't return anything and exit silently

                // check that $this->internal_trigger_data is arra and as at least element

                if(is_array($this->internal_trigger_data) and count($this->internal_trigger_data) > 0 )
                {
                    $internal_trigger_data =  $this->internal_trigger_data;
                    $text = $internal_trigger_data["message"];

                }
                else
                {
                    $text = "Empty Message";

                }

                $returned_bot_parameter['progress_tracking'] = "completed";
                $returned_bot_parameter['current_operation'] = "internal trigger";
                $returned_bot_parameter['current_sub_operation'] = "system generated trigger";
                $returned_bot_parameter['validation_status'] = true;
                $returned_bot_parameter['has_parameters'] = true;
                $returned_bot_parameter['can_proceed'] = false;
                $returned_bot_parameter['internal_trigger_data'] = $this->internal_trigger_data;


                $returned_bot_parameter['bot_response_text'] = $text;


                $new_activity = new ActivityLog();
                $new_activity->user_id = $user_id;
                $new_activity->time_initiated = Carbon::now()->timestamp; // Timestamp for now
                $new_activity->time_received = null;
                $new_activity->registration_channel_id = $this->registration_channel_id;;
                $new_activity->current_bot_action_id = 14;
                $new_activity->next_bot_action_id = null;
                $new_activity->bot_action_parameter = json_encode($returned_bot_parameter);

                $new_activity->created_at = Carbon::now()->toDateTimeString();

                $new_activity->save();

                $returned_bot_parameter['text_processor_response_text'] = $returned_bot_parameter['bot_response_text'];

                return $returned_bot_parameter;

                break;
        }

        if(!is_null($bot_action_object ))
        {
            //There is going to be an "anything" tag, which means if the bot_action has an "anything" text among the array of possible response text, the wordprocessor will allow the process to continue.

            // Decode Json to array
            //if there is an anything tag, just allow the process to continue
            // Check that word is among words in array and revert

            $bot_possible_word_array = json_decode($bot_action_object->bot_possible_text_response, true);

//           return  $returned_bot_parameter['text_processor_response_text'] = $y;


//            return $bot_possible_word_array;


            if(in_array("anything", $bot_possible_word_array ))
            {
                $returned_bot_parameter['can_proceed'] = true;

                $text = "You can Proceed.";
                $returned_bot_parameter['text_processor_response_text'] = $text;

                return $returned_bot_parameter;
            }
            else // if there is no "anything" tag, then process the word processor comparison
            {
                if (in_array(strtolower($response_text), $bot_possible_word_array ))
                {
                    $returned_bot_parameter['can_proceed'] = true;

                    $text = "You can Proceed.";
                    $returned_bot_parameter['text_processor_response_text'] = $text;

                    return $returned_bot_parameter;
                }
                else
                {
                    $returned_bot_parameter['can_proceed'] = false;
                    $imploded_text =  implode (", ", $bot_possible_word_array);
                    $reply_noun = count($bot_possible_word_array) > 1 ? "replies are" : "reply is";

                    $receiver_name_without_number =  preg_replace('/[0-9]+/', '', $receiver_name);

                    $text = "⚠️ Sorry, ". $receiver_name_without_number . ",\n i did not get that.\n\n You replied with '" . $response_text .  "'. \n\n ✅ ✅ \nThe possible ". $reply_noun .": ". $imploded_text . "!" ;
                    $returned_bot_parameter['text_processor_response_text'] = $text;

                    return $returned_bot_parameter;
                }
            }
        }
        else
        {
            //If we can not find the current_bot_action, then allow process to proceed the next stage

            $returned_bot_parameter['can_proceed'] = true;

            $text = "Allow them to proceed to next the next process" ;

            $returned_bot_parameter['bot_response_text'] = $text;
        }

        return $returned_bot_parameter;

    }

    public function  secondsToTime($seconds)
    {
        $dtF = new \DateTime('@0');
        $dtT = new \DateTime("@$seconds");

        if($seconds < 60)
        {
            return $dtF->diff($dtT)->format('%s seconds');
        }
        elseif($seconds < 3600)
        {
            return $dtF->diff($dtT)->format('%i minutes and %s seconds');
        }
        else
        {
            return $dtF->diff($dtT)->format('%h hours, %i minutes and %s seconds');
        }

    }

    public function  secondsToTimeFormatted($seconds)
    {
        $dtF = new \DateTime('@0');
        $dtT = new \DateTime("@$seconds");

        if($seconds < 60)
        {
            return $dtF->diff($dtT)->format('00:%s');
        }
        elseif($seconds < 3600)
        {
            return $dtF->diff($dtT)->format('%i:%s');
        }
        else
        {
            return $dtF->diff($dtT)->format('%h:%i:%s');
        }

    }

    public function  timeToSeconds($timeString) // Format HH:MM:ss
    {
        //explode the string by ":" and compute all the seconds only, or else return zero

        $time_array = explode(":" , $timeString);
        if(count($time_array) > 0)
        {
            $array_count = count($time_array);
            Log:info("array count is " .  $array_count. "array is ". json_encode($time_array));

            switch ($array_count)
            {
                case 1:
                    return (int)$time_array[0]; // Not possible 23: or :23
                    break;
                case 2:
                    return (int) ( ((int)$time_array[0] * 60 ) +  ((int)$time_array[1] )) ; // 59:59 or 48:12
                    break;
                case 3:
                    return (int) ((int)$time_array[0] * 3600 ) +   ( (int) $time_array[1] * 60 )   +  ((int)$time_array[2])  ; // 00:03:45
                    break;
                default:
                    return 0;
            }


        }
        else
        {
            return 0;
        }

    }

    public  function  minutesToTime($minutes)
    {
        $seconds = $minutes * 60;
        $dtF = new \DateTime('@0');
        $dtT = new \DateTime("@$seconds");

        if($seconds < 3600)
        {
            return $dtF->diff($dtT)->format('%i minutes');
        }
        elseif($seconds < 86400)
        {
            return $dtF->diff($dtT)->format('%h hours');
        }
        else
        {
            return $dtF->diff($dtT)->format('%d days');
        }

    }

    public function create_random_name($lenght = 0)
    {
        $length = empty($lenght) ? 10 : $lenght;
        $characters = 'abcdefghijkmnpqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ23456789';
        $string = '';
        for ($p = 0; $p < $length; $p++) {
            $string.=$characters[mt_rand(0, strlen($characters) - 1)];
        }
        return $string;
    }

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

    public function sendWhatsappTextMessage(Request $request, $receiver="2347038257962", $text="" )
    {
        $BaseEndPoint = "https://www.waboxapp.com/api/send/chat";
        $CurrentEndpoint = "";
        $FullEndPoint =  $BaseEndPoint . $CurrentEndpoint;
        $PageResponse = Curl\Facades\Curl::to($FullEndPoint)
            ->withData([
                "token" => "c848db1935f79fbbaba7ae927d8fd6fb5bfd081c968ef",
                "uid" => "2349061198650", //whatsappObject['contact[uid]'],
                "to" => $receiver ,
                "custom_uid" =>sprintf('%04X%04X-%04X-%04X-%04X-%04X%04X%04X', mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(16384, 20479), mt_rand(32768, 49151), mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(0, 65535)) , // random string generator
                "text" =>  $text //"Are you Ready to start Playing PreWin Games?"
            ] )
            ->asJson()
            ->post();

        return  $PageResponse;
    }

    public function verifyUserPhoneNumber(Request $request )
    {
        //Check that phone number is not available or else send an error back

        $validator = Validator::make($request->all(), [
            'phone' => 'required',
        ]);

        if ($validator->fails())
        {
            $ValidatorErr =  $validator->getMessageBag()->toArray();
//            $First_Validator_Err  =  $ValidatorErr[0]; // $validator->getMessageBag()->toArray();
            $response_status  = 404;
            return response()->json( ["response" => false, "error_response" =>  $ValidatorErr  ],  $response_status );
        }


        $user_details = User::where([ 'phone' => $request->phone])->get();

                    if(count($user_details) > 0)
                    {
                        //User Wallet Exists and We can go forward
                        //This Phone Exists in the database

                        $phone_exists = true;
                        $response_status  = 200;
                        return response()->json( ["response" => $phone_exists,  "error_response" =>  null  ], $response_status  );
                    }
                    else
                    {
                        $phone_exists = false;
                        $response_status  = 404;
                        return response()->json( ["response" => $phone_exists, "error_response" =>  ["Phone Number not found "]   ], $response_status  );

                    }

    }

    public function registerUser(Request $request )
    {
        //Register New users to the database
//        2347038257962
        $FormMessages = array(
            'phone.digits' => 'Your phone number must be :digits digits.Also, please ensure that the phone number is in international format.'
        );
        $validator = Validator::make($request->all(), [
            'surname' => 'required|max:255',
            'firstname' => 'required|max:255',
            'phone' => 'required|digits:13|unique:users',
            'password' => 'required',
        ] , $FormMessages);

        if ($validator->fails())
        {
            $ValidatorErr =  $validator->getMessageBag()->toArray();
            $response_status  = 404;
            return response()->json( ["response" => false, "error_response" =>  $ValidatorErr  ],  $response_status );
        }

        $user_details = User::where([ 'phone' => $request->phone])->get();

        if(count($user_details) > 0)
        {
            //This Phone Exists in the database,send error message

            $registration_status = false; //we wont move forward with the registration so false will sent back.
            $response_status  = 404;
            return response()->json( ["response" => $registration_status, "message" => "The phone has already been taken.",   "error_response" =>  "Error in Registration"  ], $response_status  );
        }
        else
        {
            //Phone does not exist , so we can make an attempt to register user and theri password
            //register User to the database

            $new_user = new User();
            $new_user->phone = $request->phone;
            $new_user->surname = $request->surname;
            $new_user->othernames = $request->firstname;
            $new_user->registration_channel = 2; /* I know its from Web  */
            $new_user->password = app('hash')->make($request->password);
            $new_user->save();

            $registration_status = true;
            $response_status  = 200;
            return response()->json( ["response" => $registration_status, "message" =>  "Congratulations, Registration was successful", "error_response" =>  null   ], $response_status  );

        }

    }

    public static function post_http_request($uri, $hash = null)
    {
        $client = new Client();
        $results = $client->get(
            $uri,
            [
                'headers' => [
                    'User-Agent' => 'Laravel/5.0',
//                    'Connection' => 'close',
//                    'Hash' => $hash
                ]
            ]
        );
        return $results;
    }

    /**
     * @param $receiver_name
     * @param $returned_bot_parameter
     * @return mixed
     */
    private function promptForLogin($receiver_name, $returned_bot_parameter)
    {
//if there is no password, then , prompt that a password is set
        $returned_bot_parameter['progress_tracking'] = false;
        $returned_bot_parameter['current_operation'] = "login";
        $returned_bot_parameter['current_sub_operation'] = "prompt for login"; /* Entering email address has been skipped, we will go straight to ask for a one time password  **/
        $returned_bot_parameter['validation_status'] = true;

        $receiver_name_without_number = preg_replace('/[0-9]+/', '', $receiver_name);

//                    $text = "Hey, " . $receiver_name_without_number . " , please can i have your email address😁 ? \n\n ------------------------------ \n\n ' Just Reply with your valid email address.";

        $text = "Hey, " . $receiver_name_without_number . " , please can you reply with your password so that you can proceed.  \n\n Just Reply with your prewin password.";

//                    $text = "You can attempt to login or register";
        $returned_bot_parameter['has_parameters'] = true;
        $returned_bot_parameter['bot_response_text'] = $text;
        return $returned_bot_parameter;
    }

    private function promptForPassword($receiver_name, $returned_bot_parameter)
    {
//if there is no password, then , prompt that a password is set
        $returned_bot_parameter['progress_tracking'] = false;
        $returned_bot_parameter['current_operation'] = "login";
        $returned_bot_parameter['current_sub_operation'] = "provide password"; /* Entering email address has been skipped, we will go straight to ask for a one time password  **/
        $returned_bot_parameter['validation_status'] = true;

        $receiver_name_without_number =  preg_replace('/[0-9]+/', '', $receiver_name);

        $text = "Hey, " . $receiver_name_without_number . " , you have not set a password. Please, can you reply with your preferred prewin password?.  \n\n Just Reply with your preferred prewin password.";

//                    $text = "You can attempt to login or register";
        $returned_bot_parameter['has_parameters'] = true;
        $returned_bot_parameter['bot_response_text'] = $text;
        return $returned_bot_parameter;
    }

    /**
     * @param $receiver_name
     * @param $returned_bot_parameter
     * @return mixed
     */
    private function promptToFundWallet($receiver_name, $returned_bot_parameter)
    {
        $returned_bot_parameter['progress_tracking'] = "uncompleted";
        $returned_bot_parameter['current_operation'] = "fund wallet";
        $returned_bot_parameter['current_sub_operation'] = "choose payment channel";
        $returned_bot_parameter['validation_status'] = true;

        $receiver_name_without_number = preg_replace('/[0-9]+/', '', $receiver_name);
//        $text = "Hey, " . $receiver_name_without_number . " , please can you tell me how much  money to fund your wallet. \n\n Please Reply with the amount in naira.";

        $text = "Hey, " . $receiver_name_without_number . " , please choose your payment channel. We currently offer: \n\n Card Transaction ( Paystack,  reply with 'card' )\n\n USSD ( GTBank '*737*', reply with 'ussd'  ) \n\n  mCash (NIBSS mCash. reply with 'mcash')";

        $returned_bot_parameter['has_parameters'] = true;
        $returned_bot_parameter['bot_response_text'] = $text;
        $returned_bot_parameter['show_template'] = true;
        $returned_bot_parameter['template_option_array'] = ["Card", "USSD", "mcash"  ];

        return $returned_bot_parameter;
    }


}