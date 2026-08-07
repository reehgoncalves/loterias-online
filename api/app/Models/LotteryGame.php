<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
class LotteryGame extends Model {
    protected $fillable = ['slug','name','short_name','color','price_cents','price_table','selling_price_table','numbers_required','min_numbers','max_numbers','range_max','number_min','allow_repeated_numbers','selection_mode','special_options','rule_metadata','rules_source_url','rules_version','payout_rules','max_prize_cents','payout_ratio','active'];
    protected $casts = ['payout_rules'=>'array','price_table'=>'array','selling_price_table'=>'array','special_options'=>'array','rule_metadata'=>'array','active'=>'boolean','allow_repeated_numbers'=>'boolean','payout_ratio'=>'float'];
    public function draws(): HasMany { return $this->hasMany(Draw::class); }
    public function bets(): HasMany { return $this->hasMany(Bet::class); }
}
