<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
class Bet extends Model {
    protected $fillable = ['user_id','order_id','lottery_game_id','draw_id','numbers','special_value','amount_cents','potential_prize_cents','payout_cents','status','payment_status','is_pool_share','pool_share_id','idempotency_key','paid_at','settled_at','won_at','settlement_note'];
    protected $casts = ['numbers'=>'array','is_pool_share'=>'boolean','paid_at'=>'datetime','settled_at'=>'datetime','won_at'=>'datetime'];
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function order(): BelongsTo { return $this->belongsTo(Order::class); }
    public function game(): BelongsTo { return $this->belongsTo(LotteryGame::class, 'lottery_game_id'); }
    public function draw(): BelongsTo { return $this->belongsTo(Draw::class); }
    public function payout(): HasOne { return $this->hasOne(Payout::class); }
    public function poolShare(): BelongsTo { return $this->belongsTo(PoolShare::class, 'pool_share_id'); }
}
