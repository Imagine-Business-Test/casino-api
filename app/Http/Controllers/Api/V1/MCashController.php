<?php
namespace App\Http\Controllers\Api\V1;

use App\Models\ActivityLog;
use App\Models\PayStack;
use App\Models\Wallet;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Ixudra\Curl;

class MCashController  extends BaseController
{
    //This Controller will deal with all external calls relating to Mcash

    private $token = "EAAE3hC7zj0IBAGiHhTvUU3Q0rBDlfn9sTftdPuVbLtNDQy2E1scabwpEabrvbQW7GXYUJMUMwJ39ZC7K7QqSjwSgPbyE98B7NVlL6DjmsH4q4txenMxqglfgOSwW7xo1QScvzaMLMbov5j2ZA53VBAfphoMlxYdHkxDf4xOAZDZD";

    public function polaris_deliver_token(Request $request )
    {
        //1. generate random string
        //2. send random string to polaris endpoint

        $random_string  = $this->create_random_number(6);

        $data = [];
        $data['reference'] = $random_string;

      $response =   $this->sendReferenceToPolaris($data);

        return response()->json( ["response" => $response, 'key' => $random_string], 200  );

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


    public function polaris_receive_payment_notification(Request $request )
    {
        $request_body = $request->all();

        Log::info("================================= PayStack Wehbook Call Start ==============================================");

        Log::info(json_encode($request_body));

        Log::info("================================= PayStack Wehbook Call End  ==============================================");

        return response()->json( ["response" => true,  'data' => $request->all()], 200  );
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

    public function sendReferenceToPolaris($data)
    {
//        $reference = sha1(md5($this->phone . time()));
        $user_id = 24;

        //Set other parameters as keys in the $postdata array
        $postdata = array(
            'user_id' => $user_id,
            'unique_code' => $data['reference']
        );

        $url = "https://api.paystack.co/transaction/initialize";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postdata)); //Post Fields
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $headers = [
            'Authorization: Bearer ' . "Gbenga",
            'Content-Type: application/json',
        ];

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $request = curl_exec($ch);

        curl_close($ch);

        if ($request)
        {
            $result = json_decode($request, true);
        }
        else
        {
            $result = json_decode($request, true);
        }

        if($result['status'] )
        {
        }
        else
        {

        }

        return $result;
    }

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

    public function zenith_mcash_validate_order(Request $request, $merchantID, $orderID)
    {
        $take_all = "Validate Order ID " . json_encode($request->all()) . "|||" . json_encode($orderID);
        Log::info($take_all);
        // This a webhook that zenith bank will call in order to validate and ascertain the validity of an order id generated by us
        //  POST or GET /{merchantID}/v1/order/{orderID}/status
        //Check orderId that it exists and send back the response as below :

//        {
//            "status" : "string",
//"message" : "string",
//"amount" : 0.0
//        }

//        STATUS
//        * SUCCESSFUL - Payment for specified ID was already done.
//* PENDING - Payment can be done.
//* CANCELLED - Payment can not be done because order was cancelled.
//* INVALID_ORDER_ID - Order ID has invalid format.
//* NOT_FOUND - Specified ID was not found.
//* OTHER_ERROR - Other error, message should contain more human readable details
//about occured problem.

            try
            {
                $payments =  PayStack::where('reference',  '=' , $orderID )->orderBy('created_at', 'desc')->get();

                if(count($payments) > 0)
                {
                    $this_mcash_transaction = $payments->first();

                    if($this_mcash_transaction->status == 0)
                    {
                        $status = "PENDING";
                        $message = "The transaction ID $orderID  is valid";
                        $amount = $this_mcash_transaction->amount;

                    }
                    elseif ($this_mcash_transaction->status == 1)
                    {
                        $status = "SUCCESSFUL";
                        $message = "The transaction ID $orderID  is valid";
                        $amount = $this_mcash_transaction->amount;
                    }

                    return response()->json( ["status" => $status, "message" => $message, "amount" => $amount ], 200 );


                }
                else
                {
                    $status = "NOT_FOUND";
                    $message = "The order ID $orderID  is not valid";
                    $amount = null;

                    //Reply that  there was an error in validating this orderID
                    return response()->json(["status" => $status, "message" => $message, "amount" => $amount ], 200 );
                }
            }
            catch (\Exception $e)
            {
                $status = "OTHER_ERROR";
                $message = "An unknown or system error has occurred. Please contact ALGORITHM GAMES LTD. Thank you.";
                $amount = null;
                //Unknown or system error occurred
                return response()->json( ["status" => $status, "message" => $message, "amount" => $amount ], 200 );
            }

    }

    public function zenith_mcash_success_notification(Request $request, $merchantID, $orderID, $referenceID)
    {

        $take_all = "Validate Acquirer " . json_encode($request->all()) . "|||" . json_encode($orderID) . "|||" . json_encode($referenceID);
        Log::info($take_all);
//        return response()->json( ["status" => [$merchantID, $orderID, $referenceID] ], 200 );
        // This a webhook that zenith bank will call in order notify of  a successful transaction

        //  POST /api/{merchantID}/v1/order/{orderID}/acquire/{referenceID}
        //Check orderId that it exists and update the reference field with reference ID, then credit user and send out the appropriate message

            try
            {
                $payments =  PayStack::where('reference',  '=' , $orderID )->orderBy('created_at', 'desc')->get();
                if(count($payments) > 0)
                {
                    $this_mcash_transaction = $payments->first();

                    $this_mcash_transaction->access_code = $referenceID;
                    $this_mcash_transaction->status = 1;
                    $this_mcash_transaction->updated_at = Carbon::now();
                    $this_mcash_transaction->save();

                    // Send Notification to User on chat interface for successful

                    //Give user Notification of value given by text message and Service Worker Notification



//                    $message = "Your mCash transaction of $this_mcash_transaction->amount naira was successful. Please type 'check balance' in order to see your new balance.";
//                    // Web Notification coming soon
//                    $sms_gateway = new  SMSGatewayController();
//
//                    $sms_gateway->triggerSMS( $this_mcash_transaction->phone, $message );
//
//                    $link="http://www.estoresms.com/smsapi.php?username=Akingbenga&password=4037593m3268&sender=prewin&recipient=" .  $this_mcash_transaction->phone . "&message=" . \rawurlencode($message)."&dnd=true";
////                    return response()->json( ["status" =>  $link ], 200 );
//                    @file_get_contents($link); // Do a GET request and send SMS Verification code to the user


                    switch ( $this_mcash_transaction->registration_channel_id)
                    {
                        case 1://  Send Whatsapp Message plus SMS


                            break;
                        case 2:// Send Web Message , SMS and web notification



                            break;
                        case 3:// Send Facebook Message plus SMS



                            break;
                    }




                    $status = "SUCCESSFUL";
                    $message = "The transaction ID $orderID  is valid";
                    $amount = $this_mcash_transaction->amount;
                    return response()->json( ["status" => $status, "message" => $message, "amount" => $amount ], 204 );
                }
                else
                {
                    $status = "NOT_FOUND";
                    $message = "There was a problem with acquire";
                    $amount = null;

                    //Reply that  there was an error in validating this orderID
                    return response()->json(["status" => $status, "message" => $message, "amount" => $amount ], 422 );
                }
            }
            catch (\Exception $e)
            {
                $status = "OTHER_ERROR";
                $message = "There was a problem with acquirer.";
                $amount = null;
                //Unknown or system error occurred
                return response()->json( ["status" => $status, "message" => $message, "amount" => $amount ], 422 );
            }

    }

    public function zenith_mcash_validate_order2(Request $request, $merchantID, $orderID)
    {

        $take_all = "Validate Order ID " . json_encode($request->all()) . "|||" . json_encode($orderID);
        Log::info($take_all);
        // This a webhook that zenith bank will call in order to validate and ascertain the validity of an order id generated by us
        //  POST or GET /{merchantID}/v1/order/{orderID}/status
        //Check orderId that it exists and send back the response as below :

//        {
//            "status" : "string",
//"message" : "string",
//"amount" : 0.0
//        }

//        STATUS
//        * SUCCESSFUL - Payment for specified ID was already done.
//* PENDING - Payment can be done.
//* CANCELLED - Payment can not be done because order was cancelled.
//* INVALID_ORDER_ID - Order ID has invalid format.
//* NOT_FOUND - Specified ID was not found.
//* OTHER_ERROR - Other error, message should contain more human readable details
//about occured problem.

        $user =  User::find($orderID);

        if(!is_null($user) )
        {
            $user_phone = $user->phone;
            try
            {
                $payments =  PayStack::where('phone',  '=' , $user_phone )->orderBy('created_at', 'desc')->get();

                if(count($payments) > 0)
                {
                    $this_mcash_transaction = $payments->first();

                    if($this_mcash_transaction->status == 0)
                    {
                        $status = "PENDING";
                        $message = "The transaction ID $orderID  is valid";
                        $amount = $this_mcash_transaction->amount;

                    }
                    elseif ($this_mcash_transaction->status == 1)
                    {
                        $status = "SUCCESSFUL";
                        $message = "The transaction ID $orderID  is valid";
                        $amount = $this_mcash_transaction->amount;
                    }
                    return response()->json( ["status" => $status, "message" => $message, "amount" => $amount ], 200 );


                }
                else
                {
                    $status = "NOT_FOUND";
                    $message = "The order ID $orderID  is not valid";
                    $amount = null;

                    //Reply that  there was an error in validating this orderID
                    return response()->json(["status" => $status, "message" => $message, "amount" => $amount ], 200 );
                }
            }
            catch (\Exception $e)
            {
                $status = "OTHER_ERROR";
                $message = "An unknown or system error has occurred. Please contact ALGORITHM GAMES LTD. Thank you.";
                $amount = null;
                //Unknown or system error occurred
                return response()->json( ["status" => $status, "message" => $message, "amount" => $amount ], 200 );
            }
        }

        else
        {
            $status = "NOT_FOUND";
            $message = "The order ID $orderID  is not valid";
            $amount = null;

            //Reply that  there was an error in validating this orderID
            return response()->json(["status" => $status, "message" => $message, "amount" => $amount ], 200 );

        }

    }

    public function zenith_mcash_success_notification2(Request $request, $merchantID, $orderID, $referenceID)
    {

        $take_all = "Validate Acquirer " . json_encode($request->all()) . "|||" . json_encode($orderID) . "|||" . json_encode($referenceID);
        Log::info($take_all);
//        return response()->json( ["status" => [$merchantID, $orderID, $referenceID] ], 200 );
        // This a webhook that zenith bank will call in order notify of  a successful transaction

        //  POST /api/{merchantID}/v1/order/{orderID}/acquire/{referenceID}
        //Check orderId that it exists and update the reference field with reference ID, then credit user and send out the appropriate message

        $user =  User::find($orderID);

        if(!is_null($user) )
        {
            $user_phone = $user->phone;
            try
            {
                $payments =  PayStack::where('phone',  '=' , $user_phone )->orderBy('created_at', 'desc')->get();
                if(count($payments) > 0)
                {
                    $this_mcash_transaction = $payments->first();

                    $this_mcash_transaction->access_code = $referenceID;
                    $this_mcash_transaction->status = 1;
                    $this_mcash_transaction->updated_at = Carbon::now();
                    $this_mcash_transaction->save();

                    //Give user Value

                    $status = "SUCCESSFUL";
                    $message = "The transaction ID $orderID  is valid";
                    $amount = $this_mcash_transaction->amount;
                    return response()->json( ["status" => $status, "message" => $message, "amount" => $amount ], 204 );
                }
                else
                {
                    $status = "NOT_FOUND";
                    $message = "There was a problem with acquire";
                    $amount = null;

                    //Reply that  there was an error in validating this orderID
                    return response()->json(["status" => $status, "message" => $message, "amount" => $amount ], 422 );
                }
            }
            catch (\Exception $e)
            {
                $status = "OTHER_ERROR";
                $message = "There was a problem with acquirer.";
                $amount = null;
                //Unknown or system error occurred
                return response()->json( ["status" => $status, "message" => $message, "amount" => $amount ], 422 );
            }
        }
        else
        {
            $status = "NOT_FOUND";
            $message = "The order ID $orderID  is not valid";
            $amount = null;

            //Reply that  there was an error in validating this orderID
            return response()->json(["status" => $status, "message" => $message, "amount" => $amount ], 200 );
        }

    }

}