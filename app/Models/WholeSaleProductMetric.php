<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WholesaleProductMetric extends Model
{

    public function metric()
    {
        return $this->belongsTo('App\Models\WholesaleMetric');
    }

    public function market()
    {
        return $this->belongsTo('App\Models\Market');
    }

    public function product()
    {
        return $this->belongsTo('App\Models\Product', 'product_id',  'id');
    }
}