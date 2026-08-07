<?php

namespace App\Services;

use App\Mail\BetConfirmationMail;
use App\Models\Bet;
use App\Models\LedgerEntry;
use App\Models\Payment;
use App\Models\Order;
use App\Models\PoolShare;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use RuntimeException;

class PaymentService
{
    public function checkout(Bet $bet, User $user, string $method): array
    {
        if (! in_array($method, ['card', 'pix'], true)) throw new RuntimeException('Método de pagamento não permitido. Boleto está temporariamente desativado.');
        $key = (string) env('STRIPE_SECRET_KEY');
        $payment = Payment::firstOrCreate(['idempotency_key' => 'checkout-'.$bet->id], ['user_id' => $user->id, 'bet_id' => $bet->id, 'provider' => 'stripe', 'method' => $method, 'amount_cents' => $bet->amount_cents, 'currency' => env('STRIPE_CURRENCY', 'brl'), 'status' => 'pending']);
        if ($payment->provider_checkout_id) return ['payment' => $payment, 'checkout_url' => $payment->raw_payload['url'] ?? null];
        if ($key === '') return ['payment' => $payment, 'checkout_url' => null, 'mode' => 'stripe_not_configured'];

        $params = [
            'mode' => 'payment', 'success_url' => env('STRIPE_SUCCESS_URL'), 'cancel_url' => env('STRIPE_CANCEL_URL'),
            'payment_method_types[0]' => $method, 'line_items[0][price_data][currency]' => env('STRIPE_CURRENCY', 'brl'),
            'line_items[0][price_data][product_data][name]' => $bet->game->name.' — concurso '.$bet->draw->contest_number,
            'line_items[0][price_data][unit_amount]' => $bet->amount_cents, 'line_items[0][quantity]' => 1,
            'client_reference_id' => (string) $bet->id, 'metadata[bet_id]' => (string) $bet->id, 'metadata[user_id]' => (string) $user->id,
            'payment_intent_data[metadata][bet_id]' => (string) $bet->id,
            'payment_intent_data[metadata][user_id]' => (string) $user->id,
        ];
        $response = Http::timeout((int) env('STRIPE_TIMEOUT_SECONDS', 15))->asForm()->withBasicAuth($key, '')->withHeaders(['Idempotency-Key' => 'stripe-checkout-'.$bet->id])->post('https://api.stripe.com/v1/checkout/sessions', $params);
        if (! $response->successful()) {
            $payment->update(['raw_payload' => ['stripe_error' => $response->json('error', $response->json())]]);
            throw new RuntimeException('Stripe não criou o checkout: '.$response->json('error.message', 'erro desconhecido'));
        }
        $payload = $response->json();
        if (! is_array($payload) || empty($payload['id']) || empty($payload['url'])) {
            $payment->update(['raw_payload' => ['stripe_error' => ['message' => 'checkout_incomplete', 'payload' => $payload]]]);
            throw new RuntimeException('Stripe retornou um checkout incompleto.');
        }
        $payment->update(['provider_checkout_id' => $payload['id'] ?? null, 'raw_payload' => $payload]);
        return ['payment' => $payment->fresh(), 'checkout_url' => $payload['url'] ?? null];
    }

    public function checkoutOrder(Order $order, User $user, string $method): array
    {
        if (! in_array($method, ['card', 'pix'], true)) throw new RuntimeException('Método de pagamento não permitido. Boleto está temporariamente desativado.');
        $key = (string) env('STRIPE_SECRET_KEY');
        $payment = Payment::firstOrCreate(['idempotency_key' => 'order-checkout-'.$order->id], [
            'user_id' => $user->id, 'order_id' => $order->id, 'provider' => 'stripe', 'method' => $method,
            'amount_cents' => $order->total_cents, 'currency' => env('STRIPE_CURRENCY', 'brl'), 'status' => 'pending',
        ]);
        if ($payment->provider_checkout_id) return ['order' => $order->fresh(['items.game', 'items.draw']), 'payment' => $payment, 'checkout_url' => $payment->raw_payload['url'] ?? null];
        if ($key === '') return ['order' => $order->fresh(['items.game', 'items.draw']), 'payment' => $payment, 'checkout_url' => null, 'mode' => 'stripe_not_configured'];

        $params = [
            'mode' => 'payment', 'success_url' => env('STRIPE_SUCCESS_URL'), 'cancel_url' => env('STRIPE_CANCEL_URL'),
            'payment_method_types[0]' => $method, 'client_reference_id' => 'order-'.$order->id,
            'metadata[order_id]' => (string) $order->id, 'metadata[user_id]' => (string) $user->id,
            'payment_intent_data[metadata][order_id]' => (string) $order->id,
            'payment_intent_data[metadata][user_id]' => (string) $user->id,
        ];
        foreach ($order->items()->with(['game', 'draw'])->get() as $index => $item) {
            $params["line_items[$index][price_data][currency]"] = env('STRIPE_CURRENCY', 'brl');
            $params["line_items[$index][price_data][product_data][name]"] = $item->game->name.' — concurso '.$item->draw->contest_number;
            $params["line_items[$index][price_data][product_data][description]"] = 'Cupom '.$this->formatNumbers($item->numbers);
            $params["line_items[$index][price_data][unit_amount]"] = $item->amount_cents;
            $params["line_items[$index][quantity]"] = 1;
        }
        $response = Http::timeout((int) env('STRIPE_TIMEOUT_SECONDS', 15))->asForm()->withBasicAuth($key, '')->withHeaders(['Idempotency-Key' => 'stripe-order-'.$order->id])->post('https://api.stripe.com/v1/checkout/sessions', $params);
        if (! $response->successful()) {
            $payment->update(['raw_payload' => ['stripe_error' => $response->json('error', $response->json())]]);
            throw new RuntimeException('Stripe não criou o checkout: '.$response->json('error.message', 'erro desconhecido'));
        }
        $payload = $response->json();
        if (! is_array($payload) || empty($payload['id']) || empty($payload['url'])) {
            $payment->update(['raw_payload' => ['stripe_error' => ['message' => 'checkout_incomplete', 'payload' => $payload]]]);
            throw new RuntimeException('Stripe retornou um checkout incompleto.');
        }
        $payment->update(['provider_checkout_id' => $payload['id'] ?? null, 'raw_payload' => $payload]);
        $order->update(['provider_checkout_id' => $payload['id'] ?? null, 'raw_payload' => $payload]);
        return ['order' => $order->fresh(['items.game', 'items.draw']), 'payment' => $payment->fresh(), 'checkout_url' => $payload['url'] ?? null];
    }

    private function formatNumbers(array $numbers): string
    {
        return implode(' · ', array_map(fn ($number) => str_pad((string) $number, 2, '0', STR_PAD_LEFT), $numbers));
    }

    public function confirmFromWebhook(array $payload): void
    {
        $object = $payload['data']['object'] ?? [];
        $type = (string) ($payload['type'] ?? '');
        $orderId = $object['metadata']['order_id'] ?? null;
        $betId = $object['metadata']['bet_id'] ?? $object['client_reference_id'] ?? null;
        $checkoutId = str_starts_with((string) ($object['id'] ?? ''), 'cs_') ? $object['id'] : null;
        $providerPaymentId = $object['payment_intent'] ?? (str_starts_with((string) ($object['id'] ?? ''), 'pi_') ? $object['id'] : null);
        $payment = $checkoutId ? Payment::query()->where('provider_checkout_id', $checkoutId)->first() : null;
        $payment ??= $providerPaymentId ? Payment::query()->where('provider_payment_id', $providerPaymentId)->first() : null;
        $payment ??= $orderId ? Payment::query()->where('order_id', $orderId)->latest()->first() : null;
        $payment ??= $betId ? Payment::query()->where('bet_id', $betId)->latest()->first() : null;
        if ($type === 'checkout.session.completed' && ($object['payment_status'] ?? null) !== 'paid') {
            if ($payment) $this->markProcessingFromWebhook($payment, $payload);
            return;
        }
        if (! $payment || $payment->status === 'succeeded') return;
        if (! $this->amountAndCurrencyMatch($payment, $object)) {
            $this->failFromWebhook($payment, $payload, 'amount_or_currency_mismatch');
            return;
        }
        if ($orderId && ! $payment->order_id) $payment->update(['order_id' => $orderId]);
        $confirmedBets = [];
        DB::transaction(function () use ($payment, $payload, $object, &$confirmedBets): void {
            $payment->update(['status' => 'succeeded', 'provider_payment_id' => $object['payment_intent'] ?? (str_starts_with((string) ($object['id'] ?? ''), 'pi_') ? $object['id'] : $payment->provider_payment_id), 'raw_payload' => $payload, 'paid_at' => now()]);
            $order = $payment->order()->lockForUpdate()->first();
            if ($order) {
                $order->update(['status' => 'paid', 'payment_status' => 'succeeded', 'paid_at' => now(), 'raw_payload' => $payload]);
                foreach ($order->bets()->lockForUpdate()->get() as $bet) {
                    if ($bet->payment_status === 'succeeded') continue;
                    $bet->update(['status' => 'paid', 'payment_status' => 'succeeded', 'paid_at' => now()]);
                    $confirmedBets[] = $bet->fresh(['game', 'draw', 'user']);
                }
                foreach (PoolShare::query()->where('order_id', $order->id)->where('status', 'reserved')->lockForUpdate()->get() as $share) {
                    $share->update(['status' => 'confirmed']);
                    $pool = $share->pool()->lockForUpdate()->first();
                    if ($pool) { $pool->decrement('reserved_shares', $share->shares); $pool->increment('sold_shares', $share->shares); }
                }
                LedgerEntry::firstOrCreate(['idempotency_key' => 'payment-confirmed-'.$payment->id], ['user_id' => $payment->user_id, 'payment_id' => $payment->id, 'type' => 'payment_confirmed', 'amount_cents' => $payment->amount_cents, 'status' => 'posted', 'metadata' => ['provider' => 'stripe', 'order_id' => $order->id]]);
                return;
            }
            $bet = $payment->bet()->lockForUpdate()->first();
            if (! $bet || $bet->payment_status === 'succeeded') return;
            $bet->update(['status' => 'paid', 'payment_status' => 'succeeded', 'paid_at' => now()]);
            $confirmedBets[] = $bet->fresh(['game', 'draw', 'user']);
            LedgerEntry::firstOrCreate(['idempotency_key' => 'payment-confirmed-'.$payment->id], ['user_id' => $payment->user_id, 'bet_id' => $bet->id, 'payment_id' => $payment->id, 'type' => 'payment_confirmed', 'amount_cents' => $payment->amount_cents, 'status' => 'posted', 'metadata' => ['provider' => 'stripe']]);
        });
        foreach ($confirmedBets as $confirmedBet) if ($confirmedBet?->user?->email) Mail::to($confirmedBet->user->email)->queue(new BetConfirmationMail($confirmedBet));
    }

    public function markProcessingFromWebhook(Payment $payment, array $payload): void
    {
        DB::transaction(function () use ($payment, $payload): void {
            $locked = Payment::query()->lockForUpdate()->find($payment->id);
            if (! $locked || in_array($locked->status, ['succeeded', 'failed', 'cancelled'], true)) return;
            $locked->update(['status' => 'processing', 'raw_payload' => $payload]);
            $order = $locked->order()->lockForUpdate()->first();
            if ($order) {
                $order->update(['payment_status' => 'processing']);
                foreach ($order->bets()->lockForUpdate()->get() as $bet) $bet->update(['payment_status' => 'processing']);
            }
        });
    }

    public function markProcessingFromWebhookForPayload(array $payload): void
    {
        $payment = $this->paymentFromWebhookPayload($payload);
        if ($payment) $this->markProcessingFromWebhook($payment, $payload);
    }

    public function failFromWebhook(Payment $payment, array $payload, string $reason = 'payment_failed'): void
    {
        DB::transaction(function () use ($payment, $payload, $reason): void {
            $locked = Payment::query()->lockForUpdate()->find($payment->id);
            if (! $locked || $locked->status === 'succeeded') return;
            $retryableFailure = $reason === 'payment_intent.payment_failed';
            $locked->update(['status' => 'failed', 'raw_payload' => $payload]);
            $order = $locked->order()->lockForUpdate()->first();
            if ($order) {
                $order->update(['status' => $retryableFailure ? 'awaiting_payment' : 'cancelled', 'payment_status' => 'failed', 'raw_payload' => ['stripe_event' => $payload, 'failure_reason' => $reason]]);
                foreach ($order->bets()->lockForUpdate()->get() as $bet) $bet->update(['status' => $retryableFailure ? 'awaiting_payment' : 'cancelled', 'payment_status' => 'failed']);
                if (! $retryableFailure) {
                    foreach (PoolShare::query()->where('order_id', $order->id)->where('status', 'reserved')->lockForUpdate()->get() as $share) {
                        $share->update(['status' => 'released']);
                        $pool = $share->pool()->lockForUpdate()->first();
                        if ($pool) $pool->decrement('reserved_shares', $share->shares);
                    }
                }
                return;
            }
            $bet = $locked->bet()->lockForUpdate()->first();
            if ($bet) $bet->update(['status' => 'cancelled', 'payment_status' => 'failed']);
        });
    }

    public function failFromWebhookForPayload(array $payload): void
    {
        $payment = $this->paymentFromWebhookPayload($payload);
        if ($payment) $this->failFromWebhook($payment, $payload, (string) ($payload['type'] ?? 'payment_failed'));
    }

    private function paymentFromWebhookPayload(array $payload): ?Payment
    {
        $object = $payload['data']['object'] ?? [];
        $orderId = $object['metadata']['order_id'] ?? null;
        $betId = $object['metadata']['bet_id'] ?? $object['client_reference_id'] ?? null;
        $checkoutId = str_starts_with((string) ($object['id'] ?? ''), 'cs_') ? $object['id'] : null;
        $providerPaymentId = $object['payment_intent'] ?? (str_starts_with((string) ($object['id'] ?? ''), 'pi_') ? $object['id'] : null);

        return ($checkoutId ? Payment::query()->where('provider_checkout_id', $checkoutId)->first() : null)
            ?? ($providerPaymentId ? Payment::query()->where('provider_payment_id', $providerPaymentId)->first() : null)
            ?? ($orderId ? Payment::query()->where('order_id', $orderId)->latest()->first() : null)
            ?? ($betId ? Payment::query()->where('bet_id', $betId)->latest()->first() : null);
    }

    private function amountAndCurrencyMatch(Payment $payment, array $object): bool
    {
        $amount = $object['amount_total'] ?? $object['amount_received'] ?? null;
        $currency = $object['currency'] ?? null;
        return ($amount === null || (int) $amount === (int) $payment->amount_cents)
            && ($currency === null || strtolower((string) $currency) === strtolower((string) $payment->currency));
    }
}
