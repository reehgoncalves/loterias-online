<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $fillable = ['user_id', 'total_cents', 'currency', 'status', 'payment_status', 'provider_checkout_id', 'idempotency_key', 'raw_payload', 'paid_at'];
    protected $casts = ['raw_payload' => 'array', 'paid_at' => 'datetime'];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function items(): HasMany { return $this->hasMany(OrderItem::class); }
    public function bets(): HasMany { return $this->hasMany(Bet::class); }
    public function payments(): HasMany { return $this->hasMany(Payment::class); }
}
