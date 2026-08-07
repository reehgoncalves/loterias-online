<?php

namespace App\Services;

use App\Models\Bet;
use App\Models\Draw;
use App\Models\LedgerEntry;
use App\Models\Payout;
use Illuminate\Support\Facades\DB;

class SettlementService
{
    public function __construct(private readonly RiskGuard $risk) {}

    public function settle(Draw $draw): void
    {
        DB::transaction(function () use ($draw): void {
            $draw = Draw::query()->with('game')->lockForUpdate()->findOrFail($draw->id);
            if ($draw->status === 'settled') return;
            $result = collect($draw->results['numbers'] ?? $draw->results ?? [])->map(fn ($n) => (int) $n)->all();
            if ($result === []) return;
            foreach ($draw->bets()->where('payment_status', 'succeeded')->whereIn('status', ['paid', 'manual_review'])->lockForUpdate()->get() as $bet) {
                $matches = count(array_intersect($result, array_map('intval', $bet->numbers)));
                $multiplier = (int) ($draw->game->payout_rules[(string) $matches] ?? 0);
                $payout = min($bet->amount_cents * $multiplier, $draw->payout_cap_cents ?: PHP_INT_MAX, $draw->game->max_prize_cents ?: PHP_INT_MAX);
                if ($payout <= 0) { $bet->update(['status' => 'lost', 'settled_at' => now(), 'settlement_note' => "{$matches} acertos"]); continue; }
                if (! $this->risk->canPayout($draw, $payout)) { $bet->update(['status' => 'manual_review', 'payout_cents' => $payout, 'settled_at' => now(), 'settlement_note' => 'Reserva insuficiente; revisão manual obrigatória.']); Payout::firstOrCreate(['idempotency_key' => 'payout-'.$bet->id], ['user_id' => $bet->user_id, 'bet_id' => $bet->id, 'amount_cents' => $payout, 'status' => 'manual_review', 'review_note' => 'Caixa elegível insuficiente.']); continue; }
                $bet->update(['status' => 'won', 'payout_cents' => $payout, 'won_at' => now(), 'settled_at' => now(), 'settlement_note' => "{$matches} acertos; pagamento pendente de KYC e revisão."]); 
                Payout::firstOrCreate(['idempotency_key' => 'payout-'.$bet->id], ['user_id' => $bet->user_id, 'bet_id' => $bet->id, 'amount_cents' => $payout, 'status' => 'manual_review', 'review_note' => 'Vencedor aguardando KYC e aprovação humana.']);
                LedgerEntry::firstOrCreate(['idempotency_key' => 'payout-reserved-'.$bet->id], ['user_id' => $bet->user_id, 'bet_id' => $bet->id, 'type' => 'payout_reserved', 'amount_cents' => $payout, 'status' => 'posted', 'metadata' => ['draw_id' => $draw->id]]);
            }
            $draw->update(['status' => 'settled']);
        });
    }
}

