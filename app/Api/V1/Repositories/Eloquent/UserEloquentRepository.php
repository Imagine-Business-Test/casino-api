<?php

namespace App\Api\V1\Repositories\Eloquent;

use App\Api\V1\Models\oAuthClient;
use App\Api\V1\Models\UserAuth;
use App\Api\V1\Models\UserProfile;
use App\Contracts\Repository\IUserRepository;
use App\Api\V1\Repositories\EloquentRepository;
use App\Utils\Mapper;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UserEloquentRepository extends  EloquentRepository implements IUserRepository
{
    public $user;
    public function __construct(UserAuth $user, UserProfile $userProfile)
    {
        parent::__construct();
        $this->user =  $user;
        $this->userProfile =  $userProfile;
    }

    public function model()
    {
        return UserProfile::class;
    }

    public function filterOne(string $id): Model
    {
        $stmt =  $this->model->from('user_auth as a')
            ->select(
                'a.id',
                'a.username',
                'b.surname',
                'b.firstname',
                DB::raw("CASE WHEN a.suspended_at IS NOT NULL THEN 1 ELSE '0' END as is_suspended"),
                DB::raw("CASE WHEN a.disabled_at IS NOT NULL THEN 1 ELSE '0' END as is_disabled"),
                'b.phone',
                'b.email',
                'r.role as role_name',
                'r.stub as role_slug',
                'b.avatar',
                'a.last_login'
            )
            ->leftJoin('user_profile as b', 'a.id', 'b.id')
            ->leftJoin('user_role as r', 'a.role', 'r.id')
            ->where('a.id', '=', $id)
            // ->whereNull('a.disabled_at')
            // ->whereNull('a.suspended_at')
            ->whereNull('a.deleted_at')
            ->first();
        return $stmt;
    }

    public function filterAll(): Collection
    {
        $stmt =  $this->model->from('user_auth as a')
            ->select(
                'a.id',
                'a.username',
                'b.surname',
                'b.firstname',
                DB::raw("CASE WHEN a.suspended_at IS NOT NULL THEN 1 ELSE '0' END as is_suspended"),
                DB::raw("CASE WHEN a.disabled_at IS NOT NULL THEN 1 ELSE '0' END as is_disabled"),
                'b.phone',
                'b.email',
                'r.role as role_name',
                'r.stub as role_slug',
                'b.avatar',
                'a.last_login'
            )
            ->leftJoin('user_profile as b', 'a.id', 'b.id')
            ->leftJoin('user_role as r', 'a.role', 'r.id')
            // ->whereNull('a.disabled_at')
            // ->whereNull('a.suspended_at')
            ->whereNull('a.deleted_at')
            // ->orderBy('is_disabled', 'desc')
            // ->orderBy('is_suspended', 'desc')
            ->get();
        return $stmt;
    }

    public function showByUsername(string $username)
    {
        $res = $this->user->from('user_auth as a')
            ->select('a.id', 'a.username', 'a.password', 'b.surname', 'b.firstname', 'b.phone', 'b.email', 'b.avatar', 'c.stub as role')
            ->leftJoin('user_profile as b', 'a.id', 'b.id')
            ->leftJoin('user_role as c', 'a.role', 'c.id')
            ->where("a.username", '=', $username)
            ->get();

        return $res;
    }

    public function register($details)
    {

        try {

            DB::beginTransaction();
            $plainPassword = $details['plain_password'];

            $dbDataAuth = Mapper::toUserDBAuth($details);
            $auth = $this->user->create($dbDataAuth);

            $details['id'] = $auth->id;

            $dbDataProfile = Mapper::toUserDBProfile($details);
            $auth2 = $this->userProfile->create($dbDataProfile);


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


    public function nameByEmailExist($details)
    {

        $firstname = $details['firstname'];
        $lastname = $details['surname'];
        $email = $details['email'];

        $res =  DB::select(DB::raw(
            "SELECT a.* FROM user_profile a
            WHERE (a.firstname = '{$firstname}' and a.surname = '{$lastname}' OR
                  a.firstname ='{$lastname}' and a.surname = '{$firstname}') AND
                  a.email = '{$email}'   
         "
        ));

        return is_null($res) || empty($res) ? $res : $res[0];
    }

    public function nameByUsernameExist($details)
    {
        $username = $details['username'];

        $res =  DB::select(DB::raw(
            "SELECT a.* FROM user_auth a
            WHERE a.username = '{$username}'   
         "
        ));

        return is_null($res) || empty($res) ? $res : $res[0];
    }

    public function emailExist($details)
    {
        $email = $details['email'];

        $res =  DB::select(DB::raw(
            "SELECT a.* FROM user_profile a
            WHERE a.email = '{$email}'   
         "
        ));

        return is_null($res) || empty($res) ? $res : $res[0];
    }

    public function updateLastLogin($userId)
    {
        $updateInfo = UserAuth::where('id', $userId)
            ->update([
                'last_login' => Carbon::now()
            ]);
    }

    public function disable($userId)
    {
        $updateInfo = UserAuth::where('id', $userId)
            ->update([
                'disabled_at' => Carbon::now()
            ]);
    }

    public function enable($userId)
    {
        $updateInfo = UserAuth::where('id', $userId)
            ->update([
                'disabled_at' => null
            ]);
    }

    public function suspend($userId)
    {
        $updateInfo = UserAuth::where('id', $userId)
            ->update([
                'suspended_at' => Carbon::now()
            ]);
    }

    public function unsuspend($userId)
    {
        $updateInfo = UserAuth::where('id', $userId)
            ->update([
                'suspended_at' => null
            ]);
    }

    public function updatePassportSecret($userId, $secret)
    {
        DB::statement("UPDATE oauth_clients SET secret = '{$secret}' where user_id = {$userId}");
    }
}
