<?php

namespace Tests\Feature;

use App\Models\Draw;
use App\Models\LotteryGame;
use App\Models\LotteryPool;
use App\Models\Payout;
use App\Models\User;
use App\Services\SettlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class HomologationJourneyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Env::getRepository()->set('RISK_TEST_MODE', 'true');
        Env::getRepository()->set('RISK_TEST_CREDIT_CENTS', '500000');
        Env::getRepository()->set('RISK_MIN_RESERVE_CENTS', '0');
        Env::getRepository()->set('STRIPE_SECRET_KEY', 'sk_test_homologation');
        Env::getRepository()->set('STRIPE_PUBLISHABLE_KEY', 'pk_test_homologation');
        Env::getRepository()->set('STRIPE_WEBHOOK_SECRET', '');
        Mail::fake();

        Http::fake(function (HttpRequest $request) {
            if ($request->method() === 'POST' && str_ends_with($request->url(), '/v1/customers')) {
                return Http::response(['id' => 'cus_homologation'], 200);
            }

            if ($request->method() === 'POST' && str_contains($request->url(), '/v1/customers/cus_homologation')) {
                return Http::response(['id' => 'cus_homologation'], 200);
            }

            if ($request->method() === 'GET' && str_contains($request->url(), '/v1/payment_methods/pm_card_homologation')) {
                return Http::response(['id' => 'pm_card_homologation', 'type' => 'card', 'customer' => 'cus_homologation'], 200);
            }

            if ($request->method() === 'GET' && str_contains($request->url(), '/v1/payment_methods')) {
                return Http::response(['data' => [[
                    'id' => 'pm_card_homologation',
                    'type' => 'card',
                    'customer' => 'cus_homologation',
                    'card' => ['brand' => 'visa', 'last4' => '4242', 'exp_month' => 12, 'exp_year' => 2034, 'funding' => 'credit'],
                ]]], 200);
            }

            if ($request->method() === 'POST' && str_contains($request->url(), '/v1/setup_intents')) {
                return Http::response(['id' => 'seti_homologation', 'client_secret' => 'seti_homologation_secret'], 200);
            }

            if ($request->method() === 'POST' && str_contains($request->url(), '/v1/payment_intents')) {
                return Http::response(['id' => 'pi_homologation', 'status' => 'succeeded'], 200);
            }

            return Http::response(['error' => ['message' => 'Homologation fixture did not expect this request.']], 500);
        });
    }

    protected function tearDown(): void
    {
        Env::getRepository()->set('RISK_TEST_MODE', '');
        Env::getRepository()->set('RISK_TEST_CREDIT_CENTS', '');
        Env::getRepository()->set('RISK_MIN_RESERVE_CENTS', '');
        Env::getRepository()->set('STRIPE_SECRET_KEY', '');
        Env::getRepository()->set('STRIPE_PUBLISHABLE_KEY', '');
        Env::getRepository()->set('STRIPE_WEBHOOK_SECRET', '');

        parent::tearDown();
    }

    public function test_complete_homologation_journey_is_idempotent_and_fails_closed(): void
    {
        $admin = User::create([
            'name' => 'Admin Homologação',
            'email' => 'admin-homologation@test.local',
            'password' => Hash::make('secret'),
            'portal' => 'admin',
            'is_admin' => true,
            'active' => true,
        ]);

        $registration = $this->postJson('/api/auth/register', [
            'name' => 'Cliente Jornada E2E',
            'email' => 'cliente-jornada-e2e@test.local',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'age_confirmed' => true,
            'terms_accepted' => true,
        ])->assertCreated();
        $customer = User::where('email', 'cliente-jornada-e2e@test.local')->firstOrFail();
        $this->assertSame('cus_homologation', $customer->stripe_customer_id);

        $this->actingAs($customer, 'sanctum')->getJson('/api/v1/profile/payment-methods')
            ->assertOk()
            ->assertJsonPath('data.cards.0.last4', '4242');
        $this->actingAs($customer, 'sanctum')->postJson('/api/v1/profile/setup-intent')
            ->assertOk()
            ->assertJsonPath('data.client_secret', 'seti_homologation_secret');

        $game = LotteryGame::create([
            'slug' => 'homologation-mega',
            'name' => 'Mega-Sena Homologação',
            'short_name' => 'MEGA-H',
            'color' => '#31b8b2',
            'price_cents' => 500,
            'numbers_required' => 6,
            'range_max' => 60,
            'number_min' => 1,
            'selection_mode' => 'distinct',
            'allow_repeated_numbers' => false,
            'payout_rules' => ['6' => 2],
            'max_prize_cents' => 100000,
            'payout_ratio' => 0.70,
            'active' => true,
        ]);
        $draw = Draw::create([
            'lottery_game_id' => $game->id,
            'contest_number' => 990001,
            'draw_at' => now()->addDay(),
            'status' => 'open',
            'payout_cap_cents' => 100000,
        ]);
        $pool = LotteryPool::create([
            'lottery_game_id' => $game->id,
            'draw_id' => $draw->id,
            'name' => 'Bolão Homologação',
            'share_price_cents' => 790,
            'total_shares' => 10,
            'sold_shares' => 0,
            'reserved_shares' => 0,
            'total_stake_cents' => 7900,
            'status' => 'open',
        ]);

        $checkout = $this->actingAs($customer, 'sanctum')
            ->withHeaders(['Idempotency-Key' => 'homologation-journey-order'])
            ->postJson('/api/v1/orders/checkout', [
                'tickets' => [
                    ['game_id' => $game->id, 'draw_id' => $draw->id, 'numbers' => [1, 2, 3, 4, 5, 6]],
                    ['game_id' => $game->id, 'draw_id' => $draw->id, 'pool_id' => $pool->id, 'numbers' => [1, 2, 3, 4, 5, 6], 'shares' => 1],
                ],
                'method' => 'card',
                'payment_method_id' => 'pm_card_homologation',
            ])->assertCreated();
        $orderId = $checkout->json('data.order.id');
        $this->assertSame('payment_intent', $checkout->json('data.mode'));
        $this->assertSame(1290, $checkout->json('data.order.total_cents'));

        $event = [
            'type' => 'payment_intent.succeeded',
            'data' => ['object' => [
                'id' => 'pi_homologation',
                'amount_received' => 1290,
                'currency' => 'brl',
                'metadata' => ['order_id' => (string) $orderId],
            ]],
        ];
        $this->postJson('/api/stripe/webhook', $event)->assertOk();
        $this->postJson('/api/stripe/webhook', $event)->assertOk();
        $this->assertDatabaseHas('orders', ['id' => $orderId, 'status' => 'paid', 'payment_status' => 'succeeded']);
        $this->assertDatabaseCount('ledger_entries', 1);
        $this->assertDatabaseHas('lottery_pools', ['id' => $pool->id, 'sold_shares' => 1, 'reserved_shares' => 0]);

        $draw->update(['status' => 'result_received', 'results' => ['numbers' => [1, 2, 3, 4, 5, 6]], 'synced_at' => now()]);
        app(SettlementService::class)->settle($draw->fresh());
        $payout = Payout::query()->whereHas('bet', fn ($query) => $query->where('draw_id', $draw->id))->firstOrFail();
        $this->assertDatabaseHas('bets', ['draw_id' => $draw->id, 'status' => 'won', 'payout_cents' => 1000]);
        $this->assertSame('manual_review', $payout->status);

        $this->actingAs($admin, 'sanctum')->postJson('/api/v1/admin/payouts/'.$payout->id.'/approve', ['simulate' => true])
            ->assertOk()
            ->assertJsonPath('data.status', 'approved');
        $this->assertDatabaseHas('wallets', ['user_id' => $customer->id, 'balance_cents' => 1000]);

        $withdrawal = $this->actingAs($customer, 'sanctum')->postJson('/api/v1/wallet/withdrawals', ['amount_cents' => 1000, 'method' => 'pix', 'pix_key' => 'e2e@test.local'])
            ->assertCreated()
            ->json('data.id');
        $this->actingAs($admin, 'sanctum')->postJson('/api/v1/admin/wallet-withdrawals/'.$withdrawal.'/review', ['status' => 'paid', 'note' => 'Baixa simulada de homologação.'])
            ->assertOk()
            ->assertJsonPath('data.status', 'paid');

        $this->actingAs($admin, 'sanctum')->getJson('/api/v1/admin/dashboard')
            ->assertOk()
            ->assertJsonPath('data.kpis.revenue_cents', 1290)
            ->assertJsonPath('data.kpis.payout_cents', 1000)
            ->assertJsonPath('data.kpis.margin_cents', 290)
            ->assertJsonPath('data.risk.test_mode', true)
            ->assertJsonPath('data.risk.test_credit_cents', 500000);

        Http::assertSent(fn (HttpRequest $request): bool => str_contains($request->url(), '/v1/payment_intents') && str_contains((string) $request->body(), 'payment_method=pm_card_homologation'));
        $this->assertNotNull($registration->json('data.access_token'));
    }
}
