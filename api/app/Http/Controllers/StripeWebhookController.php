<?php

namespace App\Http\Controllers;

use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class StripeWebhookController extends Controller
{
    public function __invoke(Request $request, PaymentService $payments) {
        $payload = $request->getContent();
        $secret = (string) env('STRIPE_WEBHOOK_SECRET');
        if ($secret === '' && ! app()->environment(['local', 'testing', 'staging'])) return response()->json(['message'=>'Webhook Stripe sem segredo configurado.'], 503);
        if ($secret !== '' && ! $this->validSignature($payload, (string) $request->header('Stripe-Signature'), $secret)) return response()->json(['message'=>'Assinatura inválida.'], 400);
        $event = json_decode($payload, true);
        if (! is_array($event)) return response()->json(['message'=>'Payload inválido.'],400);
        $type = $event['type'] ?? '';
        if (in_array($type, ['checkout.session.completed','checkout.session.async_payment_succeeded','payment_intent.succeeded'], true)) $payments->confirmFromWebhook($event);
        if ($type === 'payment_intent.processing') $payments->markProcessingFromWebhookForPayload($event);
        if (in_array($type, ['payment_intent.payment_failed','payment_intent.canceled','checkout.session.async_payment_failed','checkout.session.expired'], true)) $payments->failFromWebhookForPayload($event);
        return response()->json(['received'=>true]);
    }
    private function validSignature(string $payload, string $header, string $secret): bool { $timestamp = collect(explode(',', $header))->first(fn ($part) => Str::startsWith(trim($part),'t=')); $signatures = collect(explode(',', $header))->filter(fn ($part) => Str::startsWith(trim($part),'v1='))->map(fn ($part) => Str::after(trim($part),'v1=')); if (! $timestamp || $signatures->isEmpty() || abs(time()-(int) Str::after(trim($timestamp),'t='))>(int) env('STRIPE_WEBHOOK_TOLERANCE_SECONDS', 300)) return false; $expected = hash_hmac('sha256', Str::after(trim($timestamp),'t=').'.'.$payload,$secret); return $signatures->contains(fn ($signature) => hash_equals($expected, $signature)); }
}
