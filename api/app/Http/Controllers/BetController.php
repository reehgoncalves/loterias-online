<?php

namespace App\Http\Controllers;

use App\Models\Bet;
use App\Models\Draw;
use App\Models\LotteryGame;
use App\Services\CouponGenerator;
use App\Services\RiskGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BetController extends Controller
{
    public function generateCoupons(Request $request, CouponGenerator $generator) {
        $data = $request->validate(['game_id' => 'required|integer', 'quantity' => 'nullable|integer|min:1|max:50']);
        $game = LotteryGame::query()->whereKey($data['game_id'])->where('active', true)->firstOrFail();
        return response()->json(['data' => $generator->generateBatch($game, (int) ($data['quantity'] ?? 1)), 'meta' => ['game' => $game->only(['id', 'name', 'slug', 'numbers_required', 'number_min', 'range_max', 'selection_mode'])]]);
    }

    public function store(Request $request, RiskGuard $risk, CouponGenerator $generator) {
        $data = $request->validate(['game_id'=>'required|integer','draw_id'=>'required|integer','numbers'=>'required|array']);
        $game = LotteryGame::query()->whereKey($data['game_id'])->where('active',true)->firstOrFail();
        $draw = Draw::query()->whereKey($data['draw_id'])->where('lottery_game_id',$game->id)->where('status','open')->where('draw_at','>',now())->first();
        if (! $draw) { $draw = Draw::query()->where('contest_number',$data['draw_id'])->where('lottery_game_id',$game->id)->where('status','open')->where('draw_at','>',now())->firstOrFail(); }
        $numbers = $generator->validate($game, $data['numbers']);
        return DB::transaction(function () use ($request,$risk,$game,$draw,$numbers) {
            $draw = Draw::query()->lockForUpdate()->findOrFail($draw->id);
            $amount = $game->price_cents;
            $risk->assertCanAccept($game,$draw,$amount);
            $bet = Bet::create(['user_id'=>$request->user()->id,'lottery_game_id'=>$game->id,'draw_id'=>$draw->id,'numbers'=>$numbers,'amount_cents'=>$amount,'potential_prize_cents'=>$risk->potentialPrize($game,$amount),'idempotency_key'=>$request->header('Idempotency-Key', (string) Str::uuid()),'status'=>'awaiting_payment','payment_status'=>'pending']);
            return response()->json(['data'=>$bet->load('game','draw')],201);
        });
    }
    public function mine(Request $request) { return response()->json(['data'=>Bet::with('game','draw')->where('user_id',$request->user()->id)->latest()->paginate(30)]); }
}
