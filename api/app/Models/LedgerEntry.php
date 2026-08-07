<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class LedgerEntry extends Model { protected $fillable = ['user_id','bet_id','payment_id','type','amount_cents','currency','status','idempotency_key','metadata']; protected $casts = ['metadata'=>'array']; }

