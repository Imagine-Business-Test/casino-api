<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class oAuthClient extends Model
{
    use SoftDeletes;
    protected $table = 'oauth_clients';

}
