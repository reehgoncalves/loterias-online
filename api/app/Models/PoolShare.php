<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class PoolShare extends Model { protected $fillable = ['lottery_pool_id','user_id','shares','amount_cents','status']; }

