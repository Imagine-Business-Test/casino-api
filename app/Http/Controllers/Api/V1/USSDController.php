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

class USSDController  extends BaseController
{
    private $token = "EAAE3hC7zj0IBAGiHhTvUU3Q0rBDlfn9sTftdPuVbLtNDQy2E1scabwpEabrvbQW7GXYUJMUMwJ39ZC7K7QqSjwSgPbyE98B7NVlL6DjmsH4q4txenMxqglfgOSwW7xo1QScvzaMLMbov5j2ZA53VBAfphoMlxYdHkxDf4xOAZDZD";

    public function paystack_webhook(Request $request, $receiver="2347038257962", $text="" )
    {

        $request_body = $request->all();

        Log::info("================================= PayStack Wehbook Call Start ==============================================");

        Log::info(json_encode($request_body));

        Log::info("================================= PayStack Wehbook Call End  ==============================================");


        //the response is decode -
        // Get the particular paystack record using the reference saved ,
        //Check that the event property is equal to "charge.success", if true update status fied in thepaystack record to 1
        //Send message to user that payment is successfull or not

        $question_array = [];
        $reference =  $request_body['data']['reference'];
        $amount =  $request_body['data']['amount'] / 100 ;
        $user_email_identifier =  $request_body['data']['customer']['email'] ;
        $user_email_identifier_array = explode("@", $user_email_identifier );
        $user_phone  =  $user_email_identifier_array[0];

        $this_user = User::where('phone', $user_phone)->get()->first();//get user model

        //Check that event property is "charge.success"
        if($request_body["event"] === "charge.success" )
        {
            //Update

            $paystack_payment =  PayStack::where('access_code' , $reference )->get();
            if(count($paystack_payment) > 0 )
            {

                $this_paystack_payment = $paystack_payment->first();
                $this_paystack_payment->status = 1;
                $this_paystack_payment->updated_at = Carbon::now();
                $this_paystack_payment->save();



                $user_wallet = new Wallet();
                $user_wallet->init($this_user->phone);



                $text = "You have successfully fund your wallet with " . $amount . " naira.\n\n\n Your wallet balance is ". $user_wallet->balance . " naira .\n\n\n You can now proceed with playing with playing your prewin game. \n\n Just Reply with  'play game'.";
                $question_array['progress_tracking'] = "completed";
                $question_array['current_operation'] = "fund wallet";
                $question_array['current_sub_operation'] = "wallet updated successfully";
                $question_array['validation_status'] = true;

                $question_array['show_template'] = true;
                $question_array['template_option_array'] = ["play game"  ];
                $question_array['bot_response_text'] = $text ;

//                return $question_array;

            }
            else
            {
                $text = "Your payment details could not be found. \nPlease, try initiating payment using the payment channel available.  \n\n Card Transaction ( Paystack,  reply with 'paystack' )\n\n USSD ( GTBank *737*, reply with 'ussd'  ) \n\n  mCash (NIBSS mCash. reply with 'mcash')";
                $question_array['progress_tracking'] = "uncompleted";
                $question_array['current_operation'] = "fund wallet";
                $question_array['current_sub_operation'] = "choose payment channel";
                $question_array['validation_status'] = true;

                $question_array['has_parameters'] = true;
                $question_array['bot_response_text'] = $text;
                $question_array['show_template'] = true;
                $question_array['template_option_array'] = ["paystack", "ussd", "mcash"  ];

//                return $question_array;

            }

        }
        else
        {
            $text = "Your payment attempt was not successful. \nPlease, try re-initiating payment using the payment channel available.  \n\n Card Transaction ( Paystack,  reply with 'paystack' )\n\n USSD ( GTBank *737*, reply with 'ussd'  ) \n\n  mCash (NIBSS mCash. reply with 'mcash')";
            $question_array['progress_tracking'] = "uncompleted";
            $question_array['current_operation'] = "fund wallet";
            $question_array['current_sub_operation'] = "choose payment channel";
            $question_array['validation_status'] = true;

            $question_array['has_parameters'] = true;
            $question_array['bot_response_text'] = $text;
            $question_array['show_template'] = true;
            $question_array['template_option_array'] = ["paystack", "ussd", "mcash"  ];


        }

        $activity_log = new ActivityLog();

        $activity_log->user_id = $this_user->id;
        $activity_log->time_initiated = Carbon::now()->timestamp; // Timestamp for now
        $activity_log->time_received = null;

        $activity_log->current_bot_action_id =  6;
        $activity_log->next_bot_action_id =  7;
        $activity_log->bot_action_parameter = json_encode($question_array)  ;

        $activity_log->created_at = Carbon::now()->toDateTimeString();

        $activity_log->save();

        //Send message to facebook

        $PR =  $this->sendFaceBookQuickReply( $request, $this_user->facebook_sender_id, $question_array['bot_response_text'] , $question_array['template_option_array'] );

        return response()->json( ["response" => true], 200  ); // reply back to paystack

//        {
//            "event": "charge.success",
//  "data": {
//            "id": 96113489,
//    "domain": "live",
//    "status": "success",
//    "reference": "dipl5ac1hfbs1mp",
//    "amount": 100,
//    "message": "madePayment",
//    "gateway_response": "Payment successful",
//    "paid_at": "2019-01-14T13:09:58.000Z",
//    "created_at": "2019-01-14T13:07:06.000Z",
//    "channel": "ussd",
//    "currency": "NGN",
//    "ip_address": "52.214.14.220, 162.158.38.58",
//    "metadata": "",
//    "log": null,
//    "fees": 2,
//    "fees_split": null,
//    "authorization": {
//                "authorization_code": "AUTH_3n5quq8lg7h9cqp",
//      "bin": "XXXXXX",
//      "last4": "XXXX",
//      "exp_month": null,
//      "exp_year": "2019",
//      "channel": "ussd",
//      "card_type": "offline",
//      "bank": "Guaranty Trust Bank",
//      "country_code": "NG",
//      "brand": "offline",
//      "reusable": false,
//      "signature": null
//    },
//    "customer": {
//                "id": 6047282,
//      "first_name": null,
//      "last_name": null,
//      "email": "payments@prewin.com.ng",
//      "customer_code": "CUS_1hckodi4c9z80vz",
//      "phone": null,
//      "metadata": null,
//      "risk_action": "default"
//    },
//    "plan": [
//
//            ],
//    "subaccount": [
//
//            ],
//    "paidAt": "2019-01-14T13:09:58.000Z"
//  }
//}
    }

    public function paystack_ussd_charge($email="payments@prewin.com.ng", $amount, $reference  )
    {
        $paystack_secret_key = config("paystack.SK");
//        return "Authorization : Bearer $paystack_secret_key"; // config("paystack.SK");
        $BaseEndPoint = "https://api.paystack.co/charge/";
        $CurrentEndpoint = "";
        $FullEndPoint =  $BaseEndPoint . $CurrentEndpoint;
        $PageResponse = Curl\Facades\Curl::to($FullEndPoint)
            ->withData([
                "email" => $email,
                "amount" => $amount * 100,
                "metdata" => [

                    "custom_fields" => [

                        [
                            "reference" => $reference,
//                         "display_name" => "Abuja",
//                         "value" => "Gbenga",
//                         "variable_name" => "Akinbami"
                      ]
                    ]

                ],
                "ussd" => [ "type" => "737" ]
            ] )
            ->withHeaders( array(
                "Authorization: Bearer $paystack_secret_key") )
            ->asJson()
            ->post();

        return  json_encode( $PageResponse );

//        return response()->json( ["response" => $PageResponse ], 200  ); // reply back to paystack ;
    }

    public function sendFaceBookQuickReply(Request $request, $receiver="2347038257962", $text="", $options_array )
    {
        $messagesData = [];
        $messagesData["text"] = $text;

//        Demo\n fund me \n play game\n reward\nreset password\ncheck balance

        if(count($options_array)  > 0)
        {
            foreach($options_array as $each_option)
            {
                $each_option_array["content_type"] = "text";
                $each_option_array["title"] =  ucwords($each_option);
                $each_option_array["payload"] = $each_option;
                $each_option_array["image_url"] = "https://cdn-images-1.medium.com/max/1600/1*nZ9VwHTLxAfNCuCjYAkajg.png";
                $quick_reply[] = $each_option_array;
            }

        }
        else
        {
            $quick_reply = [
                [
                    "content_type" => "text",
                    "title" => "New",
                    "payload" => "new",
                    "image_url" => "https://cdn-images-1.medium.com/max/1600/1*nZ9VwHTLxAfNCuCjYAkajg.png"
                ],
            ];

        }

        $messagesData["quick_replies"] =  $quick_reply;

        Log::info(json_encode($messagesData));


        $BaseEndPoint = "https://graph.facebook.com/v2.6/me/messages";
        $CurrentEndpoint = "";
        $FullEndPoint =  $BaseEndPoint . $CurrentEndpoint;
        $PageResponse = Curl\Facades\Curl::to($FullEndPoint)
            ->withData([
                "access_token" => $this->token,
                "messaging_type" => "RESPONSE",
                "recipient" => ["id" => $receiver ]  ,
                "message" => $messagesData,
            ] )
            ->asJson()
            ->post();

        return  $PageResponse;
    }
}