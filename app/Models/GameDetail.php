<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;

class GameDetail extends BaseModel
{
    protected $table = 'game_details';
    use SoftDeletes;

}
