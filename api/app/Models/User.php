<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Authenticatable
{
    use HasApiTokens, Notifiable;
    protected $fillable = ['name', 'email', 'password', 'portal', 'cpf', 'phone', 'stripe_customer_id', 'is_admin', 'active', 'email_verified_at', 'age_confirmed_at', 'terms_accepted_at', 'terms_version', 'marketing_opt_in', 'marketing_opted_out_at'];
    protected $hidden = ['password', 'remember_token'];
    protected function casts(): array { return ['email_verified_at' => 'datetime', 'age_confirmed_at' => 'datetime', 'terms_accepted_at' => 'datetime', 'password' => 'hashed', 'is_admin' => 'boolean', 'active' => 'boolean', 'marketing_opt_in' => 'boolean', 'marketing_opted_out_at' => 'datetime']; }
    public function orders(): HasMany { return $this->hasMany(Order::class); }
}
