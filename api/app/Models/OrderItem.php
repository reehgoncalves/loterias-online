<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    protected $fillable = ['order_id', 'lottery_game_id', 'draw_id', 'lottery_pool_id', 'numbers', 'special_value', 'amount_cents', 'shares', 'potential_prize_cents'];
    protected $casts = ['numbers' => 'array'];

    public function order(): BelongsTo { return $this->belongsTo(Order::class); }
    public function game(): BelongsTo { return $this->belongsTo(LotteryGame::class, 'lottery_game_id'); }
    public function draw(): BelongsTo { return $this->belongsTo(Draw::class); }
    public function pool(): BelongsTo { return $this->belongsTo(LotteryPool::class, 'lottery_pool_id'); }
}
