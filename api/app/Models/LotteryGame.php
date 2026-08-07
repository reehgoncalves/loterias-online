<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
class LotteryGame extends Model {
    protected $fillable = ['slug','name','short_name','color','price_cents','numbers_required','range_max','number_min','allow_repeated_numbers','selection_mode','special_options','payout_rules','max_prize_cents','payout_ratio','active'];
    protected $casts = ['payout_rules'=>'array','special_options'=>'array','active'=>'boolean','allow_repeated_numbers'=>'boolean','payout_ratio'=>'float'];
    public function draws(): HasMany { return $this->hasMany(Draw::class); }
    public function bets(): HasMany { return $this->hasMany(Bet::class); }
}
