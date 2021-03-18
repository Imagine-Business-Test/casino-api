<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WholesaleMetricWeight extends Model
{

    public function metric()
    {
        return $this->belongsTo('App\Models\WholesaleMetric');
    }

    public function product()
    {
        return $this->belongsTo('App\Models\Product', 'product_id',  'id');
    }
}