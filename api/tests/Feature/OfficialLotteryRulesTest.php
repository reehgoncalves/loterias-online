<?php

namespace Tests\Feature;

use App\Models\LotteryGame;
use App\Models\Draw;
use App\Models\User;
use App\Services\CouponGenerator;
use App\Services\LotteryRules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OfficialLotteryRulesTest extends TestCase
{
    use RefreshDatabase;

    public function test_variable_number_games_use_the_official_price_table(): void
    {
        $game = LotteryGame::create(['slug'=>'mega-sena','name'=>'Mega-Sena','short_name'=>'MEGA','color'=>'#31b8b2','price_cents'=>600,'numbers_required'=>6,'min_numbers'=>6,'max_numbers'=>20,'price_table'=>config('lottery.official_rules.mega-sena.price_table'),'range_max'=>60,'number_min'=>1,'selection_mode'=>'distinct','payout_rules'=>['4'=>20,'5'=>100,'6'=>1000],'max_prize_cents'=>1000000,'active'=>true]);
        $rules = app(LotteryRules::class);
        $numbers = app(CouponGenerator::class)->validate($game, [1,2,3,4,5,6,7]);

        $this->assertCount(7, $numbers);
        $this->assertSame(4200, $rules->priceFor($game, count($numbers)));
        $this->assertSame(600, $rules->priceFor($game, 6));
        $this->expectException(ValidationException::class);
        app(CouponGenerator::class)->validate($game, [1,2,3,4,5]);
    }

    public function test_lotomania_requires_exactly_fifty_numbers(): void
    {
        $game = LotteryGame::create(['slug'=>'lotomania','name'=>'Lotomania','short_name'=>'LOTO','color'=>'#e061b7','price_cents'=>300,'numbers_required'=>50,'min_numbers'=>50,'max_numbers'=>50,'price_table'=>[50=>300],'range_max'=>99,'number_min'=>0,'selection_mode'=>'distinct','payout_rules'=>[],'max_prize_cents'=>1000000,'active'=>true]);
        $numbers = app(CouponGenerator::class)->validate($game, range(0,49));
        $this->assertCount(50, $numbers);
        $this->assertSame(300, app(LotteryRules::class)->priceFor($game, 50));
    }

    public function test_super_sete_accepts_multiple_digits_per_column_and_charges_by_total(): void
    {
        $game = LotteryGame::create(['slug'=>'super-sete','name'=>'Super Sete','short_name'=>'7','color'=>'#41a86d','price_cents'=>300,'numbers_required'=>7,'min_numbers'=>7,'max_numbers'=>21,'price_table'=>config('lottery.official_rules.super-sete.price_table'),'range_max'=>9,'number_min'=>0,'allow_repeated_numbers'=>true,'selection_mode'=>'columns','special_options'=>['columns'=>7],'payout_rules'=>[],'max_prize_cents'=>1000000,'active'=>true]);
        $columns = [[0,1],[2],[3],[4],[5],[6],[7]];
        $validated = app(CouponGenerator::class)->validate($game, $columns);

        $this->assertSame(8, app(LotteryRules::class)->numberCount($validated, $game));
        $this->assertSame(600, app(LotteryRules::class)->priceFor($game, 8));
    }

    public function test_admin_can_adjust_selling_prices_but_not_below_official_floor(): void
    {
        $admin = User::create(['name'=>'Admin','email'=>'prices@test.local','password'=>Hash::make('secret'),'portal'=>'admin','is_admin'=>true,'active'=>true]);
        $game = LotteryGame::create(['slug'=>'mega-sena','name'=>'Mega-Sena','short_name'=>'MEGA','color'=>'#31b8b2','price_cents'=>600,'numbers_required'=>6,'min_numbers'=>6,'max_numbers'=>20,'price_table'=>config('lottery.official_rules.mega-sena.price_table'),'range_max'=>60,'number_min'=>1,'selection_mode'=>'distinct','payout_rules'=>[],'max_prize_cents'=>1000000,'active'=>true]);

        $this->actingAs($admin)->getJson('/api/v1/admin/prices')->assertOk()->assertJsonPath('data.0.official_price_table.6', 600);
        $prices = config('lottery.official_rules.mega-sena.price_table');
        $prices['6'] = 650;
        $this->actingAs($admin)->putJson('/api/v1/admin/games/'.$game->id.'/prices', ['prices'=>$prices])->assertOk()->assertJsonPath('data.price_table.6', 650);
        $this->assertSame(650, $game->fresh()->selling_price_table['6']);

        $prices['6'] = 599;
        $this->actingAs($admin)->putJson('/api/v1/admin/games/'.$game->id.'/prices', ['prices'=>$prices])->assertStatus(422)->assertJsonPath('message', fn (string $message) => str_contains($message, 'piso oficial'));
    }

    public function test_admin_sync_imports_official_result_and_exposes_winners_data(): void
    {
        Http::fake(['https://servicebus2.caixa.gov.br/portaldeloterias/api/mega-sena/' => Http::response([
            'numero' => 4001,
            'dataApuracao' => '08/08/2026',
            'listaDezenas' => ['01','02','03','04','05','06'],
        ], 200)]);
        $admin = User::create(['name'=>'Admin Resultados','email'=>'results@test.local','password'=>Hash::make('secret'),'portal'=>'admin','is_admin'=>true,'active'=>true]);
        $game = LotteryGame::create(['slug'=>'mega-sena','name'=>'Mega-Sena','short_name'=>'MEGA','color'=>'#31b8b2','price_cents'=>600,'numbers_required'=>6,'min_numbers'=>6,'max_numbers'=>20,'price_table'=>config('lottery.official_rules.mega-sena.price_table'),'range_max'=>60,'number_min'=>1,'selection_mode'=>'distinct','payout_rules'=>[],'max_prize_cents'=>1000000,'active'=>true]);
        Draw::create(['lottery_game_id'=>$game->id,'contest_number'=>4000,'draw_at'=>now()->subDay(),'status'=>'open','payout_cap_cents'=>1000000]);

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/v1/admin/results/sync', ['game'=>'mega-sena'])->assertOk();
        $response->assertJsonPath('data.0.game.slug', 'mega-sena')->assertJsonPath('data.0.contest_number', 4001)->assertJsonPath('data.0.results.numbers.0', 1);
        $this->assertDatabaseHas('draws', ['lottery_game_id'=>$game->id,'contest_number'=>4001,'status'=>'settled']);
    }
}
