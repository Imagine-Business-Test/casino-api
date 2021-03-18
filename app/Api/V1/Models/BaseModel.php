<?php

namespace App\Api\V1\Models;

use Illuminate\Database\Eloquent\Model;

class BaseModel extends Model
{
    protected $guarded = ['id'];
    protected $hidden = ['deleted_at', 'extra','phone'];
}
