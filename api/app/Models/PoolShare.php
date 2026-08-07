<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class PoolShare extends Model { protected $fillable = ['lottery_pool_id','user_id','order_id','shares','amount_cents','status']; public function order(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(Order::class); } public function pool(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(LotteryPool::class, 'lottery_pool_id'); } }
