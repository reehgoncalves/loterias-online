<?php

namespace App\Console\Commands;

use App\Models\Bet;
use Illuminate\Console\Command;

class ReleasePendingPayments extends Command
{
    protected $signature = 'lottery:release-pending';
    protected $description = 'Expira reservas de apostas que não receberam pagamento confirmado.';
    public function handle(): int { $count = Bet::where('status','awaiting_payment')->where('created_at','<',now()->subMinutes(30))->update(['status'=>'expired','settlement_note'=>'Pagamento não confirmado no prazo.']); $this->info("{$count} apostas expiradas."); return self::SUCCESS; }
}

