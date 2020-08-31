<?php

namespace  App\Api\V1\Models;

use Illuminate\Database\Eloquent\Model;

class AdminProfile extends BaseModel
{
    protected $table = "admin_profile";
    // protected $fillable = ['user_id','email'];

    public function adminAuth()
    {
        return $this->belongsTo('App\Api\V1\Models\AdminAuth');
    }
}
