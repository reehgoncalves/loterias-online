<?php

namespace App\Services;

use App\Models\Payout;
use App\Models\LedgerEntry;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Models\WalletWithdrawal;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WalletService
{
    public function walletFor(User $user): Wallet
    {
        return Wallet::firstOrCreate(['user_id' => $user->id], ['currency' => 'brl', 'status' => 'active']);
    }

    public function summary(User $user): array
    {
        $wallet = $this->walletFor($user)->load([
            'transactions' => fn ($query) => $query->latest()->limit(30),
            'withdrawals' => fn ($query) => $query->latest()->limit(20),
        ]);

        return [
            'wallet' => $wallet->only(['id', 'currency', 'balance_cents', 'locked_cents', 'status']),
            'transactions' => $wallet->transactions->map(fn (WalletTransaction $transaction) => [
                'id' => $transaction->id,
                'type' => $transaction->type,
                'amount_cents' => $transaction->amount_cents,
                'balance_after_cents' => $transaction->balance_after_cents,
                'status' => $transaction->status,
                'metadata' => $transaction->metadata,
                'created_at' => $transaction->created_at,
            ])->values(),
            'withdrawals' => $wallet->withdrawals->map(fn (WalletWithdrawal $withdrawal) => [
                'id' => $withdrawal->id,
                'amount_cents' => $withdrawal->amount_cents,
                'method' => $withdrawal->method,
                'status' => $withdrawal->status,
                'review_note' => $withdrawal->review_note,
                'requested_at' => $withdrawal->requested_at,
                'processed_at' => $withdrawal->processed_at,
            ])->values(),
        ];
    }

    public function creditPrize(Payout $payout): void
    {
        if ($payout->amount_cents <= 0) return;

        DB::transaction(function () use ($payout): void {
            $wallet = $this->lockedWallet($payout->user);
            $key = 'wallet-prize-credit-'.$payout->id;
            if (WalletTransaction::where('idempotency_key', $key)->exists()) return;
            $balance = (int) $wallet->balance_cents + (int) $payout->amount_cents;
            $wallet->update(['balance_cents' => $balance]);
            WalletTransaction::create([
                'wallet_id' => $wallet->id, 'user_id' => $payout->user_id,
                'type' => 'prize_credit', 'amount_cents' => $payout->amount_cents,
                'balance_after_cents' => $balance, 'status' => 'posted',
                'reference_type' => Payout::class, 'reference_id' => $payout->id,
                'idempotency_key' => $key, 'metadata' => ['source' => 'lottery_settlement', 'payout_id' => $payout->id],
            ]);
        });
    }

    public function approvePrizeCredit(Payout $payout, User $approver, bool $simulate = false): Payout
    {
        if ($payout->status !== 'manual_review') throw ValidationException::withMessages(['status' => 'Este prêmio já foi processado.']);
        $testBypass = $simulate && app(RiskGuard::class)->isTestMode();
        if ($payout->credit_available_at && $payout->credit_available_at->isFuture() && ! $testBypass) throw ValidationException::withMessages(['status' => 'O período de conferência de 24 horas ainda não terminou.']);

        return DB::transaction(function () use ($payout, $approver): Payout {
            $payout = Payout::query()->lockForUpdate()->findOrFail($payout->id);
            if ($payout->status !== 'manual_review') throw ValidationException::withMessages(['status' => 'Este prêmio já foi processado.']);
            $reservedKey = 'payout-reserved-'.$payout->bet_id;
            if (! LedgerEntry::where('idempotency_key', $reservedKey)->exists()) {
                $payout->load('bet.draw');
                if (! $payout->bet || ! $payout->bet->draw || ! app(RiskGuard::class)->canPayout($payout->bet->draw, (int) $payout->amount_cents)) throw ValidationException::withMessages(['status' => 'A reserva elegível ainda não cobre este prêmio.']);
                LedgerEntry::create(['user_id' => $payout->user_id, 'bet_id' => $payout->bet_id, 'type' => 'payout_reserved', 'amount_cents' => $payout->amount_cents, 'status' => 'posted', 'idempotency_key' => $reservedKey, 'metadata' => ['approved_manually' => true]]);
            }
            $payout->update(['status' => 'approved', 'approved_at' => now(), 'approved_by' => $approver->id, 'review_note' => 'Crédito aprovado após conferência manual.']);
            $this->creditPrize($payout);
            return $payout->fresh();
        });
    }

    public function requestWithdrawal(User $user, int $amountCents, string $method, string $pixKey): WalletWithdrawal
    {
        if ($amountCents < 1000) throw ValidationException::withMessages(['amount_cents' => 'O saque mínimo é de R$ 10,00.']);
        if ($method !== 'pix') throw ValidationException::withMessages(['method' => 'Somente PIX está disponível para saque.']);

        return DB::transaction(function () use ($user, $amountCents, $method, $pixKey): WalletWithdrawal {
            $wallet = $this->lockedWallet($user);
            if ($wallet->status !== 'active') throw ValidationException::withMessages(['wallet' => 'A carteira está temporariamente bloqueada.']);
            if ((int) $wallet->balance_cents < $amountCents) throw ValidationException::withMessages(['amount_cents' => 'Saldo disponível insuficiente.']);

            $wallet->update(['balance_cents' => (int) $wallet->balance_cents - $amountCents, 'locked_cents' => (int) $wallet->locked_cents + $amountCents]);
            $withdrawal = WalletWithdrawal::create(['wallet_id' => $wallet->id, 'user_id' => $user->id, 'amount_cents' => $amountCents, 'method' => $method, 'pix_key' => $pixKey, 'status' => 'manual_review', 'requested_at' => now()]);
            WalletTransaction::create([
                'wallet_id' => $wallet->id, 'user_id' => $user->id, 'type' => 'withdrawal_requested',
                'amount_cents' => -$amountCents, 'balance_after_cents' => $wallet->balance_cents,
                'status' => 'posted', 'reference_type' => WalletWithdrawal::class, 'reference_id' => $withdrawal->id,
                'idempotency_key' => 'wallet-withdrawal-'.$withdrawal->id, 'metadata' => ['method' => $method],
            ]);
            return $withdrawal;
        });
    }

    public function reviewWithdrawal(WalletWithdrawal $withdrawal, string $status, ?string $note = null): WalletWithdrawal
    {
        if (! in_array($status, ['approved', 'rejected', 'paid'], true)) throw ValidationException::withMessages(['status' => 'Status de revisão inválido.']);
        if ($status === 'paid' && ! app()->environment(['local', 'testing', 'staging'])) throw ValidationException::withMessages(['status' => 'A baixa simulada só existe na homologação.']);
        if (! in_array($withdrawal->status, ['manual_review', 'approved'], true)) throw ValidationException::withMessages(['status' => 'Esta solicitação já foi processada.']);

        return DB::transaction(function () use ($withdrawal, $status, $note): WalletWithdrawal {
            $withdrawal = WalletWithdrawal::query()->lockForUpdate()->findOrFail($withdrawal->id);
            $wallet = $withdrawal->wallet()->lockForUpdate()->firstOrFail();
            if ($status === 'rejected') {
                $wallet->update(['balance_cents' => (int) $wallet->balance_cents + $withdrawal->amount_cents, 'locked_cents' => max(0, (int) $wallet->locked_cents - $withdrawal->amount_cents)]);
                WalletTransaction::create(['wallet_id' => $wallet->id, 'user_id' => $withdrawal->user_id, 'type' => 'withdrawal_released', 'amount_cents' => $withdrawal->amount_cents, 'balance_after_cents' => $wallet->balance_cents, 'status' => 'posted', 'reference_type' => WalletWithdrawal::class, 'reference_id' => $withdrawal->id, 'idempotency_key' => 'wallet-withdrawal-release-'.$withdrawal->id, 'metadata' => ['reason' => $note]]);
            }
            if ($status === 'paid') $wallet->update(['locked_cents' => max(0, (int) $wallet->locked_cents - $withdrawal->amount_cents)]);
            $withdrawal->update(['status' => $status, 'review_note' => $note, 'processed_at' => now()]);
            return $withdrawal->fresh();
        });
    }

    private function lockedWallet(User $user): Wallet
    {
        $this->walletFor($user);
        return Wallet::query()->where('user_id', $user->id)->lockForUpdate()->firstOrFail();
    }
}
