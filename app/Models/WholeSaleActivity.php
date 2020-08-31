<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WholesaleActivity extends Model
{

    public function market()
    {
        return $this->belongsTo('App\Models\Market');
    }

    public function product()
    {
        return $this->belongsTo('App\Models\Product');
    }
}