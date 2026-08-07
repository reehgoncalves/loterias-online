<?php

namespace App\Http\Controllers;

use App\Models\Bet;
use App\Models\LedgerEntry;
use App\Models\LotteryGame;
use App\Models\LotteryPool;
use App\Models\Payment;
use App\Models\Payout;
use App\Models\Wallet;
use App\Models\WalletWithdrawal;
use App\Services\RiskGuard;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    public function dashboard(RiskGuard $risk) {
        $revenue = (int) Payment::where('status','succeeded')->sum('amount_cents');
        $payout = (int) Bet::whereIn('status',['won','manual_review'])->sum('payout_cents');
        $pendingPayments = (int) Payment::whereIn('status', ['pending', 'processing'])->sum('amount_cents');
        $walletLiability = (int) Wallet::sum('balance_cents') + (int) Wallet::sum('locked_cents');
        $pendingWithdrawals = (int) WalletWithdrawal::whereIn('status', ['manual_review', 'approved'])->sum('amount_cents');
        $chart = collect(range(6,0))->map(function (int $days) { $date = now()->subDays($days); return ['date'=>$date->format('d/m'),'apostado'=>(int) Payment::where('status','succeeded')->whereDate('paid_at',$date)->sum('amount_cents'),'premios'=>(int) Bet::whereIn('status',['won','manual_review'])->whereDate('settled_at',$date)->sum('payout_cents')]; });
        $rows = Bet::with(['user','game'])->latest()->limit(12)->get()->map(fn (Bet $bet)=>['id'=>'#LO-'.str_pad((string)$bet->id,5,'0',STR_PAD_LEFT),'player'=>$bet->user->name,'game'=>$bet->game->name,'amount_cents'=>$bet->amount_cents,'status'=>$bet->status]);
        return response()->json(['data' => [
            'kpis' => [
                'revenue_cents' => $revenue,
                'payout_cents' => $payout,
                'margin_cents' => $revenue - $payout,
                'active_bets' => Bet::whereIn('status', ['paid', 'awaiting_payment'])->count(),
            ],
            'chart' => [
                ['name' => 'Apostado', 'data' => $chart->pluck('apostado')],
                ['name' => 'Prêmios', 'data' => $chart->pluck('premios')],
            ],
            'bets' => $rows,
            'finance' => [
                'gross_revenue_cents' => $revenue,
                'payouts_cents' => $payout,
                'net_margin_cents' => $revenue - $payout,
                'pending_payments_cents' => $pendingPayments,
                'wallet_liability_cents' => $walletLiability,
                'pending_withdrawals_cents' => $pendingWithdrawals,
            ],
            'risk' => [
                'ledger_cash_cents' => $risk->eligibleCash(),
                'eligible_cash_cents' => $risk->eligibleCash(),
                'test_credit_cents' => $risk->testCreditCents(),
                'test_mode' => $risk->isTestMode(),
                'min_reserve_cents' => (int) env('RISK_MIN_RESERVE_CENTS', 100000),
            ],
        ]]);
    }
    public function bets() { return response()->json(['data'=>Bet::with(['user','game','draw'])->latest()->paginate(50)]); }
    public function payments() { return response()->json(['data'=>Payment::with('user')->latest()->paginate(50)]); }
    public function pools() { return response()->json(['data'=>LotteryPool::with(['game','draw'])->latest()->paginate(50)]); }
    public function walletWithdrawals() { return response()->json(['data'=>WalletWithdrawal::with(['user','wallet'])->latest()->paginate(50)]); }
    public function reviewWithdrawal(WalletWithdrawal $withdrawal, Request $request, WalletService $wallets) { $data = $request->validate(['status'=>'required|in:approved,rejected,paid','note'=>'nullable|string|max:500']); return response()->json(['data'=>$wallets->reviewWithdrawal($withdrawal, $data['status'], $data['note'] ?? null)]); }
    public function payouts() { return response()->json(['data'=>Payout::with(['user','bet.game','bet.draw'])->whereIn('status', ['manual_review','approved'])->latest()->paginate(50)]); }
    public function approvePayout(Payout $payout, Request $request, WalletService $wallets) { $data = $request->validate(['simulate'=>'nullable|boolean']); return response()->json(['data'=>$wallets->approvePrizeCredit($payout, $request->user(), (bool) ($data['simulate'] ?? false))]); }
    public function pauseGame(LotteryGame $game) { $game->update(['active'=>DB::raw('false')]); return response()->json(['data'=>$game]); }
}
