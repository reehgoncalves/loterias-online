<?php

namespace App\Jobs;

use App\Models\Draw;
use App\Services\SettlementService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Bus\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SettleDrawBets implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public function __construct(public int $drawId) {}
    public function handle(SettlementService $settlement): void { $draw = Draw::find($this->drawId); if ($draw) $settlement->settle($draw); }
}

