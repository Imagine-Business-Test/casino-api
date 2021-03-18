<?php
namespace App\Http\Controllers\Api\V1;

use App\Libraries\Utilities;
use App\Models\ApplicationParameter;
use App\Models\DifficultyLevel;
use App\Models\Game;
use App\Models\GameDetail;
use App\Models\GameStatusTracker;
use App\Models\GameType;
use App\Models\Question;
use App\Models\QuestionCategory;
use App\Models\Reward;
use App\Models\User;
use App\Models\Wallet;
use Auth;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
class GameController extends BaseController
{

    private $userModel;

    public function __construct()
    {
        $this->middleware('auth:api');

        $this->userModel = collect ( Auth::guard('api')->user() );


    }

    public function game_demo()
    {
        //Extract User from Auth

        $userModel =     $this->userModel;

        if($userModel->isNotEmpty())
        {
            $user_id = $userModel->has("id") ? $userModel->get("id") : " ";

            // Get Demo questions for this  users
            // randomize the difficulty level and question category
            // Query for question with diff level id and question category id
            //where id is not in game.id where game.user_id is user->id

            $question_category = QuestionCategory::all()->pluck('id');


            $difficulty_level = DifficultyLevel::all()->pluck('id');


            $question_category_count = count($question_category);

            $difficulty_level_count = count($difficulty_level);


            // Do for loop that generates 10 questions
            $stop_number = 10; //Maximum number of Questions
            $randomized_question_list = []; // Array of all question to be sent for the demo game

            $questions_attempted_array =  [];

            $question_list = [];

            $winned_games = DB::table("game_details as gd")
                ->join('games as g', 'g.id', '=', 'gd.game_id')
                ->where([ "g.user_id" =>  $user_id ]  )
                ->groupBy(['gd.question_id' ])
                ->select(['gd.question_id'])
                ->get();

            if(count($winned_games) > 0)
            {
                $questions_attempted_array =  $winned_games->pluck("question_id");
            }
            else
            {
                $questions_attempted_array =  [];

            }

            for ($x = 0; $x < $stop_number; $x++)
            {
                $one_randomized_question_category = random_int(0, ($question_category_count - 1));

                $one_randomized_difficulty_level = random_int(0, ($difficulty_level_count - 1));
                // Query for question with diff level id and question category id
                //where id is not in game.id where game.user_id is user->id

//                return response()->json( ["response" => $questions_attempted_array   ]   ); // Petty response


                $questions = Question::where([ 'is_pretest' => 0])
                    ->whereNotIn("id" , $questions_attempted_array ) // Get Question
                    ->where(["difficulty_level" =>  $one_randomized_difficulty_level
                        , "question_category_id" => $one_randomized_question_category ])
                    ->orderByRaw(" RAND() ")
//                                       ->offset($random_offset)
                    ->take(50)
                    ->with('answers')
                    ->get();

                if($questions->count()  > 0 )
                {
                    $t[] = $x;
                    $random_index = random_int(0, ($questions->count() - 1));
                    $one_randomized_question = $questions->get($random_index);
                    $question_list[] =  $one_randomized_question;
                }
                else
                {
                    //Skip this loop
                    --$x;
                    $t[] = $x;
                    continue;
                }

//                return response()->json( ["response" => $questions_attempted_array ]   ); // Petty response

            }

            $demoQuestionList =  [
                "questions" =>   $question_list ,
            ];

            $response_message =
                [
                    'error' => null,
                    'errorText' => "",
                    'data' => [
                        $demoQuestionList
                    ],
                    'resultCode' => "10", //10 means positive success
                    'resultText' => "User Profile successful",
                    'resultStatus' => true

                ];

            return response()->json($response_message);

//            return response()->json( ["response" => $question_list  ]   ); // Petty response
        }
        else
        {
            //User not found
            // User Profile not found or collection is empty
            $userProfileResult =  [
                "user" =>   null ,
                "game_count" => (int) null,
                "win_count" => (int) null
            ];

            $response_message =
                [
                    'error' => null,
                    'errorText' => "",
                    'data' => [
                        $userProfileResult
                    ],
                    'resultCode' => "40", // 40 means user not found
                    'resultText' => "Error, user profile not found.",
                    'resultStatus' => false

                ];

            return response()->json($response_message);
        }

    }

    public function play_game(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'amount'       => 'required|integer',
            'reward_type'        => 'required|integer',
        ]);

        if ($validator->fails())
        {
            // Validator fails
            $validatorList = [];
            // Prepare Validation Error
            foreach (collect($validator->errors()) as $eachValidation)
            {

                $validatorList[] = $eachValidation[0]; // Get the first Error for this field
            }
//            return response()->json( ["response" => $validatorList   ]   ); // Petty respons

            $response_message =
                [
                    'error' =>  $validatorList ,
                    'errorText' => "Validation Error",
                    'data' => null,  // Data should return null whenever error occures
                    'resultCode' => "55", // 55 means error due to validation
                    'resultText' => "Failure due to validation error.",
                    'resultStatus' => false
                ];

            return response()->json($response_message);
        }

        //Extract User from Auth
        $userModel =     $this->userModel;

        if($userModel->isNotEmpty())
        {
            // Prerequisites for playing real game
            //1. Check that amount to be used for playing is smaller or equal to available Balance
            //2. Check that game type id is valid
            // 3. Make Purchase for Game

//            //Make Purchase from Wallet

            $amount = $request->amount;
            $reward_type = $request->reward_type;
            $user_id = $userModel->has("id") ? $userModel->get("id") : " ";
            $user_phone = $userModel->has("phone") ? $userModel->get("phone") : " ";

            $user_wallet = new Wallet();

            $user_wallet->init($user_phone);

            $balance = $user_wallet->balance;

//            return response()->json( ["response" => $balance   ]   ); // Petty respons

            //1. Check that amount to be used for playing is smaller or equal to available Balance
            if( $balance < $amount )
            {
                // Amount sent from mobile app is less that available balance of player
                // Return error

                $playGameError =  [
                                        "Available Amount is less than sent amount"
                                  ];

                $response_message =
                    [
                        'error' => $playGameError,
                        'errorText' => "You do not have sufficient amount to play this game",
                        'data' => null,
                        'resultCode' => "57", // 57 means insufficient fund in player account
                        'resultText' => "Insufficient fund to play game. Please, fund your account.",
                        'resultStatus' => false

                    ];

                return response()->json($response_message);
            }

            //2. Check that game type id is valid
            $gameType = GameType::find($reward_type);
            if(is_null($gameType))
            {
                $rewardTypeError =
                                [
                                    "The reward type chosen is not valid."
                                ];

                $response_message =
                    [
                        'error' => $rewardTypeError,
                        'errorText' => "Reward type is not valid",
                        'data' =>  null, // Data return null whenever there is error
                        'resultCode' => "58", // 58 means insufficient fund in player account
                        'resultText' => "InValid Reward Type. Please choose other pay or mega pay",
                        'resultStatus' => false
                    ];

                return response()->json($response_message);
            }

            // Perquisite for playing real game
                //1. Check that amount to be used for playing is smaller or equal to available Balance
                //2. Check that game type id is valid
                // 3. Make Purchase for Game

            //Make Purchase from Wallet
            $game_play_amount_option =
                                            [
                                              "amount" => $amount /* Unit amount to be subtracted for game play */,
                                              "phone" =>  $user_phone ,
                                              "info" => "Mobile Game Play",
                                              'registration_channel_id' => 4 /*  For Mobile Application    */
                                            ];

            $game_play_amount =  $user_wallet->buy($game_play_amount_option);
//
            //Check if response is true or false
            //if true sent positive message
            //if false ask that they return fund wallet
            if($game_play_amount['response'])
            {

                // If true send positive message
                // Start computing questions and send result back
                //

                // Get Real Questions questions for this  users to play game
                // randomize the difficulty level and question category
                // Query for question with diff level id and question category id
                //where id is not in game.id where game.user_id is user->id

                $question_category = QuestionCategory::all()->pluck('id');

                $difficulty_level = DifficultyLevel::all()->pluck('id');

                $question_category_count = count($question_category);

                $difficulty_level_count = count($difficulty_level);


                // Do for loop that generates 10 questions
                $stop_number = 10; //Maximum number of Questions
                $randomized_question_list = []; // Array of all question to be sent for the demo game

                $questions_attempted_array =  [];

                $question_list = [];

                $user_attempted_questions = DB::table("game_details as gd")
                                                ->join('games as g', 'g.id', '=', 'gd.game_id')
                                                ->where([ "g.user_id" =>  $user_id ]  )
                                                ->groupBy(['gd.question_id' ])
                                                ->select(['gd.question_id'])
                                                ->get();

                if(count($user_attempted_questions) > 0)
                {
                    $questions_attempted_array =  $user_attempted_questions->pluck("question_id");
                }
                else
                {
                    $questions_attempted_array =  [];

                }

                for ($x = 0; $x < $stop_number; $x++)
                {
                    $one_randomized_question_category = random_int(0, ($question_category_count - 1));

                    $one_randomized_difficulty_level = random_int(0, ($difficulty_level_count - 1));
                    // Query for question with diff level id and question category id
                    //where id is not in game.id where game.user_id is user->id

//                return response()->json( ["response" => $questions_attempted_array   ]   ); // Petty response


                    $questions = Question::where([ 'is_pretest' => 0])// ispretest will be used to differentiate demo questions from Real Game play  questions
                        ->whereNotIn("id" , $questions_attempted_array ) // Get Question
                        ->where(["difficulty_level" =>  $one_randomized_difficulty_level
                            , "question_category_id" => $one_randomized_question_category ])
                        ->orderByRaw(" RAND() ")
                        ->take(50)
                        ->with('answers')
                        ->get();

                    if($questions->count()  > 0 )
                    {
                        $t[] = $x;
                        $random_index = random_int(0, ($questions->count() - 1));
                        $one_randomized_question = $questions->get($random_index);
                        $question_list[] =  $one_randomized_question;
                    }
                    else
                    {
                        //Skip this loop
                        --$x;
                        $t[] = $x;
                        continue;
                    }

//                return response()->json( ["response" => $question_list ]   ); // Petty response

                }

                // Calculate Current Balance And send it
                $user_wallet->calc();

                // compute estimate-winning

                $estimated_winning = 0;
                if( $reward_type == 1) // Other pay  5
                {
                   $estimated_winning =  $amount * 5;
                }
                else if ($reward_type == 2) // Mega Pay Game * 10
                {
                    $estimated_winning =  $amount * 10;
                }


                $playGameQuestionList = [
                                                "questions" =>   $question_list ,
                                                "amount_played" =>   $amount,
                                                "reward_type" =>   $reward_type,
                                                "balance" =>   $user_wallet->balance,
                                                "user_id" =>   $user_id,
                                                "expected_winning" =>  $estimated_winning,
                                    ];

                $response_message =     [
                        'error' => null,
                        'errorText' => "",
                        'data' => $playGameQuestionList,
                        'resultCode' => "10", //10 means positive success
                        'resultText' => "Question Play Game Response successful ",
                        'resultStatus' => true
                    ];

                return response()->json( $response_message );

            }
            else
            {
                // Game play purchase was not successful. Return error message

                $playGameError =
                                        [
                                            " Game Purchase was unsuccessful. Please try again. "
                                        ];

                $response_message =
                                        [
                                            'error' =>  $playGameError,
                                            'errorText' =>"Game Purchase was unsuccessful. Please try again.",
                                            'data' =>  null,
                                            'resultCode' => "50", // 50 means error message
                                            'resultText' => "Error in ",
                                            'resultStatus' => false
                                        ];
                return response()->json($response_message);
            }
        }
        else
        {
            //User not found
            // User Profile not found or collection is empty
            $userProfileResult =  [
                                        "user" =>   null ,
                                        "game_count" => (int) null,
                                        "win_count" => (int) null
                                  ];

            $response_message =
                [
                    'error' => null,
                    'errorText' => "",
                    'data' => [
                                  $userProfileResult
                              ],
                    'resultCode' => "40", // 40 means user not found
                    'resultText' => "Error, user profile not found.",
                    'resultStatus' => false
                ];

            return response()->json($response_message);
        }

    }

    public function submit_played_game(Request $request)
    {
        // Log::info(json_encode($request->all()));

//        return response()->json( ["response" => $request->all()   ]   );



        //1.  The aim of this function is to save all gaming parameter associated with the initialized game sent from the mobile.
        //2.  The game object will be save first with all necessary parameters and a game_id will be released
        //3.  The game_id obtained will be used alongside other parameters to populate row for the game_details
        //4.  The games_status_tracker table is populated using the game_id generated above. The rows are populated accordingly


        $ValidationMessages  = [
                            "game.user_id.required" => "The user_id property in  the game object is required",
                            "game.user_id.integer" => "The user_id property in  the game object must be an integer",

                            "game.game_unique_identifier.required" => "The game_unique_identifier property in  the game object is required",

                            "game.game_type_id.required" => "The game_type_id property in  the game object is required",
                            "game.game_type_id.integer" => "The game_type_id  property in  the game object must be an integer",

                            "game.expected_winning.required" => "The expected_winning property in  the game object is required",
                            "game.expected_winning.numeric" => "The expected_winning property in  the game object must be numeric",

                            "game.amount_staked.required" => "The amount_staked property in  the game object is required",
                            "game.amount_staked.numeric" => "The amount_staked  property in  the game object must be numeric",

                            "game.game_state_changed.required" => "The user_id property in  the game object is required",
                            "game.game_state_changed.integer" => "The user_id property in  the game object must be an integer",

                            "game.created_at.required" => "The created_at property in  the game object is required",
                            "game.created_at.date_format" => "The created_at property in  the game object must be a date time string with this format => Y-m-d H:i:s ",

                            "game_details.*.game_unique_identifier.required" => "The amount_staked property in  the game object is required",

                            'game_details.*.question_id.required'       => "The question_id property in the array of game_details object is required",
                            "game_details.*.question_id.integer" => "The question_id property in the array of game_details must be an integer",

                            'game_details.*.chosen_answer_id.required'       => "The chosen_answer_id property in the array of game_details object is required",
                            "game_details.*.chosen_answer_id.integer" => "The chosen_answer_id property in the array of game_details must be an integer",

                            'game_details.*.game_start_time.required'       => "The game_start_time property in the array of game_details object is required",
                            "game_details.*.game_start_time.date_format" => "The game_start_time property in the array of game_details must be a date time string with this format => Y-m-d H:i:s (for example: 2019-07-19 11:51:03)",

                            'game_details.*.current_game_score.required'       => "The current_game_score property in the array of game_details object is required",
                            "game_details.*.current_game_score.integer" => "The current_game_score property in the array of game_details must be must be an integer",

                            'game_details.*.current_game_status_id.required'       => "The current_game_status_id property in the array of game_details object is required",
                            "game_details.*.current_game_status_id.integer" => "The current_game_status_id property in the array of game_details must be an integer",

                            'game_details.*.question_number.required'       => "The question_number property in the array of game_details object is required",
                            "game_details.*.question_number.integer" => "The question_number property in the array of game_details must be an integer",

                            'game_details.*.created_at.required'       => "The created_at property in the array of game_details object is required",
                            "game_details.*.created_at.date_format" => "The created_at property in the array of game_details must be a date time string with this format => Y-m-d H:i:s (for example: 2019-07-19 11:51:03) ",

                            'games_status_tracker.*.game_status_id.required'       => "The game_status_id property in the array of games_status_tracker object is required",
                            "games_status_tracker.*.game_status_id.integer" => "The game_status_id property in the array of games_status_tracker must be an integer",

                            'games_status_tracker.*.previous_game_status_id.required'       => "The previous_game_status_id property in the array of games_status_tracker object is required",
                            "games_status_tracker.*.previous_game_status_id.integer" => "The previous_game_status_id property in the array of games_status_tracker must be an integer",

                            'games_status_tracker.*.expected_winning.required'       => "The expected_winning property in the array of games_status_tracker object is required",
                            "games_status_tracker.*.expected_winning.numeric" => "The expected_winning property in the array of games_status_tracker must be numeric",

                            'games_status_tracker.*.actual_winning.required'       => "The actual_winning property in the array of games_status_tracker object is required",
                            "games_status_tracker.*.actual_winning.numeric" => "The actual_winning property in the array of games_status_tracker must be numeric",

                            'games_status_tracker.*.score.required'       => "The score property in the array of games_status_tracker object is required",

                            "games_status_tracker.*.score.integer" => "The score property in the array of games_status_tracker must be an integer",

                            'games_status_tracker.*.wallet_balance.required'       => "The wallet_balance property in the array of games_status_tracker object is required",
                            "games_status_tracker.*.wallet_balance.numeric" => "The wallet_balance property in the array of games_status_tracker must be numeric",

                            'games_status_tracker.*.total_actual_winning.required'       => "The total_actual_winning property in the array of games_status_tracker object is required",
                            "games_status_tracker.*.total_actual_winning.numeric" => "The total_actual_winning property in the array of games_status_tracker must be numeric",

                            'games_status_tracker.*.start_time.required'       => "The start_time property in the array of games_status_tracker object is required",
                            "games_status_tracker.*.start_time.date_format" => "The start_time property in the array of games_status_tracker must be a date time string with this format => Y-m-d H:i:s (for example: 2019-07-19 11:51:03) ",

                            'games_status_tracker.*.end_time.required'       => "The end_time property in the array of games_status_tracker object is required",
                            "games_status_tracker.*.end_time.date_format" => "The end_time property in the array of games_status_tracker must be a date time string with this format => Y-m-d H:i:s (for example: 2019-07-19 11:51:03) ",

                            'games_status_tracker.*.amount_staked.required'       => "The expected_winning property in the array of games_status_tracker object is required",
                            "games_status_tracker.*.amount_staked.numeric" => "The expected_winning property in the array of games_status_tracker must be an integer",

                            'games_status_tracker.*.created_at.required'       => "The created_at property in the array of games_status_tracker object is required",
                            "games_status_tracker.*.created_at.date_format" => "The created_at property in the array of games_status_tracker must be a date time string with this format => Y-m-d H:i:s (for example: 2019-07-19 11:51:03) ",
        ];

        $validator = Validator::make($request->all(), [
                                                        'game_details.*.game_unique_identifier'       => 'required',
                                                        'game_details.*.question_id'       => 'required|integer',
                                                        'game_details.*.chosen_answer_id'       => 'required|integer',
                                                        'game_details.*.game_start_time'       => 'required|date_format:Y-m-d H:i:s',
                                                        'game_details.*.current_game_score'       => 'required|integer',
                                                        'game_details.*.current_game_status_id'       => 'required|integer',
                                                        'game_details.*.question_number'       => 'required|integer',
                                                        'game_details.*.created_at'       => 'required|date_format:Y-m-d H:i:s',

                                                        'game.user_id'       => 'required|integer',
                                                        'game.game_unique_identifier'       => 'required',
                                                        'game.game_type_id'       => 'required|integer',
                                                        'game.expected_winning'       => 'required|numeric',
                                                        'game.amount_staked'       => 'required|numeric',
                                                        'game.game_state_changed'       => 'required|integer',
                                                        'game.created_at'       => 'required|date_format:Y-m-d H:i:s',

                                                        'games_status_tracker.*.game_status_id'       => 'required|integer',
                                                        'games_status_tracker.*.previous_game_status_id'       => 'required|integer',
                                                        'games_status_tracker.*.expected_winning'       => 'required|numeric',
                                                        'games_status_tracker.*.actual_winning'       => 'required|numeric',
                                                        'games_status_tracker.*.score'       => 'required|integer',
                                                        'games_status_tracker.*.wallet_balance'       => 'required|numeric',
                                                        'games_status_tracker.*.total_actual_winning'       => 'required|numeric',
                                                        'games_status_tracker.*.start_time'       => 'required|date_format:Y-m-d H:i:s',
                                                        'games_status_tracker.*.end_time'       => 'nullable|date_format:Y-m-d H:i:s',
                                                        'games_status_tracker.*.amount_staked'       => 'required|numeric',
                                                        'games_status_tracker.*.created_at'       => 'required|date_format:Y-m-d H:i:s',

        ], $ValidationMessages);


        if (false) //$validator->fails())
        {
            // Validator fails
            $validatorList = [];
            // Prepare Validation Error
            foreach (collect($validator->errors()) as $eachValidation)
            {
                $validatorList[] = $eachValidation[0]; // Get the first Error for this field
            }
//            return response()->json( ["response" => $validatorList   ]   ); // Petty respons

            $response_message =
                [
                    'error' =>  $validatorList ,
                    'errorText' => "Validation Error",
                    'data' => null,  // Data should return null whenever error occures
                    'resultCode' => "55", // 55 means error due to validation
                    'resultText' => "Failure due to validation error.",
                    'resultStatus' => false
                ];

            return response()->json($response_message);
        }

//        return response()->json( ["response" =>  $request->all()   ]   ); //
        //Extract User from Auth
        $userModel =     $this->userModel;

        if($userModel->isNotEmpty())
        {
            $user_id = $userModel->has("id") ? $userModel->get("id") : " ";
            $user_phone = $userModel->has("phone") ? $userModel->get("phone") : " ";

            $game = $request->game;
            $game_details = $request->game_details;
            $games_status_tracker = $request->games_status_tracker;
            $game_unique_identifier = $game['game_unique_identifier'];

            try
            {
                // 1. Get game object and save to database retrieving the game_id values to be used to save game_details and games_status_tracker
                $game_data = [
                    'registration_channel_id' => 4, /* For Mobile Application  */
                    'user_id' => $user_id,
                    'game_unique_identifier' => $game_unique_identifier,
                    'game_type_id' => $game['game_type_id'],
                    'expected_winning' => $game['expected_winning'],
                    'amount_staked' => $game['amount_staked'],
                    'game_state_changed' => 0,
                    'created_at' => $game['created_at'],
                ];

                $game_id = DB::table('games')->insertGetId($game_data);

                // 2. Get games_status_tracker object, run a loop and save to database
                if (count($games_status_tracker) > 1)
                {
                    foreach ($games_status_tracker as $each_games_status_tracker)
                    {
                        $game_status_tracker_data = [
                            'game_id' => $game_id, //Game Id , generated from above
                            'game_status_id' => $each_games_status_tracker['game_status_id'],
                            'expected_winning' => $each_games_status_tracker['expected_winning'],
                            'actual_winning' => $each_games_status_tracker['actual_winning'],
                            'score' => $each_games_status_tracker['score'],
                            'wallet_balance' => $each_games_status_tracker['wallet_balance'],
                            'total_actual_winning' => $each_games_status_tracker['total_actual_winning'],
                            'start_time' => $each_games_status_tracker['start_time'],
                            'end_time' => $each_games_status_tracker['end_time'],
                            'amount_staked' => $each_games_status_tracker['amount_staked'],
                            'previous_game_status_id' => $each_games_status_tracker['previous_game_status_id'],
                            'created_at' => $each_games_status_tracker['created_at'],
//                            'updated_at' => $each_games_status_tracker['updated_at'],
                        ];

                        GameStatusTracker::create($game_status_tracker_data);

                        if ($each_games_status_tracker['game_status_id'] == 4) /* A winning occurred and needs to be recorded in the reward */
                            {
                            $reward_data =
                                [
                                'phone' => $user_phone,
                                'amount' => $each_games_status_tracker['actual_winning'],
                                'info' => "Game Play Reward",
                                'registration_channel_id' => 4  /* Mobile application channel  */
                            ];
                            //Create a Reward Model and Store reward
                            Reward::create($reward_data);
                        }

                    }
                }

                // 3. Get game_details object, run a loop and save to database
                if (count($game_details) > 1)
                {
                    foreach ($game_details as $each_game_details)
                    {
                        $game_details_array['current_game_score'] = $each_game_details['current_game_score'];
                        $game_details_array['current_game_status_id'] = $each_game_details['current_game_status_id'];
                        $game_details_array['game_id'] = $game_id;
                        $game_details_array['game_unique_identifier'] = $each_game_details['game_unique_identifier'];
                        $game_details_array['question_id'] = $each_game_details['question_id'];
                        $game_details_array['question_number'] = $each_game_details['question_number'];
                        $game_details_array['game_start_time'] = $each_game_details['game_start_time'];
                        $game_details_array['correct_answer_id'] = $each_game_details['correct_answer_id'];
                        $game_details_array['chosen_answer_id'] = $each_game_details['chosen_answer_id'];
                        $game_details_array['created_at'] = $each_game_details['created_at'];
//                        $game_details_array['updated_at'] =  $each_game_details['updated_at'];

                        GameDetail::create($game_details_array);
                    }
                }

                // Response to be return
                // user_id, try catch response, success, return message to
                $text = "Your game played details is successfully saved. ";

                $GamePlayedDetailsResponse =
                                                    [
                                                        "user_id" =>   $user_id,
                                                        "user_phone" =>  $user_phone,
                                                        "success" =>  $text,
                                                        "game_id" =>  $game_id
                                                    ];

                $response_message =     [
                                            'error' => null,
                                            'errorText' => "",
                                            'data' => $GamePlayedDetailsResponse,
                                            'resultCode' => "10", //10 means positive success
                                            'resultText' => $text,
                                            'resultStatus' => true
                                        ];

                return response()->json( $response_message );
            }
            catch (\Exception $e)
            {
                Log::info(json_encode($e->getMessage()));
                Log::info(" Game Error Details ( All Request details ) =====> " );

                Log::info(json_encode($request->all()) );

                $message = "There was an error processing this game play submission. You might be trying to add a game that had already being added. " ;
                $response_message = [
                                        'error' => [$e->getMessage()] ,
                                        'errorText' => $message,
                                        'data' => null,
                                        'resultCode' => "57", //57 means error from internal error through exception handling
                                        'resultText' => "",
                                        'resultStatus' => false
                                    ];

                return response()->json( $response_message );
            }
        }
        else
        {
            //User not found
            // User Profile not found or collection is empty
            $userProfileResult =  [
                "user" =>   null ,
                "game_count" => (int) null,
                "win_count" => (int) null
            ];

            $response_message =
                [
                    'error' => null,
                    'errorText' => "",
                    'data' => [
                        $userProfileResult
                    ],
                    'resultCode' => "40", // 40 means user not found
                    'resultText' => "Error, user profile not found.",
                    'resultStatus' => false
                ];

            return response()->json($response_message);
        }

    }

    public function init_quick_games(Request $request)
    {
//        return response()->json( ["response" => $request->all()   ]   ); //

        $validator = Validator::make($request->all(),
                                        [
                                                'amount'       => 'required|integer',
                                                'reward_type'   => 'required|integer',
                                                "platform" =>  "required"
                                        ]);

        if ($validator->fails())
        {
            // Validator fails
            $validatorList = [];
            // Prepare Validation Error
            foreach (collect($validator->errors()) as $eachValidation)
            {

                $validatorList[] = $eachValidation[0]; // Get the first Error for this field
            }
//            return response()->json( ["response" => $validatorList   ]   ); // Petty respons

            $response_message =
                [
                    'error' =>  $validatorList ,
                    'errorText' => "Validation Error",
                    'data' => null,  // Data should return null whenever error occures
                    'resultCode' => "55", // 55 means error due to validation
                    'resultText' => "Failure due to validation error.",
                    'resultStatus' => false
                ];

            return response()->json($response_message);
        }

        //Extract User from Auth
        $userModel = $this->userModel;

        if($userModel->isNotEmpty())
        {
            // Prerequisites for playing real game

            //1. Check that amount to be used for playing is smaller or equal to available Balance
            // and not more than a particular amount
            //2. Check that game type id is valid
            // 3. Make Purchase for Game


            $amount = $request->amount;
            $reward_type = $request->reward_type;
            $user_id = $userModel->has("id") ? $userModel->get("id") : " ";
            $user_phone = $userModel->has("phone") ? $userModel->get("phone") : " ";

            $user_wallet = new Wallet();

            $user_wallet->init($user_phone);

            $balance = $user_wallet->balance;

            //1. Check that amount to be used for playing is smaller or equal to available Balance
            if( $balance < $amount )
            {
                // Amount sent from mobile app is less that available balance of player
                // Return error

                $playGameError =
                    [
                         "Available Amount is less than sent amount"
                    ];

                $response_message =
                    [
                        'error' => $playGameError,
                        'errorText' => "You do not have sufficient amount to play this game",
                        'data' => null,
                        'resultCode' => "57", // 57 means insufficient fund in player account
                        'resultText' => "Insufficient fund to play game. Please, fund your account.",
                        'resultStatus' => false

                    ];

                return response()->json($response_message);
            }

            //2. Check that game type id is valid

            $gameType = GameType::find($reward_type);
            if(is_null($gameType))
            {
                $rewardTypeError =
                    [
                        "The reward type chosen is not valid."
                    ];

                $response_message =
                    [
                        'error' => $rewardTypeError,
                        'errorText' => "Reward type is not valid",
                        'data' =>  null, // Data return null whenever there is error
                        'resultCode' => "58", // 58 means insufficient fund in player account
                        'resultText' => "InValid Reward Type. Please choose other pay or mega pay",
                        'resultStatus' => false
                    ];

                return response()->json($response_message);
            }


            //Make Purchase from Wallet

            // Compute Game Platform through the registration_channel

            $PlatformTransformerArray =
                [
                    "web" => "2",
                    "mobile" => "4"
                ];

            $RewardTypeTransformerArray =
                                            [
                                                1  => "other_pay",
                                                2  => "mega_pay"
                                            ];


            $platform =  $request->platform;
            $reward_type_slug =  $RewardTypeTransformerArray[$reward_type];

            Log:info("PlatformTransformerArray" . $PlatformTransformerArray[$platform] . "RewardTypeTransformerArray"
              . $reward_type_slug  );

            $game_play_amount_option =
                [
                    "amount" => $amount /* Unit amount to be subtracted for game play */,
                    "phone" =>  $user_phone ,
                    "info" => "Mobile Game Play",
                    'registration_channel_id' => $PlatformTransformerArray[$platform] /*  For Mobile Application    */
                ];

            $game_play_amount =  $user_wallet->buy($game_play_amount_option);
            $one_randomized_difficulty_level = 1;
//
            //Check if response is true or false
            //if true sent positive message
            //if false ask that they return and  fund wallet
            if($game_play_amount['response'])
            {
                // If true send positive message
                // Start computing questions and send result back

                // Get Real Questions questions for this  users to play game
                // randomize the difficulty level and question category
                // Query for question with diff level id and question category id
                //where id is not in game.id where game.user_id is user->id

                $question_category = QuestionCategory::all()->pluck('id');

                $difficulty_level = DifficultyLevel::all()->pluck('id');

                $question_category_count = count($question_category);

                $difficulty_level_count = count($difficulty_level);

                $one_randomized_question = [];



                // Do for loop that generates 1 questions
                $stop_number = 1; //Maximum number of Questions
                $randomized_question_list = []; // Array of all question to be sent for the demo game

                $questions_attempted_array =  [];

                $question_list = [];

                $user_attempted_questions = DB::table("game_details as gd")
                                                        ->join('games as g', 'g.id', '=', 'gd.game_id')
                                                        ->where([ "g.user_id" =>  $user_id ]  )
                                                        ->groupBy(['gd.question_id' ])
                                                        ->select(['gd.question_id'])
                                                        ->get();

                if(count($user_attempted_questions) > 0)
                {
                    $questions_attempted_array =  $user_attempted_questions->pluck("question_id");
                }
                else
                {
                    $questions_attempted_array =  [];
                }

                for ($x = 0; $x < $stop_number; $x++)
                {
                    $one_randomized_question_category = random_int(0, ($question_category_count - 1));

                    $one_randomized_difficulty_level = random_int(0, ($difficulty_level_count - 1));
                    // Query for question with diff level id and question category id
                    //where id is not in game.id where game.user_id is user->id

//                return response()->json( ["response" => $questions_attempted_array   ]   ); // Petty response

                    $questions = Question::where([ 'is_pretest' => 0])
                                                    ->whereNotIn("id" , $questions_attempted_array ) // Get Question
                                                    ->where(["difficulty_level" =>  $one_randomized_difficulty_level
                                                        , "question_category_id" => $one_randomized_question_category ])
                                                        ->orderByRaw(" RAND() ")
                                                        ->take(50)
                                                        ->with('answers')
                                                        ->get();



                    if($questions->count()  > 0 )
                    {
                        $random_index = random_int(0, ($questions->count() - 1));
                        $one_randomized_question = $questions->get($random_index);
                    }
                    else
                    {
                        //Skip this loop
                        --$x;
                        continue;
                    }

                }
                // Calculate Current Balance And send it
                $user_wallet->calc();

                // compute estimate-winning using application parameter
                $application_game_parameters = ApplicationParameter::where("parameter_slug", "game_play_parameters" )->get();
                Log::info("Application Parameters ======> " . json_encode($application_game_parameters ));

                $quick_play_parameters_array = [];
                if( count($application_game_parameters) > 0 )
                {
                    $application_game_parameter = $application_game_parameters->first();
                    $application_game_parameter_json = $application_game_parameter->application_parameter_json;
                    $application_game_parameter_array = json_decode($application_game_parameter_json, true);

                    $quick_play_parameters_array  = array_key_exists("general", $application_game_parameter_array ) ?                     $application_game_parameter_array["general"] : null ;

                }
                else
                {
                    $quick_play_parameters_array = null;
                }


                $estimated_winning =  $amount * (int)$quick_play_parameters_array[$reward_type_slug]["reward_stakes"];

                $game_unique_identifier =  sprintf('%04X%04X-%04X-%04X-%04X-%04X%04X%04X', mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(16384, 20479), mt_rand(32768, 49151), mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(0, 65535));

                $game_data = [
                    'registration_channel_id' =>  $PlatformTransformerArray[$platform] ,
                    'user_id' => $user_id,
                    'game_unique_identifier' =>  $game_unique_identifier ,
                    'game_type_id' =>  $reward_type,
                    'expected_winning' => $estimated_winning  ,
                    'amount_staked' => $amount,
                    'game_state_changed' => 0,
                ];

                $game_id =  DB::table('games')->insertGetId($game_data);

                $start_time = Carbon::now()->toDateTimeString();
                $previous_game_status_id = 3;
                $game_status_tracker_data = [
                                                'game_id' => $game_id,
                                                'game_status_id' => 3,
                                                'expected_winning' =>  $estimated_winning,
                                                'actual_winning' => 0,
                                                'score' => 0,
                                                'wallet_balance' => $user_wallet->balance,
                                                'total_actual_winning' =>  0,
                                                'start_time' => $start_time,
                                                'end_time' => null,
                                                'amount_staked' => $amount,
                                                'previous_game_status_id' => $previous_game_status_id,  /* the game_status_id now  will be 0 at start of the game  */
                ];

                GameStatusTracker::create($game_status_tracker_data);



                $playGameQuestionList =
                    [
                        "questions" =>   $question_list ,
                        "question" =>   $one_randomized_question ,
                        "amount_played" =>   $amount,
                        "reward_type" =>   $reward_type,
                        "balance" =>   $user_wallet->balance,
                        "user_id" =>   $user_id,
                        "expected_winning" =>  $estimated_winning,
                        "reward_stakes" =>  $quick_play_parameters_array[$reward_type_slug]["reward_stakes"],
                        "game_play_type" =>  $quick_play_parameters_array[$reward_type_slug]["name"] ,
                        "number_of_questions_for_win" =>  $quick_play_parameters_array[$reward_type_slug]["number_of_questions_for_win"] ,
                        "game_time" =>  $quick_play_parameters_array[$reward_type_slug]["game_time"] ,
                        "slug" =>  $reward_type_slug,
                        "game_secret_key" => Utilities::create_random_number(7),
                        "game_unique_identifier"  =>  $game_unique_identifier,
                        "game_id"  =>   $game_id,
                        'start_time' => $start_time,
                        'difficulty_level' => $one_randomized_difficulty_level

                    ];

                $response_message =     [
                    'error' => null,
                    'errorText' => "",
                    'data' => $playGameQuestionList,
                    'resultCode' => "10", //10 means positive success
                    'resultText' => "Question play game started successful ",
                    'resultStatus' => true
                ];

                return response()->json( $response_message );

            }
            else
            {
                // Game play purchase was not successful. Return error message

                $playGameError =
                    [
                        " Game Purchase was unsuccessful. Please try again. "
                    ];

                $response_message =
                    [
                        'error' =>  $playGameError,
                        'errorText' =>"Game Purchase was unsuccessful. Please try again.",
                        'data' =>  null,
                        'resultCode' => "50", // 50 means error message
                        'resultText' => "Error in ",
                        'resultStatus' => false
                    ];
                return response()->json($response_message);
            }
        }
        else
        {
            //User not found
            // User Profile not found or collection is empty
            $userProfileResult =  [
                "user" =>   null ,
                "game_count" => (int) null,
                "win_count" => (int) null
            ];

            $response_message =
                [
                    'error' => null,
                    'errorText' => "",
                    'data' => [
                        $userProfileResult
                    ],
                    'resultCode' => "40", // 40 means user not found
                    'resultText' => "Error, user profile not found.",
                    'resultStatus' => false
                ];

            return response()->json($response_message);
        }


    }

    public function quick_games_next_question(Request $request)
    {
//        return response()->json( ["response" => $request->all()   ]   );

        $validator = Validator::make($request->all(),
            [
                'game_unique_identifier'   => 'required',
                "platform" =>  "required",
                "current_game_score"  =>  "required|numeric",
                'question_id'   => 'required|numeric',
                "question_number" =>  "required|numeric",
                "game_start_time"  =>  "required",
                'correct_answer_id'   => 'required|numeric',
                "chosen_answer_id" =>  "required|numeric",
                "previous_score"  =>  "required|numeric",
                "difficulty_level" => "required|numeric",
                "reward_stakes" =>  "required|numeric",
                "game_play_type" =>  "required",
                "slug" =>  "required",
                "game_secret_key" => "required",
                "number_of_questions_for_win"=> "required|numeric"
            ]);

        if ($validator->fails())
        {
            $validatorList = [];
            foreach (collect($validator->errors()) as $eachValidation)
            {

                $validatorList[] = $eachValidation[0]; // Get the first Error for this field
            }
            $response_message =
                [
                    'error' =>  $validatorList ,
                    'errorText' => "Validation Error",
                    'data' => null,  // Data should return null whenever error occures
                    'resultCode' => "55", // 55 means error due to validation
                    'resultText' => "Failure due to validation error.",
                    'resultStatus' => false
                ];

            return response()->json($response_message);
        }

        //Extract User from Auth
        $userModel = $this->userModel;

        if($userModel->isNotEmpty())
        {
            // This method will save game_details and release a new set question to user

            // 1. Check that game_unique_identifier is valid and represent a game
            // 2. Get Score and Save GameDetails Row
            // 3.  Prepare another Question using Adaptivea approach to generate next Question
            // 4.  Send Response

            $game_unique_identifier = $request->game_unique_identifier;
            $platform = $request->platform;
            $score = $request->current_game_score;

            $question_id = $request->question_id;
            $question_number = $request->question_number;
            $game_start_time = $request->game_start_time;

            $correct_answer_id = $request->correct_answer_id;
            $chosen_answer_id = $request->chosen_answer_id;
            $previous_score = $request->previous_score;
            $current_difficulty_level = $request->difficulty_level;

            $reward_stakes  =  $request->reward_stakes; //  $quick_play_parameters_array[$reward_type_slug]["reward_stakes"],
            $game_play_type =   $request->game_play_type; // $quick_play_parameters_array[$reward_type_slug]["name"] ,
            $slug =   $request->slug;
            $number_of_questions_for_win =   $request->number_of_questions_for_win;
            $game_secret_key = $request->game_secret_key;

            $user_id = $userModel->has("id") ? $userModel->get("id") : " ";
            $user_phone = $userModel->has("phone") ? $userModel->get("phone") : " ";

            $games = Game::where("game_unique_identifier", $game_unique_identifier )->get();


            if(count($games)  >  0 )
            {
                $this_game = $games->first();

                // if $isCorrectAnswer is true, then increment score and save GameDetail Table
                $game_details_array['game_unique_identifier'] =  $game_unique_identifier;
                $game_details_array['question_id'] =  $question_id;
                $game_details_array['question_number'] = $question_number;
                $game_details_array['game_start_time'] = $game_start_time;
                $game_details_array['correct_answer_id'] = $correct_answer_id;
                $game_details_array['chosen_answer_id'] = $chosen_answer_id;
                $game_details_array['current_game_score'] = $score;
                $game_details_array['current_game_status_id'] = 3;
                $game_details_array['game_id'] = $this_game->id;

                GameDetail::create($game_details_array);

                // Do for loop that generates 1 questions
                $stop_number = 1; //Maximum number of Questions
                $adaptive_difficulty_level = 1;

                $question_category = QuestionCategory::all()->pluck('id');
                $question_category_count = count($question_category);

                $user_attempted_questions = DB::table("game_details as gd")
                    ->join('games as g', 'g.id', '=', 'gd.game_id')
                    ->where([ "g.user_id" =>  $user_id ]  )
                    ->groupBy(['gd.question_id' ])
                    ->select(['gd.question_id'])
                    ->get();

                $one_randomized_question = [];
                if(count($user_attempted_questions) > 0)
                {
                    $questions_attempted_array =  $user_attempted_questions->pluck("question_id");
                }
                else
                {
                    $questions_attempted_array =  [];
                }

                for ($x = 0; $x < $stop_number; $x++)
                {
                    $one_randomized_question_category = random_int(0, ($question_category_count - 1));

                    $adaptive_difficulty_level  =  $this->computeAdaptiveBehaviour($previous_score, $score, $current_difficulty_level);

                    $questions = Question::where([ 'is_pretest' => 0])
                        ->whereNotIn("id" , $questions_attempted_array ) // Get Question
                        ->where([
                                    "difficulty_level" => $adaptive_difficulty_level  ,
                                    "question_category_id" => $one_randomized_question_category
                        ])
                        ->orderByRaw(" RAND() ")
                        ->take(50)
                        ->with('answers')
                        ->get();


                    if($questions->count()  > 0 )
                    {
                        $random_index = random_int(0, ($questions->count() - 1));
                        $one_randomized_question = $questions->get($random_index);
                    }
                    else
                    {
                        //Skip this loop
                        --$x;
                        continue;
                    }

                }

                // Push Next Question Out to response

                $user_wallet = new Wallet();

                $user_wallet->init($user_phone);

                $balance = $user_wallet->balance;

                $playGameQuestionList =
                    [
                        "question" =>   $one_randomized_question ,
                        "amount_played" =>   $this_game->amount_staked,
                        "reward_type" =>   $this_game->game_type_id,
                        "balance" =>   $balance,
                        "user_id" =>   $user_id,
                        "expected_winning" =>  $this_game->expected_winning,
                        "reward_stakes" =>  $reward_stakes,
                        "game_play_type" =>  $game_play_type,
                        "slug" =>  $slug,
                        "game_secret_key" => $game_secret_key,
                        "game_unique_identifier"  =>  $game_unique_identifier,
                        "game_id"  =>   $this_game->id,
                        'start_time' =>  $game_start_time,
                        'difficulty_level' => $adaptive_difficulty_level,
                        "number_of_questions_for_win" =>  $number_of_questions_for_win
                    ];

                $response_message =     [
                    'error' => null,
                    'errorText' => "",
                    'data' => $playGameQuestionList,
                    'resultCode' => "10", //10 means positive success
                    'resultText' => "Next Question play game sent  successful ",
                    'resultStatus' => true
                ];

                return response()->json( $response_message );

            }
            else
            {
                // Game does not exist

                $response_message =
                    [
                        'error' => null,
                        'errorText' => "",
                        'data' =>  null,
                        'resultCode' => "40", // 40 means user not found
                        'resultText' => "Invalid Game. Game not found.",
                        'resultStatus' => false
                    ];

                return response()->json($response_message);
            }


        }
        else
        {
            //User not found
            // User Profile not found or collection is empty
            $userProfileResult =  [
                "user" =>   null ,
                "game_count" => (int) null,
                "win_count" => (int) null
            ];

            $response_message =
                [
                    'error' => null,
                    'errorText' => "",
                    'data' => [
                        $userProfileResult
                    ],
                    'resultCode' => "40", // 40 means user not found
                    'resultText' => "Error, user profile not found.",
                    'resultStatus' => false
                ];

            return response()->json($response_message);
        }


    }

    public function quick_games_submit_question(Request $request)
    {
//        return response()->json( ["response" => $request->all()   ]   );

        $validator = Validator::make($request->all(),
            [
                'game_unique_identifier'   => 'required',
                "platform" =>  "required",
                "current_game_score"  =>  "required|numeric",
                'question_id'   => 'required|numeric',
                "question_number" =>  "required|numeric",
                "game_start_time"  =>  "required",
                'correct_answer_id'   => 'numeric',
                "chosen_answer_id" =>  "numeric",
                "previous_score"  =>  "required|numeric",
                "difficulty_level" => "required|numeric",
                "reward_stakes" =>  "required|numeric",
                "game_play_type" =>  "required",
                "slug" =>  "required",
                "game_secret_key" => "required",
                "number_of_questions_for_win"=> "required|numeric"
            ]);

        if ($validator->fails())
        {
            $validatorList = [];
            foreach (collect($validator->errors()) as $eachValidation)
            {

                $validatorList[] = $eachValidation[0]; // Get the first Error for this field
            }
            $response_message =
                [
                    'error' =>  $validatorList ,
                    'errorText' => "Validation Error",
                    'data' => null,  // Data should return null whenever error occures
                    'resultCode' => "55", // 55 means error due to validation
                    'resultText' => "Failure due to validation error.",
                    'resultStatus' => false
                ];

            return response()->json($response_message);
        }

        //Extract User from Auth
        $userModel = $this->userModel;

        if($userModel->isNotEmpty())
        {
            // This method will save game_details and release a new set question to user

            // 1.  Check that game_unique_identifier is valid and represent a game
            // 2.  Get Score and Save GameDetails Row
            // 3.  Check that current score is same or greater than 8 ( or number for correct answer )
            // 4 . if true, create Reward or else dont create Reward
            // 5.  Save GameStatus Tracker
            // 6.  Update games table according here : game_state_changed and updated_at

            $game_unique_identifier = $request->game_unique_identifier;
            $platform = $request->platform;
            $score = $request->current_game_score;

            $question_id = $request->question_id;
            $question_number = $request->question_number;
            $game_start_time = $request->game_start_time;
            $correct_answer_id = $request->correct_answer_id;
            $chosen_answer_id = $request->chosen_answer_id;
//            $previous_score = $request->previous_score;
            $current_difficulty_level = $request->difficulty_level;

            $reward_stakes  =  $request->reward_stakes;
            $game_play_type =   $request->game_play_type;
            $slug =   $request->slug;
            $number_of_questions_for_win =   $request->number_of_questions_for_win;

            $game_secret_key = $request->game_secret_key;

            $winning_status = 5;
            $user_id = $userModel->has("id") ? $userModel->get("id") : " ";
            $user_phone = $userModel->has("phone") ? $userModel->get("phone") : " ";

            $games = Game::where("game_unique_identifier", $game_unique_identifier )->get();

            if(count($games)  >  0 )
            {

                $PlatformTransformerArray =
                    [
                        "web" => "2",
                        "mobile" => "4"
                    ];

                $this_game = $games->first();

                // if $isCorrectAnswer is true, then increment score and save GameDetail Table
                $game_details_array['game_unique_identifier'] =  $game_unique_identifier;
                $game_details_array['question_id'] =  $question_id;
                $game_details_array['question_number'] = $question_number;
                $game_details_array['game_start_time'] = $game_start_time;
                $game_details_array['correct_answer_id'] = $correct_answer_id;
                $game_details_array['chosen_answer_id'] = $chosen_answer_id;
                $game_details_array['current_game_score'] = $score;
                $game_details_array['current_game_status_id'] = 3;
                $game_details_array['game_id'] = $this_game->id;

                GameDetail::create($game_details_array);


                // Check that score is same or greater than number_of_score_for_corrrect
                if( $score >= $number_of_questions_for_win  )
                {
                    // Create Reward Row for this user
                    $amount_rewarded =   (int)$this_game->amount_staked * (int)$reward_stakes;

                    Log::info($amount_rewarded . " ".  $reward_stakes . " " .  $this_game->amount_staked   );

                    $reward_data = [
                                        'phone' => $user_phone,
                                        'amount' => $amount_rewarded,
                                        'info' => "Game Play",
                                        'registration_channel_id' => $PlatformTransformerArray[$platform]
                                 ];
                    //Create a Reward Model and Store reward
                    Reward::create($reward_data);

                    // Create GameStatusTracker Row for win

                    $winning_status = 4;
                    $winning_statement = "win";

                    $user_wallet = new Wallet();

                    $user_wallet->init($user_phone);

                    $new_balance = $user_wallet->balance;

                        $game_status_tracker_data =
                            [
                                'game_id' => $this_game->id,
                                'game_status_id' => $winning_status,  /* This is the id for game that was won */
                                'expected_winning' => $this_game->expected_winning,
                                'actual_winning' => $amount_rewarded,
                                'score' => $score,
                                'wallet_balance' => $new_balance,
                                'total_actual_winning' => $amount_rewarded,
                                'start_time' => $game_start_time,
                                'end_time' => Carbon::now(),
                                'amount_staked' => $this_game->amount_staked ,
                                'previous_game_status_id' => 3,
                            ];


                        GameStatusTracker::create($game_status_tracker_data);
                }
                else
                {
                    // Create GameStatusTracker Row for Loss

                    $user_wallet = new Wallet();

                    $user_wallet->init($user_phone);

                    $new_balance = $user_wallet->balance;
                    $winning_status =   5;
                    $winning_statement = "loss";
                    $amount_rewarded = 0;

                    $game_status_tracker_data =
                        [
                            'game_id' => $this_game->id,
                            'game_status_id' => $winning_status,  /* This is the id for game that was lost */
                            'expected_winning' => $this_game->expected_winning,
                            'actual_winning' => $amount_rewarded,
                            'score' => $score,
                            'wallet_balance' => $new_balance,
                            'total_actual_winning' => 0,
                            'start_time' => $game_start_time,
                            'end_time' => Carbon::now(),
                            'amount_staked' => $this_game->amount_staked ,
                            'previous_game_status_id' => 3,
                        ];

                    GameStatusTracker::create($game_status_tracker_data);
                }

                //Update games table according here : game_state_changed and updated_at
                $this_game->updated_at = Carbon::now();
                $this_game->game_state_changed = 1;
                $this_game->save();

                $GameResultList =
                    [
                        "amount_played" =>   $this_game->amount_staked,
                        "reward_type" =>   $this_game->game_type_id,
                        "balance" =>   $new_balance,
                        "user_id" =>   $user_id,
                        "score" =>   $score,
                        "expected_winning" =>  $this_game->expected_winning,
                        "reward_stakes" =>  $reward_stakes,
                        "game_play_type" =>  $game_play_type,
                        "slug" =>  $slug,
                        'actual_winning' => $amount_rewarded,
                        'winning_status' => $winning_status,
                        'winning_statement' => $winning_statement,
                        "game_secret_key" => $game_secret_key,
                        "game_unique_identifier"  =>  $game_unique_identifier,
                        "game_id"  =>   $this_game->id,
                        'start_time' =>  $game_start_time,
                        "number_of_questions_for_win" =>  $number_of_questions_for_win
                    ];

                $response_message =
                                 [
                                            'error' => null,
                                            'errorText' => "",
                                            'data' => $GameResultList,
                                            'resultCode' => "10", //10 means positive success
                                            'resultText' => "Game Result Message. ",
                                            'resultStatus' => true
                                ];

                return response()->json( $response_message );

            }
            else
            {
                // Game does not exist

                $response_message =
                    [
                        'error' => null,
                        'errorText' => "",
                        'data' =>  null,
                        'resultCode' => "40", // 40 means user not found
                        'resultText' => "Invalid Game. Game not found.",
                        'resultStatus' => false
                    ];

                return response()->json($response_message);
            }
        }
        else
        {
            //User not found
            // User Profile not found or collection is empty
            $userProfileResult =  [
                "user" =>   null ,
                "game_count" => (int) null,
                "win_count" => (int) null
            ];

            $response_message =
                [
                    'error' => null,
                    'errorText' => "",
                    'data' => [
                        $userProfileResult
                    ],
                    'resultCode' => "40", // 40 means user not found
                    'resultText' => "Error, user profile not found.",
                    'resultStatus' => false
                ];

            return response()->json($response_message);
        }

    }

    private function computeAdaptiveBehaviour( $previous_score, $score, $difficulty_level)
    {
        // Preliminary Computation
        if($difficulty_level > 3)
        {
            $difficulty_level = 3;  // reset to 3
        }
        if( $difficulty_level  <  1)
        {
            $difficulty_level = 1;  // reset to 1
        }
        $result_array =
            [
                "difficulty_level"  =>  $difficulty_level
            ];
        //If $previous_score is the same  as $score , decrease  difficulty level by  1
        // If $score is greater than  $previous_score, increase  difficulty level by  1
        // If $score is less than $previous_score   , decrease  difficulty level by  1

        // if difficulty level is greater than 3 , reset to 3
        //if difficulty level is less than 1, reset to 1

        if($score == $previous_score)
        {
            $result_array["difficulty_level"]   = $difficulty_level - 1;

        }
        else if ($score > $previous_score)
        {
            $result_array["difficulty_level"]   = $difficulty_level + 1;
        }
        else if($score < $previous_score)
        {
            $result_array["difficulty_level"]   = $difficulty_level -  1;
        }
        else
        {
            $result_array["difficulty_level"]   = 3;
        }

        // Concluding  Reset Computation
        if( $result_array["difficulty_level"] > 3 )
        {
            $result_array["difficulty_level"] = 3;  // reset to 3
        }
        if ( $result_array["difficulty_level"]  <  1 )
        {
            $result_array["difficulty_level"] = 1;  // reset to 1
        }

        return  $result_array["difficulty_level"];
    }

    public function user_game_history_page_data(Request $request)
    {

        //Extract User from Auth
        $userModel = $this->userModel;

        if($userModel->isNotEmpty())
        {
            // This method will provide information for the Game History Page on the web interface

            // 1.  Get Game Played history from begining , today and Yesterday
            // 2.  Get Game wins history from beginning , today and yesterday
            // 3.  Get Game loss history from beginning , today and yesterday

            $user_id = $userModel->has("id") ? $userModel->get("id") : " ";
            $user_phone = $userModel->has("phone") ? $userModel->get("phone") : " ";

            $today_date  =  Carbon::now()->toDateString();
            $yesterday_date  =   Carbon::now()->subDay()->toDateString();


            $general_games_queryBuilder  = DB::table("games as g")
                ->join('games_status_tracker as gst', 'gst.game_id', '=', 'g.id')
                ->join('registration_channels as rc', 'rc.id', '=', 'g.registration_channel_id')
                ->join('game_types as gt', 'gt.id', '=', 'g.game_type_id')
                ->join('game_status as gs', 'gs.id', '=', 'gst.game_status_id');

            $general_games_queryBuilder_all_games  =  clone $general_games_queryBuilder;
            $general_games_queryBuilder_cloned_today  =  clone $general_games_queryBuilder;
            $general_games_queryBuilder_cloned_yesterday  =  clone $general_games_queryBuilder;

//            return response()->json( ["response" =>  $general_games_queryBuilder, "today_date" => $today_date, "yesterday_date" => $yesterday_date   ]   );

            /*************  ALL GAME HISTORY   **********************************/

            $all_games_history =  $general_games_queryBuilder_all_games
                                                ->whereIn('gst.game_status_id',[4, 5], "or")
                                              ->groupBy(['g.id' ])
                                              ->selectRaw('*, TIMESTAMPDIFF(SECOND, gst.start_time, gst.end_time) as "speed" ' )
                                               ->get();

            $all_games_today  = $general_games_queryBuilder_cloned_today->whereDate('g.created_at', $today_date )
                                                           ->groupBy(['g.id' ])->get();

            $all_games_yesterday  = $general_games_queryBuilder_cloned_yesterday->whereDate('g.created_at', $yesterday_date )
                                                                ->groupBy(['g.id' ])->get();

            /*************  ALL GAME HISTORY   **********************************/

            /*************  ALL WON GAME HISTORY   **********************************/

            $general_games_queryBuilder_cloned_won_total  =  clone $general_games_queryBuilder;
            $general_games_queryBuilder_cloned_won_today  =  clone $general_games_queryBuilder;
            $general_games_queryBuilder_cloned_won_yesterday  =  clone $general_games_queryBuilder;

            $total_all_games_won = $general_games_queryBuilder_cloned_won_total->where('gst.game_status_id' , 4 )
                                                                                ->groupBy(['g.id' ])->get();

            $all_games_won_today  = $general_games_queryBuilder_cloned_won_today->whereDate('g.created_at', $today_date )
                                                                            ->where('gst.game_status_id' , 4 )
                                                                            ->groupBy(['g.id' ])->get();

            $all_games_won_yesterday  = $general_games_queryBuilder_cloned_won_yesterday->whereDate('g.created_at', $yesterday_date )
                ->where('gst.game_status_id' , 4 )
                ->groupBy(['g.id' ])->get();


            /************* ALL WON GAME HISTORY   **********************************/

            /*************  ALL LOSS GAME HISTORY   **********************************/

            $general_games_queryBuilder_cloned_loss_total  =  clone $general_games_queryBuilder;
            $general_games_queryBuilder_cloned_loss_today  =  clone $general_games_queryBuilder;
            $general_games_queryBuilder_cloned_loss_yesterday  =  clone $general_games_queryBuilder;


            $total_all_games_loss = $general_games_queryBuilder_cloned_loss_total->where('gst.game_status_id' , 5 )
                ->groupBy(['g.id' ])->get();

            $all_games_loss_today  = $general_games_queryBuilder_cloned_loss_today->whereDate('g.created_at', $today_date )
                                                                                    ->where('gst.game_status_id' , 5 )
                                                                                    ->groupBy(['g.id' ])->get();

            $all_games_loss_yesterday  = $general_games_queryBuilder_cloned_loss_yesterday->whereDate('g.created_at', $yesterday_date )
                                                                                        ->where('gst.game_status_id' , 5 )
                                                                                        ->groupBy(['g.id' ])->get();

            /************* ALL LOSS GAME HISTORY   **********************************/



            //->orderBy('id','Desc')
//                                    ->selectRaw('g.*,  gst.*,  rc.channel_name , gt.name as game_name, gs.status')
//                                    ->selectRaw('g.*,  gst.*,  rc.channel_name , gt.name as game_name, gs.status')
//                                    ->take(10)


            //                ->whereDate('g.created_at', "2019-10-30")
//                ->orderBy('created_at','Desc')
//                          ->where('gst.game_status_id' , 1 ) // get all games that got started
//                ->groupBy(['gst.game_id' ])
//            ->whereRaw(' gst.created_at in (select MAX(games_status_tracker.created_at) from games_status_tracker group by game_id )' )
//                ->where('g.game_sent' , 0 ) // when the game is completed

//                ->skip($start_from)
//                ->take($pageSize)


//            return response()->json( [
//                "response_count" =>  count($all_games_history) ,
//                "response" =>  $all_games_history  ]   );

//            "id": "4",
//            "user_id": "81",
//            "game_unique_identifier": "91b3f264-71b6-407c-b291-d5aee9915880",
//            "purchase_id": null,
//            "game_sent": "0",
//            "registration_channel_id": "4",
//            "game_type_id": "1",
//            "expected_winning": "5",
//            "created_at": "2019-06-28 09:23:17",
//            "updated_at": null,
//            "deleted_at": null,
//            "amount_staked": "1",
//            "game_state_changed": "1",
//            "game_received_verified": "0",
//            "game_id": "284",
//            "game_status_id": "4",
//            "previous_game_status_id": "3",
//            "actual_winning": "2.5",
//            "score": "4",
//            "wallet_balance": "1888",
//            "total_actual_winning": "2.5",
//            "start_time": "2019-08-20 12:31:35",
//            "end_time": null,
//            "channel_name": "Mobile",
//            "channel_description": null,
//            "name": "other pay",
//            "description": "win ",
//            "status": "win"
//        }

            $game_status_array =
                [
                    "win"  =>     "01",
                    "loss" =>     "02",
                    "void" =>     "03",
                    "pending" =>  "04",
                    "fixed" =>    "05",
                ];

            $game_play_array = [];

            foreach($all_games_history as $key =>  $game)
            {
                $game_id_key = $game->game_id;
                $game_unique_identifier = $game->game_unique_identifier;
                $date  = date_create($game->start_time);
                $formatted_date =  date_format($date,"Y-m-d");
                $formatted_time =  date_format($date,"h:m:s");
                $amount_won =  $game->actual_winning;
                $amount_played =  $game->amount_staked;
                $status =   $game->status;
                $game_type = $game->name;
                $play_channel  = $game->channel_name;
                $time_spent  = $game->speed;

                $game_play_array_element["gameID"] =  $game_id_key;
                $game_play_array_element["amount_won"] = number_format($amount_won, 0, '.', ',');
                $game_play_array_element["amount_played"] =number_format($amount_played, 0, '.', ',');
                $game_play_array_element["formatted_date"] =  $formatted_date;
                $game_play_array_element["formatted_time"] = $formatted_time;
                $game_play_array_element["status"] = $status;
                $game_play_array_element["package"] =  ucwords($game_type);
                $game_play_array_element["category"] =  null;
                $game_play_array_element["play_channel"] = $play_channel;
                $game_play_array_element["time_spent"] = $time_spent; // in seconds


                $game_play_array[] = $game_play_array_element;
            }


            $GameHistoryList =
                [
                    "game_history_list" =>   $game_play_array,

                    "all_games_total_count" =>   count($all_games_history),
                    "all_games_today_count" =>   count($all_games_today),
                    "all_games_yesterday_count" =>   count($all_games_yesterday),

                    "total_all_games_won" =>   count($total_all_games_won),
                    "all_games_won_today_count" =>   count($all_games_won_today),
                    "all_games_won_yesterday_count" =>   count($all_games_won_yesterday),

                    "total_all_games_loss" =>   count($total_all_games_loss),
                    "all_games_loss_today" =>   count($all_games_loss_today),
                    "all_games_loss_yesterday" =>   count($all_games_loss_yesterday)
                ];

            $response_message =
                [
                    'error' => null,
                    'errorText' => "",
                    'data' => $GameHistoryList,
                    'resultCode' => "10", //10 means positive success
                    'resultText' => "Game History Data. ",
                    'resultStatus' => true
                ];

            return response()->json( $response_message );

        }
        else
        {
            //User not found
            // User Profile not found or collection is empty
            $userProfileResult =  [
                "user" =>   null ,
                "game_count" => (int) null,
                "win_count" => (int) null
            ];

            $response_message =
                [
                    'error' => null,
                    'errorText' => "",
                    'data' => [
                        $userProfileResult
                    ],
                    'resultCode' => "40", // 40 means user not found
                    'resultText' => "Error, user profile not found.",
                    'resultStatus' => false
                ];

            return response()->json($response_message);
        }

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
        if ($this->user()->role == 'admin') {
            $users = User::whereIn('role', ['user', 'admin'])->paginate();
        }
        else if ($this->user()->role == 'superadmin') {
            $users = User::paginate();
        }
        else {
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

        if(!$userModel->isNotEmpty())
        {
            $username = $userModel->has("name") ? $userModel->get("name") : " ";
            $user_phone = $userModel->has("phone") ? $userModel->get("phone") : " ";
            $user_id = $userModel->has("id") ? $userModel->get("id") : " ";
            $first_name = $userModel->has("othernames") ? $userModel->get("othernames") : " ";
            $last_name = $userModel->has("surname") ? $userModel->get("surname") : " ";
            $userDetailsArray =
            [
                "user_id" => $user_id,
                "phone" => $user_phone,
                "username" => $username,
                "first_name" => $first_name,
                "last_name" => $last_name
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

//            return response()->json( ["response" => $winned_games  ]   ); // Petty response


            $userProfileResult =  [
                                    "user" =>   $userDetailsArray ,
                                    "game_count" => count($user_games),
                                    "win_count" => count($winned_games)
                                  ];

            $response_message =
                                [
                                    'error' => null,
                                    'errorText' => "",
                                    'data' => [
                                                $userProfileResult
                                              ],
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
                    'data' => [
                        $userProfileResult
                    ],
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
