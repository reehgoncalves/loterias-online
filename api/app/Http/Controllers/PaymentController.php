<?php

namespace App\Http\Controllers;

use App\Models\Bet;
use App\Services\PaymentService;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function checkout(Request $request, PaymentService $payments) {
        $data = $request->validate(['bet_id'=>'required|integer','method'=>'required|in:card,pix,boleto']);
        $bet = Bet::with(['game','draw'])->where('id',$data['bet_id'])->where('user_id',$request->user()->id)->where('status','awaiting_payment')->firstOrFail();
        return response()->json(['data'=>$payments->checkout($bet,$request->user(),$data['method'])]);
    }
}

