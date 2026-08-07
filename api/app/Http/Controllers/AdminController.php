<?php

namespace App\Http\Controllers;

use App\Models\Bet;
use App\Models\LedgerEntry;
use App\Models\LotteryGame;
use App\Models\LotteryPool;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    public function dashboard() {
        $revenue = (int) Payment::where('status','succeeded')->sum('amount_cents');
        $payout = (int) Bet::whereIn('status',['won','manual_review'])->sum('payout_cents');
        $chart = collect(range(6,0))->map(function (int $days) { $date = now()->subDays($days); return ['date'=>$date->format('d/m'),'apostado'=>(int) Payment::where('status','succeeded')->whereDate('paid_at',$date)->sum('amount_cents'),'premios'=>(int) Bet::whereIn('status',['won','manual_review'])->whereDate('settled_at',$date)->sum('payout_cents')]; });
        $rows = Bet::with(['user','game'])->latest()->limit(12)->get()->map(fn (Bet $bet)=>['id'=>'#LO-'.str_pad((string)$bet->id,5,'0',STR_PAD_LEFT),'player'=>$bet->user->name,'game'=>$bet->game->name,'amount_cents'=>$bet->amount_cents,'status'=>$bet->status]);
        return response()->json(['data'=>['kpis'=>['revenue_cents'=>$revenue,'payout_cents'=>$payout,'margin_cents'=>$revenue-$payout,'active_bets'=>Bet::whereIn('status',['paid','awaiting_payment'])->count()],'chart'=>[['name'=>'Apostado','data'=>$chart->pluck('apostado')],['name'=>'Prêmios','data'=>$chart->pluck('premios')]],'bets'=>$rows,'risk'=>['ledger_cash_cents'=>(int) LedgerEntry::where('status','posted')->sum('amount_cents')]]]);
    }
    public function bets() { return response()->json(['data'=>Bet::with(['user','game','draw'])->latest()->paginate(50)]); }
    public function payments() { return response()->json(['data'=>Payment::with('user')->latest()->paginate(50)]); }
    public function pools() { return response()->json(['data'=>LotteryPool::with(['game','draw'])->latest()->paginate(50)]); }
    public function pauseGame(LotteryGame $game) { $game->update(['active'=>DB::raw('false')]); return response()->json(['data'=>$game]); }
}
