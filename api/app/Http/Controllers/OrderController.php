<?php

namespace App\Http\Controllers;

use App\Models\Draw;
use App\Models\LotteryGame;
use App\Models\LotteryPool;
use App\Models\Order;
use App\Models\PoolShare;
use App\Services\CouponGenerator;
use App\Services\LotteryRules;
use App\Services\PaymentService;
use App\Services\RiskGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    public function checkout(Request $request, RiskGuard $risk, CouponGenerator $generator, LotteryRules $rules, PaymentService $payments)
    {
        $data = $request->validate([
            'tickets' => 'required|array|min:1|max:20',
            'tickets.*.game_id' => 'required|integer',
            'tickets.*.draw_id' => 'required|integer',
            'tickets.*.numbers' => 'required|array',
            'tickets.*.lines' => 'nullable|array|max:10',
            'tickets.*.special_value' => 'nullable|string|max:120',
            'tickets.*.pool_id' => 'nullable|integer',
            'tickets.*.shares' => 'nullable|integer|min:1|max:100',
            'method' => 'required|in:card,pix',
            'payment_method_id' => 'nullable|string|max:100',
        ]);

        $idempotencyKey = $request->header('Idempotency-Key', 'order-'.Str::uuid());
        $existing = Order::query()->where('user_id', $request->user()->id)->where('idempotency_key', $idempotencyKey)->first();
        if ($existing) {
            $payment = $existing->payments()->latest()->first();
            $checkoutUrl = $existing->raw_payload['url'] ?? null;
            return response()->json(['data' => ['order' => $existing->load('items'), 'payment' => $payment, 'checkout_url' => $checkoutUrl, 'mode' => $checkoutUrl ? 'idempotent_replay' : 'stripe_not_configured']]);
        }

        try {
            $order = DB::transaction(function () use ($request, $data, $risk, $generator, $rules, $idempotencyKey) {
                $prepared = [];
                $total = 0;
                $batchExposureByDraw = [];

                foreach ($data['tickets'] as $index => $ticket) {
                    $pool = ! empty($ticket['pool_id'])
                        ? LotteryPool::query()->with(['game', 'draw'])->whereKey($ticket['pool_id'])->where('status', 'open')->lockForUpdate()->firstOrFail()
                        : null;
                    $game = $pool?->game ?? LotteryGame::query()->whereKey($ticket['game_id'])->where('active', DB::raw('true'))->firstOrFail();
                    $draw = $pool?->draw ?? Draw::query()->whereKey($ticket['draw_id'])->where('lottery_game_id', $game->id)->where('status', 'open')->where('draw_at', '>', now())->firstOrFail();
                    $shares = max(1, (int) ($ticket['shares'] ?? 1));
                    if ($pool && $shares > ((int) $pool->total_shares - (int) $pool->sold_shares - (int) $pool->reserved_shares)) throw ValidationException::withMessages(['pool' => 'Não há cotas suficientes disponíveis neste bolão.']);

                    $special = $rules->validateSpecial($game, $ticket['special_value'] ?? null);
                    if ($pool) {
                        // Os números do bolão vêm do cadastro do servidor. O cliente
                        // pode exibi-los, mas nunca consegue substituí-los no checkout.
                        $lines = is_array($pool->lines) && $pool->lines !== [] ? $pool->lines : [$generator->generateBatch($game, 1)[0]['numbers']];
                        if (count($lines) > 10) throw ValidationException::withMessages(['pool' => 'Um bolão pode ter no máximo 10 jogos exibidos neste recibo.']);
                        $validatedLines = array_map(fn (array $line) => $generator->validate($game, $line), $lines);
                        $amount = (int) $pool->share_price_cents * $shares;
                    } else {
                        $validatedLines = [$generator->validate($game, $ticket['numbers'])];
                        $amount = $rules->priceFor($game, $rules->numberCount($validatedLines[0], $game));
                    }

                    $draw = Draw::query()->lockForUpdate()->findOrFail($draw->id);
                    $extraExposure = $batchExposureByDraw[$draw->id] ?? 0;
                    $risk->assertCanAcceptWithExtraExposure($game, $draw, $amount, $extraExposure);
                    $batchExposureByDraw[$draw->id] = $extraExposure + $risk->potentialPrize($game, $amount);
                    $prepared[] = ['game' => $game, 'draw' => $draw, 'lines' => $validatedLines, 'special' => $special, 'amount' => $amount, 'pool' => $pool, 'shares' => $shares, 'index' => $index];
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
                    $poolShare = null;
                    if ($item['pool']) {
                        $item['pool']->increment('reserved_shares', $item['shares']);
                        $poolShare = PoolShare::create(['lottery_pool_id' => $item['pool']->id, 'user_id' => $request->user()->id, 'order_id' => $order->id, 'shares' => $item['shares'], 'amount_cents' => $item['amount'], 'status' => 'reserved']);
                    }
                    $lineCount = count($item['lines']);
                    foreach ($item['lines'] as $lineIndex => $numbers) {
                        $lineAmount = $this->splitAmount($item['amount'], $lineCount, $lineIndex);
                        $orderItem = $order->items()->create([
                            'lottery_game_id' => $item['game']->id,
                            'draw_id' => $item['draw']->id,
                            'lottery_pool_id' => $item['pool']?->id,
                            'numbers' => $numbers,
                            'special_value' => $item['special'],
                            'amount_cents' => $lineAmount,
                            'shares' => $item['shares'],
                            'potential_prize_cents' => $risk->potentialPrize($item['game'], $lineAmount),
                        ]);
                        $order->bets()->create([
                            'user_id' => $request->user()->id,
                            'lottery_game_id' => $item['game']->id,
                            'draw_id' => $item['draw']->id,
                            'numbers' => $numbers,
                            'special_value' => $item['special'],
                            'amount_cents' => $lineAmount,
                            'potential_prize_cents' => $risk->potentialPrize($item['game'], $lineAmount),
                            'idempotency_key' => 'order-'.$order->id.'-item-'.$orderItem->id,
                            'status' => 'awaiting_payment',
                            'payment_status' => 'pending',
                            'is_pool_share' => (bool) $item['pool'],
                            'pool_share_id' => $poolShare?->id,
                        ]);
                    }
                }

                return $order->load(['items.game', 'items.draw', 'items.pool', 'bets']);
            });
        } catch (ValidationException $exception) {
            $errors = $exception->errors();
            return response()->json(['message' => collect($errors)->flatten()->first() ?? 'Pedido recusado pelas regras da operação.', 'errors' => $errors], 422);
        }

        try {
            $checkout = $payments->checkoutOrder($order, $request->user(), $data['method'], $data['payment_method_id'] ?? null);
        } catch (\RuntimeException $exception) {
            $payments->cancelOrderAfterCheckoutFailure($order, $exception->getMessage());
            return response()->json(['message' => $exception->getMessage(), 'data' => ['order' => $order->fresh(['items.game', 'items.draw']), 'payment' => $order->payments()->latest()->first()]], 502);
        }
        return response()->json(['data' => ['order' => $checkout['order'] ?? $order->fresh(['items.game', 'items.draw']), 'payment' => $checkout['payment'], 'checkout_url' => $checkout['checkout_url'] ?? null, 'mode' => $checkout['mode'] ?? null, 'payment_intent_status' => $checkout['payment_intent_status'] ?? null, 'client_secret' => $checkout['client_secret'] ?? null, 'pix' => $checkout['pix'] ?? null]], 201);
    }

    private function splitAmount(int $amount, int $parts, int $index): int
    {
        $base = intdiv($amount, max(1, $parts));
        return $base + ($index < ($amount % max(1, $parts)) ? 1 : 0);
    }
}
