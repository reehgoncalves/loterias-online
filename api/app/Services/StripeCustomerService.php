<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class StripeCustomerService
{
    public function ensure(User $user): ?string
    {
        $secret = (string) env('STRIPE_SECRET_KEY');
        if ($secret === '') return null;

        $user->refresh();
        if ($user->stripe_customer_id) {
            $this->sync($user, $secret);
            return $user->stripe_customer_id;
        }

        try {
            $response = Http::timeout((int) env('STRIPE_TIMEOUT_SECONDS', 15))
                ->asForm()
                ->withBasicAuth($secret, '')
                ->post('https://api.stripe.com/v1/customers', [
                    'email' => $user->email,
                    'name' => $user->name,
                    'metadata[user_id]' => (string) $user->id,
                ]);
        } catch (\Throwable $exception) {
            throw new RuntimeException('Stripe está indisponível no momento.', 0, $exception);
        }

        if (! $response->successful()) {
            throw new RuntimeException('Stripe não criou o cliente: '.$response->json('error.message', 'erro desconhecido'));
        }

        $customerId = $response->json('id');
        if (! is_string($customerId) || ! str_starts_with($customerId, 'cus_')) {
            throw new RuntimeException('Stripe retornou um cliente inválido.');
        }

        $user->update(['stripe_customer_id' => $customerId]);
        return $customerId;
    }

    private function sync(User $user, string $secret): void
    {
        try {
            $response = Http::timeout((int) env('STRIPE_TIMEOUT_SECONDS', 15))
                ->asForm()
                ->withBasicAuth($secret, '')
                ->post('https://api.stripe.com/v1/customers/'.$user->stripe_customer_id, [
                    'email' => $user->email,
                    'name' => $user->name,
                    'metadata[user_id]' => (string) $user->id,
                ]);
        } catch (\Throwable $exception) {
            throw new RuntimeException('Stripe está indisponível no momento.', 0, $exception);
        }

        if (! $response->successful()) {
            throw new RuntimeException('Stripe não sincronizou o cliente: '.$response->json('error.message', 'erro desconhecido'));
        }
    }
}
