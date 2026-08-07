<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketingCampaign extends Model
{
    protected $fillable = ['slug', 'template', 'subject', 'window', 'active', 'scheduled_at', 'sent_at'];
    protected $casts = ['active' => 'boolean', 'scheduled_at' => 'datetime', 'sent_at' => 'datetime'];
}

