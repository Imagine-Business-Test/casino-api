<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property integer $id
 * @property integer $banks_id
 * @property integer $registrar_id
 * @property integer $user_id
 * @property string $name
 * @property string $address
 * @property string $phone
 * @property string $created
 * @property string $updated
 * @property integer $status
 */
class ProductMetric extends Model
{

    public function metric()
    {
        return $this->belongsTo('App\Models\Metric');
    }

    public function product()
    {
        return $this->belongsTo('App\Models\Product');
    }
}
