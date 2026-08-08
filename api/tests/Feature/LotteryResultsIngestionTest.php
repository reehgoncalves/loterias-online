<?php

namespace Tests\Feature;

use App\Jobs\SettleDrawBets;
use App\Models\Draw;
use App\Models\LotteryGame;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class LotteryResultsIngestionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        putenv('RESULTS_INGEST_SECRET=ingest-test-secret');
    }

    protected function tearDown(): void
    {
        putenv('RESULTS_INGEST_SECRET');
        parent::tearDown();
    }

    public function test_external_ingestion_requires_secret_and_imports_idempotently(): void
    {
        $game = LotteryGame::create([
            'slug' => 'mega-sena', 'name' => 'Mega-Sena', 'short_name' => 'MEGA',
            'color' => '#31b8b2', 'price_cents' => 600, 'numbers_required' => 6,
            'min_numbers' => 6, 'max_numbers' => 20, 'price_table' => config('lottery.official_rules.mega-sena.price_table'),
            'range_max' => 60, 'number_min' => 1, 'selection_mode' => 'distinct',
            'payout_rules' => [], 'max_prize_cents' => 1000000, 'active' => true,
        ]);
        $payload = [
            'source' => 'caixa', 'slug' => $game->slug, 'contest_number' => 5001,
            'draw_at' => '2026-08-08 20:00:00', 'numbers' => [1, 2, 3, 4, 5, 6],
            'raw' => ['numero' => 5001, 'dataApuracao' => '08/08/2026', 'listaDezenas' => ['01', '02', '03', '04', '05', '06']],
        ];

        $this->postJson('/api/internal/lottery-results', $payload)->assertUnauthorized();

        Queue::fake();
        $response = $this->withHeader('Authorization', 'Bearer ingest-test-secret')
            ->postJson('/api/internal/lottery-results', $payload)
            ->assertOk()
            ->assertJsonPath('data.slug', 'mega-sena')
            ->assertJsonPath('data.contest_number', 5001);

        $drawId = $response->json('data.id');
        $this->assertDatabaseHas('draws', ['id' => $drawId, 'contest_number' => 5001, 'status' => 'result_received']);
        Queue::assertPushed(SettleDrawBets::class, fn (SettleDrawBets $job) => $job->drawId === $drawId);

        $this->withHeader('Authorization', 'Bearer ingest-test-secret')
            ->postJson('/api/internal/lottery-results', $payload)
            ->assertOk()
            ->assertJsonPath('data.id', $drawId);

        $this->assertSame(1, Draw::where('lottery_game_id', $game->id)->where('contest_number', 5001)->count());
    }

    public function test_external_ingestion_rejects_inactive_or_unknown_games(): void
    {
        $payload = [
            'source' => 'caixa', 'slug' => 'unknown-game', 'contest_number' => 5002,
            'draw_at' => '2026-08-08 20:00:00', 'numbers' => [1, 2, 3], 'raw' => ['numero' => 5002],
        ];

        $this->withHeader('Authorization', 'Bearer ingest-test-secret')
            ->postJson('/api/internal/lottery-results', $payload)
            ->assertStatus(422)
            ->assertJsonPath('message', 'Modalidade não encontrada ou inativa.');
    }
}
