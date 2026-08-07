<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailDelivery extends Model
{
    protected $fillable = ['marketing_campaign_id', 'user_id', 'draw_id', 'type', 'status', 'idempotency_key', 'sent_at', 'error'];
    protected $casts = ['sent_at' => 'datetime'];
}

