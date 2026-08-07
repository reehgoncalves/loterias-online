<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletWithdrawal extends Model
{
    protected $fillable = ['wallet_id', 'user_id', 'amount_cents', 'method', 'pix_key', 'status', 'review_note', 'provider_transfer_id', 'requested_at', 'processed_at'];
    protected $hidden = ['pix_key'];
    protected $casts = ['amount_cents' => 'integer', 'pix_key' => 'encrypted', 'requested_at' => 'datetime', 'processed_at' => 'datetime'];

    public function wallet(): BelongsTo { return $this->belongsTo(Wallet::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
