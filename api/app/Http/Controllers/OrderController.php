<?php

namespace App\Http\Controllers;

use App\Models\Draw;
use App\Models\LotteryGame;
use App\Models\Order;
use App\Models\LotteryPool;
use App\Models\PoolShare;
use App\Services\CouponGenerator;
use App\Services\PaymentService;
use App\Services\RiskGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    public function checkout(Request $request, RiskGuard $risk, CouponGenerator $generator, PaymentService $payments)
    {
        $data = $request->validate([
            'tickets' => 'required|array|min:1|max:20',
            'tickets.*.game_id' => 'required|integer',
            'tickets.*.draw_id' => 'required|integer',
            'tickets.*.numbers' => 'required|array',
            'tickets.*.pool_id' => 'nullable|integer',
            'tickets.*.shares' => 'nullable|integer|min:1|max:20',
            'method' => 'required|in:card,pix',
        ]);

        $idempotencyKey = $request->header('Idempotency-Key', 'order-'.Str::uuid());
        $existing = Order::query()->where('user_id', $request->user()->id)->where('idempotency_key', $idempotencyKey)->first();
        if ($existing) {
            $payment = $existing->payments()->latest()->first();
            $checkoutUrl = $existing->raw_payload['url'] ?? null;
            return response()->json(['data' => ['order' => $existing->load('items'), 'payment' => $payment, 'checkout_url' => $checkoutUrl, 'mode' => $checkoutUrl ? 'idempotent_replay' : 'stripe_not_configured']]);
        }

        try {
            $order = DB::transaction(function () use ($request, $data, $risk, $generator, $idempotencyKey) {
            $prepared = [];
            $total = 0;
            $batchExposureByDraw = [];

            foreach ($data['tickets'] as $index => $ticket) {
                $pool = ! empty($ticket['pool_id']) ? LotteryPool::query()->with(['game', 'draw'])->whereKey($ticket['pool_id'])->where('status', 'open')->lockForUpdate()->firstOrFail() : null;
                $game = $pool?->game ?? LotteryGame::query()->whereKey($ticket['game_id'])->where('active', true)->firstOrFail();
                $draw = $pool?->draw ?? Draw::query()->whereKey($ticket['draw_id'])->where('lottery_game_id', $game->id)->where('status', 'open')->where('draw_at', '>', now())->firstOrFail();
                $shares = max(1, (int) ($ticket['shares'] ?? 1));
                if ($pool && $shares > ((int) $pool->total_shares - (int) $pool->sold_shares - (int) $pool->reserved_shares)) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['pool' => 'Não há cotas suficientes disponíveis neste bolão.']);
                }
                $numbers = $pool ? $generator->generateBatch($game, 1)[0]['numbers'] : $generator->validate($game, $ticket['numbers']);

                // Lock each draw before evaluating exposure so simultaneous checkouts cannot oversubscribe it.
                $draw = Draw::query()->lockForUpdate()->findOrFail($draw->id);
                $amount = $pool ? $pool->share_price_cents * $shares : $game->price_cents;
                $extraExposure = $batchExposureByDraw[$draw->id] ?? 0;
                $risk->assertCanAcceptWithExtraExposure($game, $draw, $amount, $extraExposure);
                $batchExposureByDraw[$draw->id] = $extraExposure + $risk->potentialPrize($game, $amount);
                $prepared[] = compact('game', 'draw', 'numbers', 'amount', 'index');
                $prepared[array_key_last($prepared)]['pool'] = $pool;
                $prepared[array_key_last($prepared)]['shares'] = $shares;
                $total += $amount;
            }

            $order = Order::create([
                'user_id' => $request->user()->id,
                'total_cents' => $total,
                'currency' => env('STRIPE_CURRENCY', 'brl'),
                'status' => 'awaiting_payment',
                'payment_status' => 'pending',
                'idempotency_key' => $idempotencyKey,
            ]);

            foreach ($prepared as $item) {
                $orderItem = $order->items()->create([
                    'lottery_game_id' => $item['game']->id,
                    'draw_id' => $item['draw']->id,
                    'lottery_pool_id' => $item['pool']?->id,
                    'numbers' => $item['numbers'],
                    'amount_cents' => $item['amount'],
                    'shares' => $item['shares'],
                    'potential_prize_cents' => $risk->potentialPrize($item['game'], $item['amount']),
                ]);
                $order->bets()->create([
                    'user_id' => $request->user()->id,
                    'lottery_game_id' => $item['game']->id,
                    'draw_id' => $item['draw']->id,
                    'numbers' => $item['numbers'],
                    'amount_cents' => $item['amount'],
                    'potential_prize_cents' => $risk->potentialPrize($item['game'], $item['amount']),
                    'idempotency_key' => 'order-'.$order->id.'-item-'.$orderItem->id,
                    'status' => 'awaiting_payment',
                    'payment_status' => 'pending',
                    'is_pool_share' => (bool) $item['pool'],
                ]);
                if ($item['pool']) {
                    $item['pool']->increment('reserved_shares', $item['shares']);
                    PoolShare::create(['lottery_pool_id' => $item['pool']->id, 'user_id' => $request->user()->id, 'order_id' => $order->id, 'shares' => $item['shares'], 'amount_cents' => $item['amount'], 'status' => 'reserved']);
                }
            }

                return $order->load(['items.game', 'items.draw', 'bets']);
            });
        } catch (ValidationException $exception) {
            $errors = $exception->errors();
            return response()->json(['message' => collect($errors)->flatten()->first() ?? 'Pedido recusado pelas regras da operação.', 'errors' => $errors], 422);
        }

        $checkout = $payments->checkoutOrder($order, $request->user(), $data['method']);
        return response()->json(['data' => ['order' => $checkout['order'] ?? $order->fresh(['items.game', 'items.draw']), 'payment' => $checkout['payment'], 'checkout_url' => $checkout['checkout_url'] ?? null, 'mode' => $checkout['mode'] ?? null]], 201);
    }
}
