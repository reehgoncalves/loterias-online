<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class Payout extends Model {
    protected $fillable = ['user_id','bet_id','amount_cents','status','idempotency_key','approved_at','paid_at','review_note','credit_available_at','approved_by'];
    protected $casts = ['approved_at'=>'datetime','paid_at'=>'datetime','credit_available_at'=>'datetime'];
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function bet(): BelongsTo { return $this->belongsTo(Bet::class); }
    public function approver(): BelongsTo { return $this->belongsTo(User::class, 'approved_by'); }
}
