<?php

namespace App\Models;

use Illuminate\Auth\Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;

class User extends BaseModel implements AuthenticatableContract  /*, AuthorizableContract*/
{

    use  SoftDeletes, Authenticatable ;


    // Soft delete and user authentication


    // When querying the user, do not expose the password
    protected $hidden = ['password', 'deleted_at', 'encrypted_password'];

    public function posts()
    {
        return $this->hasMany(Post::class);
    }

    // jwt need to implement the method
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    // jwt need to implement the method
    public function getJWTCustomClaims()
    {
        return [];
    }
}
