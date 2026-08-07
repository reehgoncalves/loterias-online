<?php

namespace Tests\Feature;

use App\Mail\BetConfirmationMail;
use App\Models\Bet;
use App\Models\Draw;
use App\Models\LedgerEntry;
use App\Models\LotteryGame;
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
        $event = ['type' => 'checkout.session.completed', 'data' => ['object' => ['id' => 'cs_webhook_flow', 'payment_intent' => 'pi_webhook_flow', 'metadata' => ['order_id' => (string) $orderId]]]];

        $this->postJson('/api/stripe/webhook', $event)->assertOk();
        $this->postJson('/api/stripe/webhook', $event)->assertOk();

        $this->assertDatabaseHas('orders', ['id' => $orderId, 'status' => 'paid', 'payment_status' => 'succeeded']);
        $this->assertDatabaseHas('bets', ['order_id' => $orderId, 'status' => 'paid', 'payment_status' => 'succeeded']);
        $this->assertSame(1, LedgerEntry::where('idempotency_key', 'payment-confirmed-'.$payment->id)->count());
        Mail::assertQueued(BetConfirmationMail::class, 1);
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
