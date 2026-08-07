<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class Payment extends Model { protected $fillable = ['user_id','order_id','bet_id','provider','provider_payment_id','provider_checkout_id','method','amount_cents','currency','status','raw_payload','paid_at','idempotency_key']; protected $casts = ['raw_payload'=>'array','paid_at'=>'datetime']; public function order(): BelongsTo { return $this->belongsTo(Order::class); } public function bet(): BelongsTo { return $this->belongsTo(Bet::class); } public function user(): BelongsTo { return $this->belongsTo(User::class); } }
