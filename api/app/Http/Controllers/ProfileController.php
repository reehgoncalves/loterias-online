<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class ProfileController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user();
        return response()->json(['data' => [
            'id' => $user->id, 'name' => $user->name, 'email' => $user->email,
            'phone' => $user->phone, 'cpf' => $user->cpf, 'marketing_opt_in' => (bool) $user->marketing_opt_in,
            'has_stripe_customer' => (bool) $user->stripe_customer_id,
        ]]);
    }

    public function billingPortal(Request $request)
    {
        $secret = (string) env('STRIPE_SECRET_KEY');
        if ($secret === '') return response()->json(['message' => 'O portal de pagamentos ficará disponível após configurar a chave do Stripe no ambiente seguro da API.'], 503);

        $user = $request->user();
        if (! $user->stripe_customer_id) {
            $customerResponse = Http::asForm()->withBasicAuth($secret, '')->post('https://api.stripe.com/v1/customers', [
                'email' => $user->email, 'name' => $user->name, 'metadata[user_id]' => (string) $user->id,
            ]);
            if (! $customerResponse->successful()) throw new RuntimeException('Stripe não criou o perfil de cobrança: '.$customerResponse->json('error.message', 'erro desconhecido'));
            $user->update(['stripe_customer_id' => $customerResponse->json('id')]);
        }

        $portal = Http::asForm()->withBasicAuth($secret, '')->post('https://api.stripe.com/v1/billing_portal/sessions', [
            'customer' => $user->stripe_customer_id,
            'return_url' => rtrim(env('FRONTEND_URL', 'http://127.0.0.1:5173'), '/').'/perfil',
        ]);
        if (! $portal->successful()) throw new RuntimeException('Stripe não abriu o portal: '.$portal->json('error.message', 'erro desconhecido'));
        return response()->json(['data' => ['url' => $portal->json('url')]]);
    }
}
