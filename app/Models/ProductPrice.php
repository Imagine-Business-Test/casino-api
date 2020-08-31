<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductPrice extends Model
{

    public function metric()
    {
        return $this->belongsTo('App\Models\Metric');
    }

    public function market()
    {
        return $this->belongsTo('App\Models\Market');
    }

    public function product()
    {
        return $this->belongsTo('App\Models\Product');
    }
    public function added_by_user()
    {
        return $this->belongsTo('App\Models\User', 'added_by', 'id');
    }
}