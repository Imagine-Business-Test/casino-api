<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;

class ApplicationParameter extends BaseModel
{
    protected $table = 'application_parameters';
    use SoftDeletes;

}
