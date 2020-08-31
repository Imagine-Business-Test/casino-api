<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductVariety extends Model
{

    public function product()
    {
        return $this->belongsTo('App\Models\Product', 'product_id',  'id');
    }
    public function variety()
    {
        return $this->belongsTo('App\Models\Variety', 'variety_id',  'variety_id');
    }

}
