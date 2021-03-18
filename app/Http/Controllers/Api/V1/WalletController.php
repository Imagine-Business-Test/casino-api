<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\FundTransfer;
use App\Http\Controllers\ZenithFundTransfer;
use App\Libraries\TransRef;
use App\Libraries\Utilities;
use App\Models\BankAccounts;
use App\Models\BankCode;
use App\Models\DifficultyLevel;
use App\Models\GameType;
use App\Models\PayStack;
use App\Models\Question;
use App\Models\QuestionCategory;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WebPay;
use App\Models\Withdrawal;
use Auth;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class WalletController extends BaseController
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
                    'data' => $demoQuestionList,
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
                    'errorText' => "Error, user profile not found. Please login with username and password.",
                    'data' => $userProfileResult,
                    'resultCode' => "40", // 40 means user not found
                    'resultText' => "Error, user profile not found.",
                    'resultStatus' => false

                ];

            return response()->json($response_message);
        }

    }

    public function play_game(Request $request)
    {
//        return response()->json( ["response" => $request->all()   ]   ); //

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


                $playGameQuestionList = [
                                                "questions" =>   $question_list ,
                                                "amount_played" =>   $amount,
                                                "reward_type" =>   $reward_type,
                                                "balance" =>   $user_wallet->balance,
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
                    'data' => $userProfileResult,
                    'resultCode' => "40", // 40 means user not found
                    'resultText' => "Error, user profile not found.",
                    'resultStatus' => false
                ];

            return response()->json($response_message);
        }

    }

    public function  load_wallet(Request $request)
    {
        //Extract User from Auth
        $userModel =  $this->userModel;

        if($userModel->isNotEmpty())
        {
            $user_phone = $userModel->has("phone") ? $userModel->get("phone") : " ";

            $user_id = $userModel->has("id") ? $userModel->get("id") : " ";

            $user_wallet = new Wallet();

            $user_wallet->init($user_phone);

            $balance = $user_wallet->balance;

//            return response()->json( ["response" => $balance   ]   ); // Petty respons

                $CheckBalanceResponse =
                                            [
                                                "balance" =>   $balance,
                                                "user_id" =>   $user_id,
                                                "user_phone" =>  $user_phone ,
                                            ];

                $response_message =     [
                                            'error' => null,
                                            'errorText' => "",
                                            'data' => $CheckBalanceResponse,
                                            'resultCode' => "10", //10 means positive success
                                            'resultText' => "User balance successful. ",
                                            'resultStatus' => true
                                        ];
                return response()->json( $response_message );
        }
        else
        {
            // User Profile not found or collection is empty
            $response_message =
                                    [
                                        'error' => null,
                                        'errorText' => "",
                                        'data' =>  null,
                                        'resultCode' => "40", // 40 means user not found
                                        'resultText' => "Error, user profile not found. Please login with username and password.",
                                        'resultStatus' => false
                                    ];

            return response()->json($response_message);
        }
    }

    public function web_load_wallet(Request $request)
    {

        // This method will push data that which is to be used for viewing the wallet PAGE IN prewin web
        //Extract User from Auth
        $userModel =  $this->userModel;

        if($userModel->isNotEmpty())
        {
            $user_phone = $userModel->has("phone") ? $userModel->get("phone") : " ";

            $user_id = $userModel->has("id") ? $userModel->get("id") : " ";

            $user_wallet = new Wallet();

            $user_wallet->init($user_phone);

            $balance = $user_wallet->balance; // Main Balance
            $bonus =  0; // No concept of bonus exists in prewin for now
            $total_balance  =  $balance  + $bonus ; // Total Balance

            // Get payment/transaction history

            $payment_history  =  $user_wallet->transaction_history(); //


//            return response()->json( ["response" => $balance, "bonus" =>  $bonus, "payment_history" => $payment_history   ]   ); // Petty respons

            $PaymentHistoryResponse =
                [
                    "balance" =>   $balance,
                    "user_id" =>   $user_id,
                    "user_phone" =>  $user_phone ,
                    "bonus" =>   $bonus,
                    "payment_history" =>  $payment_history ,
                    "total_balance" =>   $total_balance
                ];

            $response_message =     [
                'error' => null,
                'errorText' => "",
                'data' => $PaymentHistoryResponse,
                'resultCode' => "10", //10 means positive success
                'resultText' => " Wallet Page Data.",
                'resultStatus' => true
            ];
            return response()->json( $response_message );
        }
        else
        {
            // User Profile not found or collection is empty
            $response_message =
                [
                    'error' => null,
                    'errorText' => "",
                    'data' =>  null,
                    'resultCode' => "40", // 40 means user not found
                    'resultText' => "Error, user profile not found. Please login with username and password.",
                    'resultStatus' => false
                ];

            return response()->json($response_message);
        }
    }

    public function withdraw_money_page_data(Request $request)
    {

        // This method will push data that which is to be used for viewing the withdraw money page
        // Contains List of bank,
        // List of bank accounts

        //Extract User from Auth
        $userModel =  $this->userModel;

        if($userModel->isNotEmpty())
        {
            $user_phone = $userModel->has("phone") ? $userModel->get("phone") : " ";

            $user_id = $userModel->has("id") ? $userModel->get("id") : " ";

            $user_wallet = new Wallet();

            $user_wallet->init($user_phone);

            $banks = BankCode::all()->toArray();

            $BankListResponse =
                [
                    "banks" =>   $banks,
                ];

            // Get all payment history for this user using this phone number
            $saved_bank_details = DB::table("bank_accounts")->where(['user_id' => $user_id ])->get();

                        return response()->json( ["response" => $BankListResponse, "saved_bank_details" =>  $saved_bank_details   ]   );

            $balance = $user_wallet->balance; // Main Balance
            $bonus =  0; // No concept of bonus exists in prewin for now
            $total_balance  =  $balance  + $bonus ; // Total Balance

            // Get payment/transaction history

            $payment_history  =  $user_wallet->transaction_history(); //



            $PaymentHistoryResponse =
                [
                    "balance" =>   $balance,
                    "user_id" =>   $user_id,
                    "user_phone" =>  $user_phone ,
                    "bonus" =>   $bonus,
                    "payment_history" =>  $payment_history ,
                    "total_balance" =>   $total_balance
                ];

            $response_message =     [
                'error' => null,
                'errorText' => "",
                'data' => $PaymentHistoryResponse,
                'resultCode' => "10", //10 means positive success
                'resultText' => " Wallet Page Data.",
                'resultStatus' => true
            ];
            return response()->json( $response_message );
        }
        else
        {
            // User Profile not found or collection is empty
            $response_message =
                [
                    'error' => null,
                    'errorText' => "",
                    'data' =>  null,
                    'resultCode' => "40", // 40 means user not found
                    'resultText' => "Error, user profile not found. Please login with username and password.",
                    'resultStatus' => false
                ];

            return response()->json($response_message);
        }
    }

    public function save_user_bank_details(Request $request)
    {
        // This method will save the bank account details for the user
        // Save user bank name, bank account, bank code and bank name



            //Extract User from Auth
        $userModel =  $this->userModel;

        if($userModel->isNotEmpty())
        {
            $user_phone = $userModel->has("phone") ? $userModel->get("phone") : " ";

            $user_id = $userModel->has("id") ? $userModel->get("id") : " ";

            $user_wallet = new Wallet();

            $user_wallet->init($user_phone);

           // Save account
            $validator = Validator::make($request->all(),
                [
                    'bank_account_name'     => 'required',
                    'bank_account_no'       => 'required',
                    'bank_name'             => 'required',
                    'bank_sort_code'        => 'required',
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


            $bank_account_name =  $request->bank_account_name;
            $bank_account_no =  $request->bank_account_no;
            $bank_name   =  $request->bank_name;
            $bank_sort_code  =  $request->bank_sort_code;

            try
            {
                $bank_account = new BankAccounts;

                $bank_account->bank_account_no = $bank_account_no;
                $bank_account->bank_account_name = $bank_account_name;
                $bank_account->bank_sort_code = $bank_sort_code;
                $bank_account->bank_name = $bank_name;
                $bank_account->user_id = $user_id;

                $bank_account->save();

                $BankDetailsResponse =
                                            [
                                                "user_id" =>   $user_id,
                                                "user_phone" =>  $user_phone
                                            ];

                $response_message =
                    [
                    'error' => null,
                    'errorText' => "",
                    'data' => $BankDetailsResponse,
                    'resultCode' => "10", //10 means positive success
                    'resultText' => " User bank details saved successfully.",
                    'resultStatus' => true
                ];
                return response()->json( $response_message );

            }
            catch(\Exception $e)
            {
                // return with error
                $response_message =

                    [
                        'error' => $e->getMessage(),
                        'errorText' => "",
                        'data' => [
                                         'message' => 'error creating user.'
                                  ],
                        'resultCode' => "50", // 50 means error message
                        'resultText' => "Unable to save bank details.",
                        'resultStatus' => false
                    ];

                return response()->json($response_message);

            }

        }
        else
        {
            // User Profile not found or collection is empty
            $response_message =
                [
                    'error' => null,
                    'errorText' => "",
                    'data' =>  null,
                    'resultCode' => "40", // 40 means user not found
                    'resultText' => "Error, user profile not found. Please login with username and password.",
                    'resultStatus' => false
                ];

            return response()->json($response_message);
        }
    }

    public function  fund_deposit_paystack(Request $request)
    {

        // The purpose of this function is to collect payment option and deposit amount from the mobile app
        //and release  a paystack initiation url to the user

        // Additional feature( to come in later ), the lumen_prewin program will dispatch the paystack  notification response, if and only if, the registration_channel is from mobile app = 4. The mobile app api will receive the response and create a instant notification which alerts the user of success or failure


        // Fund Account using the  Paystack Card option
            //1. Verify by Validation option "paystack is sent
            // 2. Initialize Paystack and get URL after doing all necessary checks
            // 3. Return paystack URL with instruction . Return response

        $validator = Validator::make($request->all(),
            [
                'payment_option'       => 'required',
                'amount'        => 'required|integer|min:1',
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


            $payment_option = $request->payment_option;
            $amount  = $request->amount;

            if( $payment_option != "paystack" )
            {
                // We only want to process the paystack payment here
                $paymentOptionMismatchError = [  " Only Paystack payment option is allowed"];

                $response_message =
                    [
                        'error' =>  $paymentOptionMismatchError ,
                        'errorText' => "Payment Option Error. Only Paystack method is allowed.",
                        'data' => null,  // Data should return null whenever error occurs
                        'resultCode' => "50", // 50 means general error message  due to validation
                        'resultText' => "Only paystack card payment can be processed. Please choose 'paystack'",
                        'resultStatus' => false
                    ];

                return response()->json($response_message);

            }

        //Extract User from Auth
        $userModel =  $this->userModel;

        if($userModel->isNotEmpty())
        {
            // 2. Initialize Paystack and get URL after doing all necessary checks

            $user_phone = $userModel->has("phone") ? $userModel->get("phone") : " ";

            $user_id = $userModel->has("id") ? $userModel->get("id") : " ";

            $webPay = new WebPay();

            $payment_amount =  $amount;

            $webPay->authorize($user_phone); // construct the paystack email for the paystack payment

            $paystack_initiation = $webPay->pay($payment_amount); //initialize paystack transaction and save to the purchase table

            Log::info(json_encode($paystack_initiation) . " => " . gettype($paystack_initiation));

            if(!is_null($paystack_initiation))
            {
                $paystack_initiation = collect($paystack_initiation);
                $authorization_url  = $paystack_initiation->has("authorization_url"  ) ?  $paystack_initiation['authorization_url'] : "";

                $text = "You have initiated a payment transaction. Please click the authorization url below in order to proceed with payment.\n\n\n Click this link : " . $authorization_url  ;

                $PaystackInitiationResponse =
                                                [
                                                    "user_id" =>   $user_id,
                                                    "user_phone" =>  $user_phone ,
                                                    "authorization_url" => $authorization_url,
                                                    "payment_option" => $payment_option,
                                                    "amount" => $amount
                                                ];

                $response_message =     [
                    'error' => null,
                    'errorText' => "",
                    'data' =>  $PaystackInitiationResponse,
                    'resultCode' => "10", //10 means positive success
                    'resultText' => $text,
                    'resultStatus' => true
                ];
                return response()->json( $response_message );


            }
            else
            {
                // Paystack initiation Fails

                $text = " Your payment attempt was unsuccessful.\n\n\n Please, try again. ";
                $response_message =
                    [
                        'error' => null,
                        'errorText' => "",
                        'data' => null ,
                        'resultCode' => "59", //    59 means error due failed payment operation
                        'resultText' => $text,
                        'resultStatus' => false
                   ];
                return response()->json( $response_message );

            }

        }
        else
        {
            // User Profile not found or collection is empty
            $response_message =
                [
                    'error' => null,
                    'errorText' => "",
                    'data' =>  null,
                    'resultCode' => "40", // 40 means user not found
                    'resultText' => "Error, user profile not found. Please login with username and password.",
                    'resultStatus' => false
                ];

            return response()->json($response_message);
        }
    }

    public function  fund_deposit_mcash(Request $request)
    {
        // The purpose of this function is to collect payment option and deposit amount from the mobile app
        //and release  a mcash code plus an orderID for the user to use

        // Additional feature( to come in later ), the lumen_prewin program will dispatch the macash notification response, if and only if, the registration_channel is from mobile app = 4. The mobile app api will receive the response and create a instant notification which alerts the user of success or failure


        // Fund Account using the Mcash Payment Option
            //1. Generated Mcash Code and Generated 5-digit orderID  alphanum
            // 2. Return Mcash Code and OrderID can return response

        $validator = Validator::make($request->all(),
            [
                'payment_option'       => 'required',
                'amount'        => 'required|integer|min:1',
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


        $payment_option = $request->payment_option;
        $amount  = $request->amount;

        if( $payment_option != "mcash" )
        {
            // We only want to process Mcash  payment here
            $paymentOptionMismatchError = [  " Only Mcash payment option is allowed"];

            $response_message =
                [
                    'error' =>  $paymentOptionMismatchError ,
                    'errorText' => "Payment Option Error. Only Mcash method is allowed.",
                    'data' => null,  // Data should return null whenever error occurs
                    'resultCode' => "50", // 50 means general error message  due to validation
                    'resultText' => "Only Mcash  payment can be processed. Please choose 'mcash'",
                    'resultStatus' => false
                ];
            return response()->json($response_message);
        }

        //Extract User from Auth
        $userModel =  $this->userModel;

        if($userModel->isNotEmpty())
        {
            // 2. Initialize Mcash and get code plus orderID after doing all necessary checks
            $user_phone = $userModel->has("phone") ? $userModel->get("phone") : " ";

            $user_id = $userModel->has("id") ? $userModel->get("id") : " ";

            $payment_amount =  $amount;

                //Proceed with Mcash in instruction
                $random_alphanum_array = Utilities::getUniqueFixedCharacters(6, ["reference" => "paystack"]);
                $random_alphanum = $random_alphanum_array["generated_character"];
                $random_alphanum = !is_null($random_alphanum) ? $random_alphanum : TransRef::getHashedToken(6); // if null is returned by Utilities::getUniqueFixedCharacters(6, ["reference" => "paystack"]), call  TransRef::getHashedToken(6)
                $mcashCode = "*402*403*96608150*". $payment_amount ."#";

                $text = "You are about to pay " . $payment_amount .  " naira through the mCash channel.\nPlease, dial this mCash code: $mcashCode \n  Your orderID is $random_alphanum, please write it down, it will be used during the mCash transaction. \n";

                // Save Details to PayStack table
                $payment_data['phone'] = $user_phone;
                $payment_data['amount'] = $payment_amount;
                $payment_data['registration_channel_id'] = 4;
                $payment_data['payment_channel_id'] = 3;// id for Zenith Bank Mcash
                $payment_data['authorization_url'] = null;
                $payment_data['access_code'] =  null;
                $payment_data['reference'] =  $random_alphanum;
                $payment_data['status'] =  0;
                $payment_data['created_at'] =  Carbon::now()->toDateTimeString();

                PayStack::create($payment_data); // Create Payment entry into the database

//            Log::info(json_encode($paystack_initiation) . " => " . gettype($paystack_initiation));

                $MCashInitiationResponse =
                    [
                        "user_id" =>   $user_id,
                        "user_phone" =>  $user_phone ,
                        "McashCode" => $mcashCode,
                        "orderID" => $random_alphanum,
                        "payment_option" => $payment_option,
                        "amount" => $amount
                    ];

                $response_message =     [
                    'error' => null,
                    'errorText' => "",
                    'data' => $MCashInitiationResponse,
                    'resultCode' => "10", //10 means positive success
                    'resultText' => $text,
                    'resultStatus' => true
                ];

                return response()->json( $response_message );


        }
        else
        {
            // User Profile not found or collection is empty
            $response_message =
                [
                    'error' => null,
                    'errorText' => "",
                    'data' =>  null,
                    'resultCode' => "40", // 40 means user not found
                    'resultText' => "Error, user profile not found. Please login with username and password.",
                    'resultStatus' => false
                ];

            return response()->json($response_message);
        }
    }

    public function  fund_withdraw_money(Request $request)
    {
        /**
         *The purpose of this function is to take the bank details of player and amount provided by the player and send the amount to their bank account provided.
         *
         * collect: bank account name
         *          bank account number
         *          bank name
         *          bank_code
         *
         * 1.  Check again that the amount is less than or equal to the player balance
         * 2.  Check that the bank name is in the list from the BankCode table. Then extract bank code from the table BankCode table
         * 3.  Initiate Transfer and return response appropriately
         *
         * */


        $validator = Validator::make($request->all(),
            [
                'amount'        => 'required|integer|min:1',
                "bank_account_name" =>  'required|min:1',
                "bank_account_number" =>  'required|min:10',
                "bank_name" => 'required|min:1',
                "bank_code" => 'required|min:1',
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

        $activity_log_decoded_bot_parameter = [];

        $amount  = $request->amount;
        $bank_account_name =   $request->bank_account_name;
        $bank_account_number =   $request->bank_account_number;
        $bank_name =   $request->bank_name;
        $bank_code =   $request->bank_code;


        $activity_log_decoded_bot_parameter["bank_name"] = $bank_name ;
        $activity_log_decoded_bot_parameter["bank_account_name"] = $bank_account_name ;
        $activity_log_decoded_bot_parameter["bank_account_no"] = $bank_account_number ;
        $activity_log_decoded_bot_parameter["bank_sort_code"] = $bank_code ;
        $activity_log_decoded_bot_parameter["withdrawal_amount"] = $amount ;


        //Extract User from Auth
        $userModel =  $this->userModel;

        if($userModel->isNotEmpty())
        {
            $user_phone = $userModel->has("phone") ? $userModel->get("phone") : " ";

            $user_id = $userModel->has("id") ? $userModel->get("id") : " ";

            $user_wallet = new Wallet();

            $user_wallet->init($user_phone);

            if( $user_wallet->balance >= $amount  )
            {
                $withdrawal_response = $this->transferFundToUser('paystack', $user_phone , $activity_log_decoded_bot_parameter );

                Log::info("Paystack " . json_encode($withdrawal_response));

                if($withdrawal_response["success"] == true) // If api call from paystack/zenith comes back as true
                {
                    //Perform fund reduction process
                    //Give positive response and end process
                    $text = "Congratulations, " . $bank_account_name  .  "! \n\n\n *Your bank account has been credited with " . $amount. " naira. Thank you";

                    $WithdrawMoneySendPResponse =
                        [
                            "user_id" =>   $user_id,
                            "user_phone" =>  $user_phone ,
                            "amount" => $amount
                        ];

                    $response_message =     [
                        'error' => null,
                        'errorText' => "",
                        'data' => $WithdrawMoneySendPResponse,
                        'resultCode' => "10", //10 means positive success
                        'resultText' => $text,
                        'resultStatus' => true
                    ];

                    return response()->json( $response_message );
                }
                else
                {
                    //Fund transfer attempt was not successful
                    $text = "Sorry fund transfer attempt was not successful, you can contact our customer service at customercare@prewin.com.ng . We apologize for the inconvenience";

                    $WithdrawMoneyError = [ $text ];

                    $response_message =
                        [
                            'error' =>  $WithdrawMoneyError ,
                            'errorText' => $text,
                            'data' => null,  // Data should return null whenever error occurs
                            'resultCode' => "50", // 50 means general error message  due to validation
                            'resultText' =>  $text ,
                            'resultStatus' => false
                        ];
                    return response()->json($response_message);

                }

            }
            else
            {
                $WithdrawMoneyError = [ "Your cash out amount cannot be greater than your wallet balance." ];

                $response_message =
                    [
                        'error' =>  $WithdrawMoneyError ,
                        'errorText' => "Your cash out amount cannot be greater than your wallet balance.",
                        'data' => null,  // Data should return null whenever error occurs
                        'resultCode' => "50", // 50 means general error message  due to validation
                        'resultText' => "Your cash out amount cannot be greater than your wallet balance.",
                        'resultStatus' => false
                    ];
                return response()->json($response_message);
            }
        }
        else
        {
            $response_message =
                [
                    'error' => null,
                    'errorText' => "Error, user profile not found. Please login with username and password.",
                    'data' =>  null,
                    'resultCode' => "40", // 40 means user not found
                    'resultText' => "Error, user profile not found. Please login with username and password.",
                    'resultStatus' => false
                ];

            return response()->json($response_message);
        }
    }

    private function transferFundToUser($string, $user_phone, $activity_log_decoded_bot_parameter) : array
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
        $user_wallet->init($user_phone);

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

                    $withdrawal_reference = Utilities::create_random_number(15);
                    $FundTransfer = new FundTransfer($data, $withdrawal_reference); // Normal PHP method
                    //initiate
                    $initiate = $FundTransfer->authorize();

                    Log::info("Initiate" . json_encode($initiate));
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
                                "phone" =>  $user_phone,
                                "amount" => $fund_transfer_amount ,
                                "info" => "User fund transfer",
                                'registration_channel_id' => 4,
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

                    $withdrawal_reference = Utilities::create_random_number(15);
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
                                "phone" =>  $user_phone,
                                "amount" => $fund_transfer_amount ,
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

        Log::info("Final Response for Mobile app". json_encode($fund_transfer_function_response));
        return $fund_transfer_function_response;
    }

    public function  fund_withdraw_send_otp(Request $request)
    {

//        return response()->json($request->all());
        /**
         *The purpose of this function is to send an otp SMS to the player upon loading of the withdraw money page. The otp token is auto generated and send to the user using the the appropriate gateway
         *
         *
         *Send OTP to user
         *
         * 1. Check that user amount posted is less than or greater than user balance
         * 2. Generate random numeric token
         * 3. Send OTP to user and send otp token back as
         * */

        $validator = Validator::make($request->all(),
            [
                'amount'        => 'required|integer|min:1',
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


//        *Send OTP to user
//    *
//    * 1. Check that user amount posted is less than or greater than user balance
//    * 2. Generate random numeric token
//    * 3. Send OTP to user and send otp token back as
        $amount  = $request->amount;

        //Extract User from Auth
        $userModel =  $this->userModel;

        if($userModel->isNotEmpty())
        {
            $user_phone = $userModel->has("phone") ? $userModel->get("phone") : " ";

            $user_id = $userModel->has("id") ? $userModel->get("id") : " ";

            $user_wallet = new Wallet();

            $user_wallet->init($user_phone);


            if( $user_wallet->balance >= $amount  )
            {
                //Proceed with Mcash in instruction
                $sms_code = Utilities::create_random_number(6);

                $sms_gateway = new  SMSGatewayController();
                $sms_gateway->triggerSMS( $user_phone, "SMS Verification Code for Fund Withdrawal: ". $sms_code  );
                Log::info("Send Message from VAS Gateway ===> " . $user_phone   . " SMS Verification Code for Fund Withdrawal: " .  $sms_code  );

                $text = "An SMS Verification code has been sent to your phone number. Please reply with the 6-digit code in other to proceed with fund withdrawal* \n\nThank you!";

                $WithdrawMoneySendOTPResponse =
                                                [
                                                    "user_id" =>   $user_id,
                                                    "user_phone" =>  $user_phone ,
                                                    "SMSCode" => $sms_code, // To be saved
                                                    "amount" => $amount
                                                ];

                $response_message =     [
                    'error' => null,
                    'errorText' => "",
                    'data' => $WithdrawMoneySendOTPResponse,
                    'resultCode' => "10", //10 means positive success
                    'resultText' => $text,
                    'resultStatus' => true
                ];

                return response()->json( $response_message );

            }
            else
            {
                $WithdrawMoneyError = [ "Your cash out amount cannot be greater than your wallet balance." ];

                $response_message =
                    [
                        'error' =>  $WithdrawMoneyError ,
                        'errorText' => "Your cash out amount cannot be greater than your wallet balance.",
                        'data' => null,  // Data should return null whenever error occurs
                        'resultCode' => "50", // 50 means general error message  due to validation
                        'resultText' => "Your cash out amount cannot be greater than your wallet balance.",
                        'resultStatus' => false
                    ];
                return response()->json($response_message);
            }
        }
        else
        {
            $response_message =
                [
                    'error' => null,
                    'errorText' => "Error, user profile not found. Please login with username and password.",
                    'data' =>  null,
                    'resultCode' => "40", // 40 means user not found
                    'resultText' => "Error, user profile not found. Please login with username and password.",
                    'resultStatus' => false
                ];

            return response()->json($response_message);
        }
    }

    public function  list_of_banks(Request $request)
    {
//        return response()->json($requesest->all());
        /**
         *The purpose of this function is to get the list of bank in Nigeria
         *
         * 1.  Get List of banks
         *
         *
         * */

        //Extract User from Auth
        $userModel =  $this->userModel;

        if($userModel->isNotEmpty())
        {
            $user_phone = $userModel->has("phone") ? $userModel->get("phone") : " ";

            $user_id = $userModel->has("id") ? $userModel->get("id") : " ";

            $banks = BankCode::all()->toArray();

            $BankListResponse =
                [
                    "banks" =>   $banks,
                ];

            $response_message =     [
                                        'error' => null,
                                        'errorText' => "",
                                        'data' => $BankListResponse,
                                        'resultCode' => "10", //10 means positive success
                                        'resultText' => "Bank list successfully retrieved. ",
                                        'resultStatus' => true
                                    ];

            return response()->json( $response_message );
        }
        else
        {
            $response_message =
                [
                    'error' => null,
                    'errorText' => "Error, user profile not found. Please login with username and password.",
                    'data' =>  null,
                    'resultCode' => "40", // 40 means user not found
                    'resultText' => "Error, user profile not found. Please login with username and password.",
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
                    'errorText' => "Error, user profile not found. Please login with username and password.",
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
