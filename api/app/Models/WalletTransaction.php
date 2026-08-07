<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletTransaction extends Model
{
    protected $fillable = ['wallet_id', 'user_id', 'type', 'amount_cents', 'balance_after_cents', 'status', 'reference_type', 'reference_id', 'idempotency_key', 'metadata'];
    protected $casts = ['amount_cents' => 'integer', 'balance_after_cents' => 'integer', 'metadata' => 'array'];

    public function wallet(): BelongsTo { return $this->belongsTo(Wallet::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
