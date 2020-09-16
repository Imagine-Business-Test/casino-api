<?php


namespace App\Api\V1\Controllers;


use App\Api\V1\Models\User;
use App\Contracts\Repository\IBusinessRepository;
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


class BusinessController extends BaseController
{

    private $userRepo;
    private $businessRepo;

    public function __construct(IUserRepository $userRepo, IBusinessRepository $businessRepo)
    {
        $this->userRepo = $userRepo;
        $this->businessRepo = $businessRepo;
    }



    public function findAll()
    {
        $result = $this->businessRepo->findAll();
        return $result;
    }


    public function find($id)
    {
        $result = $this->businessRepo->find($id);
        return $result;
    }

    public function register(Request $request)
    {

        $validator = Validator::make(
            $request->input(),
            [
                'name' => 'required',
                'email' => 'required'
            ]
        );

        $user = $request->user('api');

        if ($validator->fails()) {

            //send nicer error to the user
            $response_message = $this->customHttpResponse(401, 'Incorrect Details. All fields are required.');
            return response()->json($response_message);
        }


        try {


            $detail = $request->input();

            //generate the on-the-fly admin creation details
            $detail['role'] = 6; //where 6 = admin
            $password = Shortid::generate(10, "0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ@&");

            Log::info("auto-gen password => " . $password);

            $detail['username'] = $request->get('email');
            $detail['password'] = Hash::make($password);
            $detail['plain_password'] = $password;



            //check email exist
            $check = $this->userRepo->emailExist($detail);

            if (!is_null($check) && !empty($check)) {

                $response_message = $this->customHttpResponse(405, 'User with this email already exist.');
                return response()->json($response_message);
            }


            //check unique business name exist
            if ($request->has("business_slug")) {
                $checkBusiness = $this->businessRepo->slugExist($detail);
                Log::info("checking " . json_encode($checkBusiness));
                if (!is_null($checkBusiness) && !empty($checkBusiness)) {
                    Log::info("checking 2 " . json_encode($checkBusiness));
                    $response_message = $this->customHttpResponse(405, 'Business with this name already exist.');
                    return response()->json($response_message);
                }
            }



            $q1 = $this->businessRepo->register($detail);
            $resonse = json_decode($q1->getContent());

            if ($resonse->status_code === 200) {
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
                    // $sms_gateway = new  SMSGatewayController();
                    // $sms_gateway->triggerSMS( $user->phone, "SMS Verification Code: ". $sms_code  );


                } catch (\Exception $e) {
                    // Log::info("Error in SMS Code send via Email for $full_name , phone number , email =====> ".     $user->email . " $user->phone ====>  " . $e->getMessage()    );
                }

                $response_message = $this->customHttpResponse(200, "Entity added successfully Although email was sent as it's disabled.");
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
}
