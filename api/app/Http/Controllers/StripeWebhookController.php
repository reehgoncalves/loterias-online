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
        if ($secret !== '' && ! $this->validSignature($payload, (string) $request->header('Stripe-Signature'), $secret)) return response()->json(['message'=>'Assinatura inválida.'], 400);
        $event = json_decode($payload, true);
        if (! is_array($event)) return response()->json(['message'=>'Payload inválido.'],400);
        if (in_array($event['type'] ?? '', ['checkout.session.completed','payment_intent.succeeded'], true)) $payments->confirmFromWebhook($event);
        return response()->json(['received'=>true]);
    }
    private function validSignature(string $payload, string $header, string $secret): bool { $timestamp = collect(explode(',', $header))->first(fn ($part) => Str::startsWith($part,'t=')); $signature = collect(explode(',', $header))->first(fn ($part) => Str::startsWith($part,'v1=')); if (! $timestamp || ! $signature || abs(time()-(int) Str::after($timestamp,'t='))>300) return false; return hash_equals(hash_hmac('sha256', Str::after($timestamp,'t=').'.'.$payload,$secret),Str::after($signature,'v1=')); }
}

