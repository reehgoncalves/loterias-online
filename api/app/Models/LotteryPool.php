<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
class LotteryPool extends Model {
    protected $fillable = ['lottery_game_id','draw_id','name','description','share_price_cents','total_shares','sold_shares','reserved_shares','total_stake_cents','status'];
    public function game(): BelongsTo { return $this->belongsTo(LotteryGame::class, 'lottery_game_id'); }
    public function draw(): BelongsTo { return $this->belongsTo(Draw::class); }
    public function shares(): HasMany { return $this->hasMany(PoolShare::class); }
}
