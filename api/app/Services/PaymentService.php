<?php

namespace App\Services;

use App\Models\Bet;
use App\Models\LedgerEntry;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class PaymentService
{
    public function checkout(Bet $bet, User $user, string $method): array
    {
        if (! in_array($method, ['card', 'pix', 'boleto'], true)) throw new RuntimeException('Método de pagamento não permitido.');
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
        ];
        $response = Http::asForm()->withBasicAuth($key, '')->withHeaders(['Idempotency-Key' => 'stripe-checkout-'.$bet->id])->post('https://api.stripe.com/v1/checkout/sessions', $params);
        if (! $response->successful()) throw new RuntimeException('Stripe não criou o checkout: '.$response->json('error.message', 'erro desconhecido'));
        $payload = $response->json();
        $payment->update(['provider_checkout_id' => $payload['id'] ?? null, 'raw_payload' => $payload]);
        return ['payment' => $payment->fresh(), 'checkout_url' => $payload['url'] ?? null];
    }

    public function confirmFromWebhook(array $payload): void
    {
        $object = $payload['data']['object'] ?? [];
        $betId = $object['metadata']['bet_id'] ?? $object['client_reference_id'] ?? null;
        $checkoutId = $object['id'] ?? null;
        $payment = $checkoutId ? Payment::query()->where('provider_checkout_id', $checkoutId)->first() : null;
        $payment ??= $betId ? Payment::query()->where('bet_id', $betId)->latest()->first() : null;
        $payment ??= $checkoutId ? Payment::query()->where('provider_payment_id', $checkoutId)->first() : null;
        if (! $payment || $payment->status === 'succeeded') return;
        DB::transaction(function () use ($payment, $payload, $object): void {
            $payment->update(['status' => 'succeeded', 'provider_payment_id' => $object['payment_intent'] ?? $object['id'] ?? $payment->provider_payment_id, 'raw_payload' => $payload, 'paid_at' => now()]);
            $bet = $payment->bet()->lockForUpdate()->first();
            if (! $bet || $bet->payment_status === 'succeeded') return;
            $bet->update(['status' => 'paid', 'payment_status' => 'succeeded', 'paid_at' => now()]);
            LedgerEntry::firstOrCreate(['idempotency_key' => 'payment-confirmed-'.$payment->id], ['user_id' => $payment->user_id, 'bet_id' => $bet->id, 'payment_id' => $payment->id, 'type' => 'payment_confirmed', 'amount_cents' => $payment->amount_cents, 'status' => 'posted', 'metadata' => ['provider' => 'stripe']]);
        });
    }
}
