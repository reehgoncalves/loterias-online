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

    /** @return array<int, array{id: string, brand: string, last4: string, exp_month: int|null, exp_year: int|null, funding: string|null}> */
    public function listCards(User $user): array
    {
        $secret = (string) env('STRIPE_SECRET_KEY');
        if ($secret === '') return [];

        $customerId = $this->ensure($user);
        if (! $customerId) return [];

        try {
            $response = Http::timeout((int) env('STRIPE_TIMEOUT_SECONDS', 15))
                ->withBasicAuth($secret, '')
                ->get('https://api.stripe.com/v1/payment_methods', [
                    'customer' => $customerId,
                    'type' => 'card',
                    'limit' => 20,
                ]);
        } catch (\Throwable $exception) {
            throw new RuntimeException('Stripe está indisponível no momento.', 0, $exception);
        }

        if (! $response->successful()) {
            throw new RuntimeException('Stripe não listou os cartões: '.$response->json('error.message', 'erro desconhecido'));
        }

        return collect($response->json('data', []))->map(function (array $method): array {
            $card = $method['card'] ?? [];
            return [
                'id' => (string) ($method['id'] ?? ''),
                'brand' => strtoupper((string) ($card['brand'] ?? 'card')),
                'last4' => (string) ($card['last4'] ?? ''),
                'exp_month' => isset($card['exp_month']) ? (int) $card['exp_month'] : null,
                'exp_year' => isset($card['exp_year']) ? (int) $card['exp_year'] : null,
                'funding' => isset($card['funding']) ? (string) $card['funding'] : null,
            ];
        })->filter(fn (array $method): bool => str_starts_with($method['id'], 'pm_') && $method['last4'] !== '')->values()->all();
    }

    public function verifyCard(User $user, string $paymentMethodId): string
    {
        if (! preg_match('/^pm_[A-Za-z0-9_-]+$/', $paymentMethodId)) {
            throw new RuntimeException('Cartão selecionado inválido.');
        }

        $secret = (string) env('STRIPE_SECRET_KEY');
        if ($secret === '') throw new RuntimeException('O Stripe ainda não está configurado neste ambiente.');

        $customerId = $this->ensure($user);
        try {
            $response = Http::timeout((int) env('STRIPE_TIMEOUT_SECONDS', 15))
                ->withBasicAuth($secret, '')
                ->get('https://api.stripe.com/v1/payment_methods/'.$paymentMethodId);
        } catch (\Throwable $exception) {
            throw new RuntimeException('Stripe está indisponível no momento.', 0, $exception);
        }

        if (! $response->successful()) {
            throw new RuntimeException('Não foi possível validar o cartão selecionado.');
        }

        $method = $response->json();
        if (($method['type'] ?? null) !== 'card' || ($method['customer'] ?? null) !== $customerId) {
            throw new RuntimeException('Esse cartão não pertence à conta logada.');
        }

        return $paymentMethodId;
    }
}
