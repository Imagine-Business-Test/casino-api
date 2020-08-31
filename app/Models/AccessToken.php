<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property integer $id
 * @property integer $user_id
 * @property string $token
 * @property string $created
 * @property string $expired
 * @property string $updated
 * @property integer $status
 * @property User $user
 */
class AccessToken extends BaseModel
{
    /**
     * @var array
     */
    protected $fillable = ['user_id', 'token', 'created', 'expired', 'updated_at', 'status'];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo('App\Model\User');
    }
}
