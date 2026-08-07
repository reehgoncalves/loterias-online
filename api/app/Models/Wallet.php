<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Wallet extends Model
{
    protected $fillable = ['user_id', 'currency', 'balance_cents', 'locked_cents', 'status'];
    protected $casts = ['balance_cents' => 'integer', 'locked_cents' => 'integer'];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function transactions(): HasMany { return $this->hasMany(WalletTransaction::class); }
    public function withdrawals(): HasMany { return $this->hasMany(WalletWithdrawal::class); }
}
