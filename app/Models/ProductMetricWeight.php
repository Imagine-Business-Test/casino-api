<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductMetricWeight extends Model
{

    public function metric()
    {
        return $this->belongsTo('App\Models\Metric');
    }

    public function market()
    {
        return $this->belongsTo('App\Models\Metric');
    }

    public function product()
    {
        return $this->belongsTo('App\Models\Metric');
    }
}