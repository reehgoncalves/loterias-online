<?php

namespace Tests\Feature;

use App\Models\Bet;
use App\Models\Draw;
use App\Models\LotteryGame;
use App\Models\Payout;
use App\Models\User;
use App\Services\RiskGuard;
use App\Services\SettlementService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class WalletFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Env::getRepository()->set('RISK_TEST_MODE', 'true');
        Env::getRepository()->set('RISK_TEST_CREDIT_CENTS', '500000');
    }

    protected function tearDown(): void
    {
        Env::getRepository()->set('RISK_TEST_MODE', '');
        Env::getRepository()->set('RISK_TEST_CREDIT_CENTS', '');
        parent::tearDown();
    }

    public function test_homologation_credit_is_available_only_to_risk_guard(): void
    {
        $risk = app(RiskGuard::class);

        $this->assertTrue($risk->isTestMode());
        $this->assertSame(500000, $risk->testCreditCents());
        $this->assertSame(500000, $risk->eligibleCash());
    }

    public function test_prize_credit_is_idempotent_and_withdrawal_can_be_simulated(): void
    {
        $user = $this->user();
        $payout = Payout::create(['user_id' => $user->id, 'bet_id' => $this->betFor($user)->id, 'amount_cents' => 120000, 'status' => 'manual_review', 'idempotency_key' => 'wallet-payout-1']);
        $wallets = app(WalletService::class);

        $wallets->creditPrize($payout);
        $wallets->creditPrize($payout);

        $this->assertDatabaseHas('wallets', ['user_id' => $user->id, 'balance_cents' => 120000, 'locked_cents' => 0]);
        $this->assertDatabaseCount('wallet_transactions', 1);

        $withdrawal = $wallets->requestWithdrawal($user, 30000, 'pix', 'teste@pix.local');
        $this->assertSame('manual_review', $withdrawal->status);
        $this->assertDatabaseHas('wallets', ['user_id' => $user->id, 'balance_cents' => 90000, 'locked_cents' => 30000]);

        $wallets->reviewWithdrawal($withdrawal, 'paid', 'Baixa simulada de homologação.');
        $this->assertDatabaseHas('wallet_withdrawals', ['id' => $withdrawal->id, 'status' => 'paid']);
        $this->assertDatabaseHas('wallets', ['user_id' => $user->id, 'balance_cents' => 90000, 'locked_cents' => 0]);
    }

    public function test_settlement_credits_a_winner_wallet_once(): void
    {
        $user = $this->user();
        $game = LotteryGame::create(['slug' => 'wallet-test', 'name' => 'Wallet Teste', 'short_name' => 'TESTE', 'color' => '#5c2db8', 'price_cents' => 500, 'numbers_required' => 6, 'range_max' => 60, 'number_min' => 1, 'selection_mode' => 'distinct', 'allow_repeated_numbers' => false, 'payout_rules' => ['6' => 2], 'max_prize_cents' => 500000, 'payout_ratio' => 0.70, 'active' => true]);
        $draw = Draw::create(['lottery_game_id' => $game->id, 'contest_number' => 777001, 'draw_at' => now()->subHour(), 'status' => 'result_received', 'results' => ['numbers' => [1, 2, 3, 4, 5, 6]], 'payout_cap_cents' => 500000]);
        Bet::create(['user_id' => $user->id, 'lottery_game_id' => $game->id, 'draw_id' => $draw->id, 'numbers' => [1, 2, 3, 4, 5, 6], 'amount_cents' => 500, 'potential_prize_cents' => 1000, 'payout_cents' => 0, 'status' => 'paid', 'payment_status' => 'succeeded', 'is_pool_share' => false, 'idempotency_key' => 'wallet-bet-1']);

        app(SettlementService::class)->settle($draw);

        $this->assertDatabaseHas('bets', ['id' => 1, 'status' => 'won', 'payout_cents' => 1000]);
        $this->assertDatabaseMissing('wallets', ['user_id' => $user->id]);
        $payout = Payout::where('bet_id', 1)->firstOrFail();
        $this->assertSame('manual_review', $payout->status);
        $this->assertTrue($payout->credit_available_at->isFuture());
        app(WalletService::class)->approvePrizeCredit($payout, $user, true);
        $this->assertDatabaseHas('wallets', ['user_id' => $user->id, 'balance_cents' => 1000]);
        $this->assertDatabaseCount('wallet_transactions', 1);
    }

    private function user(): User
    {
        return User::create(['name' => 'Wallet Teste', 'email' => 'wallet-'.uniqid().'@test.local', 'password' => Hash::make('secret'), 'portal' => 'cliente', 'active' => true]);
    }

    private function betFor(User $user): Bet
    {
        $game = LotteryGame::create(['slug' => 'wallet-payout-game', 'name' => 'Wallet Payout', 'short_name' => 'WP', 'color' => '#5c2db8', 'price_cents' => 100, 'numbers_required' => 1, 'range_max' => 10, 'number_min' => 1, 'selection_mode' => 'distinct', 'allow_repeated_numbers' => false, 'payout_rules' => ['1' => 1], 'max_prize_cents' => 100, 'payout_ratio' => 0.70, 'active' => true]);
        $draw = Draw::create(['lottery_game_id' => $game->id, 'contest_number' => random_int(100000, 999999), 'draw_at' => now()->addDay(), 'status' => 'open', 'payout_cap_cents' => 100]);
        return Bet::create(['user_id' => $user->id, 'lottery_game_id' => $game->id, 'draw_id' => $draw->id, 'numbers' => [1], 'amount_cents' => 100, 'potential_prize_cents' => 100, 'payout_cents' => 0, 'status' => 'won', 'payment_status' => 'succeeded', 'is_pool_share' => false, 'idempotency_key' => 'wallet-payout-bet']);
    }
}
