<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use App\Services\StripeCustomerService;

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

    public function billingPortal(Request $request, StripeCustomerService $customers)
    {
        $secret = (string) env('STRIPE_SECRET_KEY');
        if ($secret === '') return response()->json(['message' => 'O portal de pagamentos ficará disponível após configurar a chave do Stripe no ambiente seguro da API.'], 503);

        $user = $request->user();
        try {
            $customerId = $customers->ensure($user);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 502);
        }

        try {
            $portal = Http::timeout((int) env('STRIPE_TIMEOUT_SECONDS', 15))->asForm()->withBasicAuth($secret, '')->post('https://api.stripe.com/v1/billing_portal/sessions', [
                'customer' => $customerId,
                'return_url' => rtrim(env('FRONTEND_URL', 'http://127.0.0.1:5173'), '/').'/perfil',
            ]);
        } catch (\Throwable $exception) {
            return response()->json(['message' => 'Stripe está indisponível no momento.'], 502);
        }
        if (! $portal->successful()) throw new RuntimeException('Stripe não abriu o portal: '.$portal->json('error.message', 'erro desconhecido'));
        return response()->json(['data' => ['url' => $portal->json('url')]]);
    }
}
