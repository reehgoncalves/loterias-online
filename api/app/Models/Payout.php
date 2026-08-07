<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Payout extends Model { protected $fillable = ['user_id','bet_id','amount_cents','status','idempotency_key','approved_at','paid_at','review_note']; protected $casts = ['approved_at'=>'datetime','paid_at'=>'datetime']; }

