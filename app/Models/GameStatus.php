<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class GameStatus extends Model
{
    use SoftDeletes;
  protected $table = 'game_status';
  protected $guarded = [];
}