<?php

namespace App\Api\V1\Repositories\Eloquent;

use App\Api\V1\Models\Businesses;
use App\Api\V1\Models\Chips;
use App\Api\V1\Models\ChipVault;
use App\Api\V1\Models\ExchangeStore;
use App\Api\V1\Models\ExchangeVault;
use App\Api\V1\Models\oAuthClient;
use App\Api\V1\Models\UserAuth;
use App\Api\V1\Models\UserProfile;
use App\Api\V1\Repositories\EloquentRepository;
use App\Contracts\Repository\IBusinessRepository;
use App\Utils\Mapper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BusinessEloquentRepository extends  EloquentRepository implements IBusinessRepository
{
    public $user;
    public $userProfile;
    public $business;
    public $chipVault;
    public $chips;
    public $exchangeVault;
    public function __construct(
        Businesses $business,
        UserAuth $user,
        UserProfile $userProfile,
        ChipVault $chipVault,
        ExchangeVault $exchangeVault,
        Chips $chips
    ) {
        parent::__construct();

        $this->user =  $user;
        $this->userProfile =  $userProfile;
        $this->business =  $business;
        $this->chipVault =  $chipVault;
        $this->chips =  $chips;
        $this->exchangeVault =  $exchangeVault;
    }

    public function model()
    {
        return Businesses::class;
    }



    public function register($details)
    {

        try {

            DB::beginTransaction();
            $plainPassword = $details['plain_password'];

            //create business
            $dbDataBiz = Mapper::toBusinessDB($details);
            $biz = $this->business->create($dbDataBiz);

            $details['business_id'] = $biz->id;

            //create user auth
            $dbDataAuth = Mapper::toUserDBAuth($details);
            $auth = $this->user->create($dbDataAuth);

            $details['id'] = $auth->id;

            //create user profile
            $dbDataProfile = Mapper::toUserDBProfile($details);
            $auth2 = $this->userProfile->create($dbDataProfile);

            /**
             * Get all the default chips in the system and use it to create
             * individual chip wallet/vault for this business
             */
            $allDefaultChips = $this->chips->select()->where('is_default', '=', '1')->get();
            $defaultDetail = ['details' => $allDefaultChips, 'business_id' => $biz->id];


            foreach ($allDefaultChips as $chip) {

                $chip = (object) $chip;
                /**
                 * Create all default chip vault for this business
                 * Note: Used this method of simultenous insertion ( save() ) to avoid foreign key constraints 
                 * as the next model/table (ExchangeVault) depends on this table's data
                 */
                $chipVault = new ChipVault();
                $chipVault->business_id = $biz->id;
                $chipVault->chip_id = $chip->id;
                $chipVault->chip_slug = $chip->slug;
                $chipVault->chip_value = $chip->value;
                $chipVault->save();

                //create all exchange vault for this business.
                $exchangeVault = new ExchangeVault();
                $exchangeVault->business_id = $biz->id;
                $exchangeVault->chip_id = $chip->id;
                $exchangeVault->chip_value = $chip->value;
                $exchangeVault->save();
            }


            $oauth_client = new oAuthClient();
            $oauth_client->user_id = $auth->id;
            $oauth_client->id = $auth->id;
            $oauth_client->name = $details['username'];
            $oauth_client->secret = base64_encode(hash_hmac('sha256', $plainPassword, 'secret', true));
            $oauth_client->password_client = 1;
            $oauth_client->personal_access_client = 0;
            $oauth_client->redirect = '';
            $oauth_client->revoked = 0;
            $oauth_client->save();


            DB::commit();
            //send nicer data to the user
            $data = ['user' => $auth];
            $response_message = $this->customHttpResponse(200, 'Registration successful.', $data);
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



    public function slugExist($details)
    {
        $slug = $details['business_slug'];

        $res =  DB::select(DB::raw(
            "SELECT a.* FROM businesses a
            WHERE a.slug = '{$slug}'   
         "
        ));

        return is_null($res) || empty($res) ? $res : $res[0];
    }
}
