<?php

namespace  App\Api\V1\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Lumen\Auth\Authorizable;
use Laravel\Passport\HasApiTokens;

class AdminAuth extends BaseModel implements AuthenticatableContract, AuthorizableContract
{
    use Authenticatable, Authorizable, HasApiTokens;
    protected $table = "admin_auth";
    // protected $hidden = [
    //     'password',
    // ];

    // public function adminProfile()
    // {
    //     return $this->hasOne('App\Api\V1\Models\AdminProfile','user_id');
    // }

    public function findForPassport($username)
    {
        // Change Custom username for passport

        return $this->where('username', $username)->first();
    }
}

