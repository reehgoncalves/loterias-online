<?php

namespace Tests\Feature;

use App\Models\Draw;
use App\Models\LotteryGame;
use App\Models\Order;
use App\Models\User;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StripeLiveSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_stripe_test_mode_can_create_card_and_pix_checkout_sessions_through_the_application(): void
    {
        $key = (string) env('STRIPE_SECRET_KEY');
        if (! filter_var(env('RUN_STRIPE_LIVE_TESTS', false), FILTER_VALIDATE_BOOL) || ! str_starts_with($key, 'sk_test_')) {
            $this->markTestSkipped('Defina RUN_STRIPE_LIVE_TESTS=true e STRIPE_SECRET_KEY=sk_test_... para executar o smoke test real do Stripe.');
        }

        Env::getRepository()->set('RISK_MIN_RESERVE_CENTS', 0);
        Env::getRepository()->set('STRIPE_SUCCESS_URL', 'https://example.com/payment=success');
        Env::getRepository()->set('STRIPE_CANCEL_URL', 'https://example.com/payment=cancelled');

        $customer = User::create(['name' => 'Stripe Live Test', 'email' => 'stripe-live-'.uniqid().'@test.local', 'password' => Hash::make('secret'), 'portal' => 'cliente', 'active' => true]);
        $game = LotteryGame::create(['slug' => 'stripe-live-'.uniqid(), 'name' => 'Stripe Live Test', 'short_name' => 'SLT', 'color' => '#5c2db8', 'price_cents' => 100, 'numbers_required' => 6, 'range_max' => 60, 'number_min' => 1, 'selection_mode' => 'distinct', 'allow_repeated_numbers' => false, 'payout_rules' => ['6' => 1], 'max_prize_cents' => 100000, 'payout_ratio' => 0.70, 'active' => true]);
        $draw = Draw::create(['lottery_game_id' => $game->id, 'contest_number' => random_int(100000, 999999), 'draw_at' => now()->addDay(), 'status' => 'open', 'payout_cap_cents' => 100000]);
        $service = app(PaymentService::class);

        foreach (['card', 'pix'] as $method) {
            $order = Order::create(['user_id' => $customer->id, 'total_cents' => 100, 'currency' => 'brl', 'status' => 'awaiting_payment', 'payment_status' => 'pending', 'idempotency_key' => 'stripe-live-'.$method.'-'.uniqid()]);
            $order->items()->create(['lottery_game_id' => $game->id, 'draw_id' => $draw->id, 'numbers' => [1, 2, 3, 4, 5, 6], 'amount_cents' => 100, 'shares' => 1, 'potential_prize_cents' => 100]);
            $result = $service->checkoutOrder($order, $customer, $method);

            $this->assertStringStartsWith('cs_', (string) $result['payment']->provider_checkout_id);
            $this->assertStringStartsWith('https://checkout.stripe.com/', (string) $result['checkout_url']);
        }
    }
}
