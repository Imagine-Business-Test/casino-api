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
class BankAccounts extends BaseModel
{

}
