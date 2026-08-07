<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
class Draw extends Model {
    protected $fillable = ['lottery_game_id','contest_number','draw_at','status','results','raw_payload','result_hash','synced_at','payout_cap_cents'];
    protected $casts = ['draw_at'=>'datetime','synced_at'=>'datetime','results'=>'array','raw_payload'=>'array'];
    public function game(): BelongsTo { return $this->belongsTo(LotteryGame::class, 'lottery_game_id'); }
    public function bets(): HasMany { return $this->hasMany(Bet::class); }
}

