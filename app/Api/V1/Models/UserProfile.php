<?php

namespace  App\Api\V1\Models;

use Illuminate\Database\Eloquent\Model;

class UserProfile extends BaseModel
{
    protected $table = "user_profile";
    protected $fillable = ['id', 'surname', 'firstname', 'email', 'phone'];

    public function UserAuth()
    {
        return $this->belongsTo('App\Api\V1\Models\UserAuth');
    }
}
