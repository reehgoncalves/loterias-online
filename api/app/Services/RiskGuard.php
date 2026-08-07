<?php

namespace App\Services;

use App\Models\Bet;
use App\Models\Draw;
use App\Models\LedgerEntry;
use App\Models\LotteryGame;
use Illuminate\Validation\ValidationException;

class RiskGuard
{
    public function isTestMode(): bool
    {
        return app()->environment(['local', 'testing', 'staging'])
            && filter_var(env('RISK_TEST_MODE', false), FILTER_VALIDATE_BOOL);
    }

    public function testCreditCents(): int
    {
        return $this->isTestMode() ? max(0, (int) env('RISK_TEST_CREDIT_CENTS', 0)) : 0;
    }

    public function potentialPrize(LotteryGame $game, int $amountCents): int
    {
        $maxMultiplier = max(array_map('intval', $game->payout_rules ?: [1]));
        $potential = $amountCents * max(1, $maxMultiplier);
        return $game->max_prize_cents > 0 ? min($potential, $game->max_prize_cents) : $potential;
    }

    public function assertCanAccept(LotteryGame $game, Draw $draw, int $amountCents): void
    {
        $this->assertCanAcceptWithExtraExposure($game, $draw, $amountCents, 0);
    }

    public function assertCanAcceptWithExtraExposure(LotteryGame $game, Draw $draw, int $amountCents, int $extraExposureCents): void
    {
        if ($amountCents <= 0 || $amountCents > (int) config('lottery.risk.max_bet_cents', env('RISK_MAX_BET_CENTS', 100000))) {
            throw ValidationException::withMessages(['amount' => 'Valor de aposta fora do limite configurado.']);
        }

        $eligibleCash = $this->eligibleCash();
        $currentExposure = (int) Bet::query()->where('draw_id', $draw->id)->whereIn('status', ['awaiting_payment', 'paid', 'manual_review'])->sum('potential_prize_cents');
        $newExposure = $currentExposure + max(0, $extraExposureCents) + $this->potentialPrize($game, $amountCents);
        $minReserve = $this->isTestMode() ? 0 : (int) env('RISK_MIN_RESERVE_CENTS', 100000);
        $ratio = $this->isTestMode() ? 1 : min(0.70, max(0, (float) env('RISK_PAYOUT_RATIO', 0.70)));
        $safety = $this->isTestMode() ? 1 : min(1, max(0, (float) env('RISK_SAFETY_RATIO', 0.80)));
        $limit = max(0, (int) floor(max(0, $eligibleCash - $minReserve) * $ratio * $safety));

        if ($newExposure > $limit) {
            throw ValidationException::withMessages(['risk' => 'Aposta temporariamente indisponível: a reserva elegível não cobre a exposição máxima deste concurso.']);
        }
    }

    public function canPayout(Draw $draw, int $amountCents): bool
    {
        $alreadyReleased = (int) LedgerEntry::query()->whereIn('type', ['payout_reserved', 'payout_sent'])->where('status', 'posted')->sum('amount_cents');
        return $amountCents + $alreadyReleased <= $this->eligibleCash();
    }

    public function eligibleCash(): int
    {
        return max(0, $this->testCreditCents() + (int) LedgerEntry::query()->where('status', 'posted')->whereIn('type', ['payment_confirmed', 'refund_reversed'])->sum('amount_cents')
            - (int) LedgerEntry::query()->where('status', 'posted')->whereIn('type', ['payout_reserved', 'payout_sent', 'refund_sent', 'chargeback'])->sum('amount_cents'));
    }
}
