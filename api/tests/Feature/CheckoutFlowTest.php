<?php

namespace Tests\Feature;

use App\Mail\BetConfirmationMail;
use App\Models\Bet;
use App\Models\Draw;
use App\Models\LedgerEntry;
use App\Models\LotteryGame;
use App\Models\LotteryPool;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Env;
use Tests\TestCase;

class CheckoutFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Env::getRepository()->set('RISK_MIN_RESERVE_CENTS', 0);
        Env::getRepository()->set('RISK_PAYOUT_RATIO', 0.70);
        Env::getRepository()->set('RISK_SAFETY_RATIO', 0.80);
        Env::getRepository()->set('STRIPE_SECRET_KEY', '');
        Env::getRepository()->set('STRIPE_WEBHOOK_SECRET', '');
    }

    protected function tearDown(): void
    {
        Env::getRepository()->set('RISK_MIN_RESERVE_CENTS', '');
        Env::getRepository()->set('RISK_PAYOUT_RATIO', '');
        Env::getRepository()->set('RISK_SAFETY_RATIO', '');
        Env::getRepository()->set('STRIPE_SECRET_KEY', '');
        Env::getRepository()->set('STRIPE_WEBHOOK_SECRET', '');
        parent::tearDown();
    }

    public function test_customer_can_create_an_idempotent_cart_order_without_boleto(): void
    {
        [$customer, $game, $draw] = $this->fixture();
        $headers = ['Idempotency-Key' => 'checkout-flow-card-1'];
        $payload = ['tickets' => [['game_id' => $game->id, 'draw_id' => $draw->id, 'numbers' => [1, 2, 3, 4, 5, 6]]], 'method' => 'card'];

        $first = $this->actingAs($customer, 'sanctum')->withHeaders($headers)->postJson('/api/v1/orders/checkout', $payload);
        $first->assertCreated()->assertJsonPath('data.mode', 'stripe_not_configured')->assertJsonPath('data.order.total_cents', 100);
        $orderId = $first->json('data.order.id');

        $this->actingAs($customer, 'sanctum')->withHeaders($headers)->postJson('/api/v1/orders/checkout', $payload)
            ->assertOk()->assertJsonPath('data.order.id', $orderId)->assertJsonPath('data.mode', 'stripe_not_configured');

        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('payments', 1);
    }

    public function test_boleto_is_rejected_until_it_is_enabled_again(): void
    {
        [$customer, $game, $draw] = $this->fixture();

        $this->actingAs($customer, 'sanctum')->postJson('/api/v1/orders/checkout', [
            'tickets' => [['game_id' => $game->id, 'draw_id' => $draw->id, 'numbers' => [1, 2, 3, 4, 5, 6]]],
            'method' => 'boleto',
        ])->assertUnprocessable()->assertJsonValidationErrors('method');
    }

    public function test_stripe_checkout_session_is_created_with_card(): void
    {
        [$customer, $game, $draw] = $this->fixture();
        $order = Order::create(['user_id' => $customer->id, 'total_cents' => 100, 'currency' => 'brl', 'status' => 'awaiting_payment', 'payment_status' => 'pending', 'idempotency_key' => 'stripe-session-test']);
        $order->items()->create(['lottery_game_id' => $game->id, 'draw_id' => $draw->id, 'numbers' => [1, 2, 3, 4, 5, 6], 'amount_cents' => 100, 'shares' => 1, 'potential_prize_cents' => 100]);
        Env::getRepository()->set('STRIPE_SECRET_KEY', 'sk_test_fake');
        Http::fake(['https://api.stripe.com/v1/checkout/sessions' => Http::response(['id' => 'cs_test_flow', 'url' => 'https://checkout.stripe.test/cs_test_flow'], 200)]);

        $result = app(PaymentService::class)->checkoutOrder($order, $customer, 'card');

        $this->assertSame('https://checkout.stripe.test/cs_test_flow', $result['checkout_url']);
        $this->assertDatabaseHas('payments', ['order_id' => $order->id, 'provider_checkout_id' => 'cs_test_flow', 'method' => 'card']);
        Http::assertSent(fn (HttpRequest $request) => str_contains((string) $request->body(), 'payment_method_types%5B0%5D=card'));
    }

    public function test_stripe_checkout_session_is_created_with_pix_and_payment_intent_metadata(): void
    {
        [$customer, $game, $draw] = $this->fixture();
        $order = Order::create(['user_id' => $customer->id, 'total_cents' => 100, 'currency' => 'brl', 'status' => 'awaiting_payment', 'payment_status' => 'pending', 'idempotency_key' => 'stripe-pix-session-test']);
        $order->items()->create(['lottery_game_id' => $game->id, 'draw_id' => $draw->id, 'numbers' => [1, 2, 3, 4, 5, 6], 'amount_cents' => 100, 'shares' => 1, 'potential_prize_cents' => 100]);
        Env::getRepository()->set('STRIPE_SECRET_KEY', 'sk_test_fake');
        Http::fake(['https://api.stripe.com/v1/checkout/sessions' => Http::response(['id' => 'cs_test_pix', 'url' => 'https://checkout.stripe.test/cs_test_pix'], 200)]);

        app(PaymentService::class)->checkoutOrder($order, $customer, 'pix');

        Http::assertSent(function (HttpRequest $request) use ($order): bool {
            parse_str((string) $request->body(), $data);
            return ($data['payment_method_types'][0] ?? null) === 'pix'
                && ($data['payment_intent_data']['metadata']['order_id'] ?? null) === (string) $order->id;
        });
    }

    public function test_stripe_api_error_keeps_payment_retryable_and_surfaces_provider_message(): void
    {
        [$customer, $game, $draw] = $this->fixture();
        $order = Order::create(['user_id' => $customer->id, 'total_cents' => 100, 'currency' => 'brl', 'status' => 'awaiting_payment', 'payment_status' => 'pending', 'idempotency_key' => 'stripe-error-test']);
        $order->items()->create(['lottery_game_id' => $game->id, 'draw_id' => $draw->id, 'numbers' => [1, 2, 3, 4, 5, 6], 'amount_cents' => 100, 'shares' => 1, 'potential_prize_cents' => 100]);
        Env::getRepository()->set('STRIPE_SECRET_KEY', 'sk_test_fake');
        Http::fake(['https://api.stripe.com/v1/checkout/sessions' => Http::response(['error' => ['message' => 'Cartão de teste recusado.']], 402)]);

        $this->expectExceptionMessage('Cartão de teste recusado.');
        try {
            app(PaymentService::class)->checkoutOrder($order, $customer, 'card');
        } finally {
            $this->assertDatabaseHas('payments', ['order_id' => $order->id, 'status' => 'pending']);
            $this->assertSame('Cartão de teste recusado.', Payment::where('order_id', $order->id)->firstOrFail()->raw_payload['stripe_error']['message']);
        }
    }

    public function test_stripe_incomplete_checkout_response_is_never_saved_as_a_valid_session(): void
    {
        [$customer, $game, $draw] = $this->fixture();
        $order = Order::create(['user_id' => $customer->id, 'total_cents' => 100, 'currency' => 'brl', 'status' => 'awaiting_payment', 'payment_status' => 'pending', 'idempotency_key' => 'stripe-incomplete-test']);
        $order->items()->create(['lottery_game_id' => $game->id, 'draw_id' => $draw->id, 'numbers' => [1, 2, 3, 4, 5, 6], 'amount_cents' => 100, 'shares' => 1, 'potential_prize_cents' => 100]);
        Env::getRepository()->set('STRIPE_SECRET_KEY', 'sk_test_fake');
        Http::fake(['https://api.stripe.com/v1/checkout/sessions' => Http::response(['id' => 'cs_missing_url'], 200)]);

        $this->expectExceptionMessage('checkout incompleto');
        try {
            app(PaymentService::class)->checkoutOrder($order, $customer, 'card');
        } finally {
            $this->assertNull(Payment::where('order_id', $order->id)->firstOrFail()->provider_checkout_id);
        }
    }

    public function test_legacy_bet_checkout_api_creates_a_stripe_session_for_pix(): void
    {
        [$customer, $game, $draw] = $this->fixture();
        $bet = Bet::create(['user_id' => $customer->id, 'lottery_game_id' => $game->id, 'draw_id' => $draw->id, 'numbers' => [1, 2, 3, 4, 5, 6], 'amount_cents' => 100, 'potential_prize_cents' => 100, 'status' => 'awaiting_payment', 'payment_status' => 'pending', 'is_pool_share' => false, 'idempotency_key' => 'legacy-bet-checkout-1']);
        Env::getRepository()->set('STRIPE_SECRET_KEY', 'sk_test_fake');
        Http::fake(['https://api.stripe.com/v1/checkout/sessions' => Http::response(['id' => 'cs_legacy_pix', 'url' => 'https://checkout.stripe.test/cs_legacy_pix'], 200)]);

        $this->actingAs($customer, 'sanctum')->postJson('/api/v1/payments/checkout', ['bet_id' => $bet->id, 'method' => 'pix'])
            ->assertOk()->assertJsonPath('data.checkout_url', 'https://checkout.stripe.test/cs_legacy_pix');
        $this->assertDatabaseHas('payments', ['bet_id' => $bet->id, 'provider_checkout_id' => 'cs_legacy_pix', 'method' => 'pix']);
    }

    public function test_billing_portal_api_creates_customer_and_session_without_exposing_stripe_key(): void
    {
        [$customer] = $this->fixture();
        Env::getRepository()->set('STRIPE_SECRET_KEY', 'sk_test_fake');
        Http::fake([
            'https://api.stripe.com/v1/customers' => Http::response(['id' => 'cus_test_checkout'], 200),
            'https://api.stripe.com/v1/billing_portal/sessions' => Http::response(['id' => 'bps_test_checkout', 'url' => 'https://billing.stripe.test/session'], 200),
        ]);

        $this->actingAs($customer, 'sanctum')->postJson('/api/v1/profile/billing-portal')
            ->assertOk()->assertJsonPath('data.url', 'https://billing.stripe.test/session');
        $this->assertDatabaseHas('users', ['id' => $customer->id, 'stripe_customer_id' => 'cus_test_checkout']);
    }

    public function test_payment_webhook_marks_an_order_paid_only_once(): void
    {
        Mail::fake();
        [$customer, $game, $draw] = $this->fixture();
        $orderResponse = $this->actingAs($customer, 'sanctum')->withHeaders(['Idempotency-Key' => 'webhook-order-1'])->postJson('/api/v1/orders/checkout', [
            'tickets' => [['game_id' => $game->id, 'draw_id' => $draw->id, 'numbers' => [1, 2, 3, 4, 5, 6]]], 'method' => 'pix',
        ])->assertCreated();
        $orderId = $orderResponse->json('data.order.id');
        $payment = Payment::where('order_id', $orderId)->firstOrFail();
        $payment->update(['provider_checkout_id' => 'cs_webhook_flow']);
        $event = ['type' => 'checkout.session.completed', 'data' => ['object' => ['id' => 'cs_webhook_flow', 'payment_intent' => 'pi_webhook_flow', 'payment_status' => 'paid', 'metadata' => ['order_id' => (string) $orderId]]]];

        $this->postJson('/api/stripe/webhook', $event)->assertOk();
        $this->postJson('/api/stripe/webhook', $event)->assertOk();

        $this->assertDatabaseHas('orders', ['id' => $orderId, 'status' => 'paid', 'payment_status' => 'succeeded']);
        $this->assertDatabaseHas('bets', ['order_id' => $orderId, 'status' => 'paid', 'payment_status' => 'succeeded']);
        $this->assertSame(1, LedgerEntry::where('idempotency_key', 'payment-confirmed-'.$payment->id)->count());
        Mail::assertQueued(BetConfirmationMail::class, 1);
    }

    public function test_payment_intent_succeeded_confirms_order_using_payment_intent_metadata(): void
    {
        Mail::fake();
        [$customer, $game, $draw] = $this->fixture();
        $orderResponse = $this->actingAs($customer, 'sanctum')->withHeaders(['Idempotency-Key' => 'payment-intent-order-1'])->postJson('/api/v1/orders/checkout', [
            'tickets' => [['game_id' => $game->id, 'draw_id' => $draw->id, 'numbers' => [1, 2, 3, 4, 5, 6]]], 'method' => 'card',
        ])->assertCreated();
        $orderId = $orderResponse->json('data.order.id');
        $event = ['type' => 'payment_intent.succeeded', 'data' => ['object' => ['id' => 'pi_direct_flow', 'amount_received' => 100, 'currency' => 'brl', 'metadata' => ['order_id' => (string) $orderId]]]];

        $this->postJson('/api/stripe/webhook', $event)->assertOk();

        $this->assertDatabaseHas('orders', ['id' => $orderId, 'status' => 'paid', 'payment_status' => 'succeeded']);
        $this->assertDatabaseHas('payments', ['order_id' => $orderId, 'provider_payment_id' => 'pi_direct_flow', 'status' => 'succeeded']);
    }

    public function test_webhook_amount_mismatch_is_rejected_without_confirming_the_order(): void
    {
        [$customer, $game, $draw] = $this->fixture();
        $orderResponse = $this->actingAs($customer, 'sanctum')->withHeaders(['Idempotency-Key' => 'amount-mismatch-order-1'])->postJson('/api/v1/orders/checkout', [
            'tickets' => [['game_id' => $game->id, 'draw_id' => $draw->id, 'numbers' => [1, 2, 3, 4, 5, 6]]], 'method' => 'card',
        ])->assertCreated();
        $orderId = $orderResponse->json('data.order.id');
        $payment = Payment::where('order_id', $orderId)->firstOrFail();
        $payment->update(['provider_checkout_id' => 'cs_amount_mismatch']);

        $this->postJson('/api/stripe/webhook', ['type' => 'checkout.session.completed', 'data' => ['object' => ['id' => 'cs_amount_mismatch', 'payment_status' => 'paid', 'amount_total' => 101, 'currency' => 'brl', 'metadata' => ['order_id' => (string) $orderId]]]])->assertOk();

        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'failed']);
        $this->assertDatabaseHas('orders', ['id' => $orderId, 'status' => 'cancelled', 'payment_status' => 'failed']);
        $this->assertDatabaseMissing('orders', ['id' => $orderId, 'status' => 'paid']);
    }

    public function test_pix_checkout_waits_for_async_success_before_confirming_the_bet(): void
    {
        Mail::fake();
        [$customer, $game, $draw] = $this->fixture();
        $orderResponse = $this->actingAs($customer, 'sanctum')->withHeaders(['Idempotency-Key' => 'pix-async-order-1'])->postJson('/api/v1/orders/checkout', [
            'tickets' => [['game_id' => $game->id, 'draw_id' => $draw->id, 'numbers' => [1, 2, 3, 4, 5, 6]]], 'method' => 'pix',
        ])->assertCreated();
        $orderId = $orderResponse->json('data.order.id');
        $payment = Payment::where('order_id', $orderId)->firstOrFail();
        $payment->update(['provider_checkout_id' => 'cs_pix_async']);

        $this->postJson('/api/stripe/webhook', ['type' => 'checkout.session.completed', 'data' => ['object' => ['id' => 'cs_pix_async', 'payment_status' => 'unpaid', 'metadata' => ['order_id' => (string) $orderId]]]])->assertOk();
        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'processing']);
        $this->assertDatabaseHas('bets', ['order_id' => $orderId, 'status' => 'awaiting_payment', 'payment_status' => 'processing']);

        $this->postJson('/api/stripe/webhook', ['type' => 'checkout.session.async_payment_succeeded', 'data' => ['object' => ['id' => 'cs_pix_async', 'payment_intent' => 'pi_pix_async', 'amount_total' => 100, 'currency' => 'brl', 'metadata' => ['order_id' => (string) $orderId]]]])->assertOk();
        $this->assertDatabaseHas('orders', ['id' => $orderId, 'status' => 'paid', 'payment_status' => 'succeeded']);
    }

    public function test_failed_payment_cancels_order_and_releases_reserved_pool_shares(): void
    {
        [$customer, $game, $draw] = $this->fixture();
        $pool = LotteryPool::create(['lottery_game_id' => $game->id, 'draw_id' => $draw->id, 'name' => 'Bolão de teste', 'share_price_cents' => 100, 'total_shares' => 2, 'sold_shares' => 0, 'reserved_shares' => 0, 'total_stake_cents' => 0, 'status' => 'open']);
        $orderResponse = $this->actingAs($customer, 'sanctum')->withHeaders(['Idempotency-Key' => 'failed-pool-order-1'])->postJson('/api/v1/orders/checkout', [
            'tickets' => [['game_id' => $game->id, 'draw_id' => $draw->id, 'pool_id' => $pool->id, 'numbers' => [1, 2, 3, 4, 5, 6], 'shares' => 1]], 'method' => 'card',
        ])->assertCreated();
        $orderId = $orderResponse->json('data.order.id');
        $payment = Payment::where('order_id', $orderId)->firstOrFail();
        $payment->update(['provider_checkout_id' => 'cs_failed_pool']);

        $this->postJson('/api/stripe/webhook', ['type' => 'checkout.session.expired', 'data' => ['object' => ['id' => 'cs_failed_pool', 'metadata' => ['order_id' => (string) $orderId]]]])->assertOk();

        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'failed']);
        $this->assertDatabaseHas('orders', ['id' => $orderId, 'status' => 'cancelled', 'payment_status' => 'failed']);
        $this->assertDatabaseHas('pool_shares', ['order_id' => $orderId, 'status' => 'released']);
        $this->assertDatabaseHas('lottery_pools', ['id' => $pool->id, 'reserved_shares' => 0]);
    }

    public function test_card_failure_keeps_checkout_retryable_until_it_expires(): void
    {
        [$customer, $game, $draw] = $this->fixture();
        $pool = LotteryPool::create(['lottery_game_id' => $game->id, 'draw_id' => $draw->id, 'name' => 'Bolão retry', 'share_price_cents' => 100, 'total_shares' => 2, 'sold_shares' => 0, 'reserved_shares' => 0, 'total_stake_cents' => 0, 'status' => 'open']);
        $orderResponse = $this->actingAs($customer, 'sanctum')->withHeaders(['Idempotency-Key' => 'retryable-card-order-1'])->postJson('/api/v1/orders/checkout', [
            'tickets' => [['game_id' => $game->id, 'draw_id' => $draw->id, 'pool_id' => $pool->id, 'numbers' => [1, 2, 3, 4, 5, 6], 'shares' => 1]], 'method' => 'card',
        ])->assertCreated();
        $orderId = $orderResponse->json('data.order.id');
        $payment = Payment::where('order_id', $orderId)->firstOrFail();
        $payment->update(['provider_checkout_id' => 'cs_retryable_card']);

        $this->postJson('/api/stripe/webhook', ['type' => 'payment_intent.payment_failed', 'data' => ['object' => ['id' => 'pi_retryable_card', 'metadata' => ['order_id' => (string) $orderId], 'last_payment_error' => ['code' => 'card_declined']]]])->assertOk();
        $this->assertDatabaseHas('orders', ['id' => $orderId, 'status' => 'awaiting_payment', 'payment_status' => 'failed']);
        $this->assertDatabaseHas('lottery_pools', ['id' => $pool->id, 'reserved_shares' => 1]);

        $this->postJson('/api/stripe/webhook', ['type' => 'payment_intent.succeeded', 'data' => ['object' => ['id' => 'pi_retryable_card', 'amount_received' => 100, 'currency' => 'brl', 'metadata' => ['order_id' => (string) $orderId]]]])->assertOk();
        $this->assertDatabaseHas('orders', ['id' => $orderId, 'status' => 'paid', 'payment_status' => 'succeeded']);
        $this->assertDatabaseHas('lottery_pools', ['id' => $pool->id, 'reserved_shares' => 0, 'sold_shares' => 1]);
    }

    public function test_webhook_rejects_invalid_signature_and_accepts_a_fresh_valid_signature(): void
    {
        Env::getRepository()->set('STRIPE_WEBHOOK_SECRET', 'whsec_test_lab');
        $payload = json_encode(['type' => 'payment_intent.processing', 'data' => ['object' => []]], JSON_THROW_ON_ERROR);

        $this->call('POST', '/api/stripe/webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => 't='.time().',v1=invalid',
        ], $payload)->assertBadRequest();

        $timestamp = (string) time();
        $signature = hash_hmac('sha256', $timestamp.'.'.$payload, 'whsec_test_lab');
        $this->call('POST', '/api/stripe/webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => 't='.$timestamp.',v1='.$signature,
        ], $payload)->assertOk()->assertJson(['received' => true]);
    }

    private function fixture(): array
    {
        $customer = User::create(['name' => 'Checkout Teste', 'email' => 'checkout@test.local', 'password' => Hash::make('secret'), 'portal' => 'cliente', 'active' => true]);
        $game = LotteryGame::create(['slug' => 'checkout-test', 'name' => 'Checkout Teste', 'short_name' => 'TESTE', 'color' => '#5c2db8', 'price_cents' => 100, 'numbers_required' => 6, 'range_max' => 60, 'number_min' => 1, 'selection_mode' => 'distinct', 'allow_repeated_numbers' => false, 'payout_rules' => ['6' => 1], 'max_prize_cents' => 100000, 'payout_ratio' => 0.70, 'active' => true]);
        $draw = Draw::create(['lottery_game_id' => $game->id, 'contest_number' => 999001, 'draw_at' => now()->addDay(), 'status' => 'open', 'payout_cap_cents' => 100000]);
        LedgerEntry::create(['user_id' => $customer->id, 'type' => 'payment_confirmed', 'amount_cents' => 2000, 'status' => 'posted', 'idempotency_key' => 'checkout-cash-'.$customer->id]);
        return [$customer, $game, $draw];
    }
}
