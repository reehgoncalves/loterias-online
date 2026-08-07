<?php

namespace App\Http\Controllers;

use App\Services\WalletService;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    public function show(Request $request, WalletService $wallets)
    {
        return response()->json(['data' => $wallets->summary($request->user())]);
    }

    public function withdraw(Request $request, WalletService $wallets)
    {
        $data = $request->validate(['amount_cents' => 'required|integer|min:1000|max:50000000', 'method' => 'required|in:pix', 'pix_key' => 'required|string|min:3|max:255']);
        $withdrawal = $wallets->requestWithdrawal($request->user(), (int) $data['amount_cents'], $data['method'], $data['pix_key']);
        return response()->json(['data' => ['id' => $withdrawal->id, 'amount_cents' => $withdrawal->amount_cents, 'status' => $withdrawal->status, 'requested_at' => $withdrawal->requested_at]], 201);
    }
}
