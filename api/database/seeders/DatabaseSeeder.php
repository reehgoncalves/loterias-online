<?php

namespace Database\Seeders;

use App\Models\Bet;
use App\Models\Draw;
use App\Models\LedgerEntry;
use App\Models\LotteryGame;
use App\Models\LotteryPool;
use App\Models\Payment;
use App\Models\Testimonial;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $password = env('SEED_DEFAULT_PASSWORD', 'Loterias@2026!');
        $now = now();
        $boolean = static fn (bool $value) => DB::raw($value ? 'true' : 'false');
        DB::table('users')->updateOrInsert(['email'=>'admin@loterias.online'], ['name'=>'Admin Loterias Online','password'=>Hash::make($password),'portal'=>'admin','is_admin'=>$boolean(true),'active'=>$boolean(true),'marketing_opt_in'=>$boolean(false),'email_verified_at'=>$now,'age_confirmed_at'=>$now,'terms_accepted_at'=>$now,'terms_version'=>config('legal.terms_version', 'v1.0'),'updated_at'=>$now,'created_at'=>$now]);
        DB::table('users')->updateOrInsert(['email'=>'cliente@loterias.online'], ['name'=>'Cliente Demonstração','password'=>Hash::make($password),'portal'=>'cliente','is_admin'=>$boolean(false),'active'=>$boolean(true),'marketing_opt_in'=>$boolean(true),'email_verified_at'=>$now,'age_confirmed_at'=>$now,'terms_accepted_at'=>$now,'terms_version'=>config('legal.terms_version', 'v1.0'),'updated_at'=>$now,'created_at'=>$now]);
        $customer = User::where('email', 'cliente@loterias.online')->firstOrFail();

        $games = [
            ['slug'=>'mega-sena','name'=>'Mega-Sena','short_name'=>'MEGA','color'=>'#31b8b2','numbers_required'=>6,'range_max'=>60,'payout_rules'=>['4'=>20,'5'=>100,'6'=>1000],'max_prize_cents'=>50000000],
            ['slug'=>'lotofacil','name'=>'Lotofácil','short_name'=>'FÁCIL','color'=>'#8c5be5','numbers_required'=>15,'range_max'=>25,'payout_rules'=>['11'=>5,'12'=>10,'13'=>50,'14'=>250,'15'=>1000],'max_prize_cents'=>30000000],
            ['slug'=>'quina','name'=>'Quina','short_name'=>'QUINA','color'=>'#ef9151','numbers_required'=>5,'range_max'=>80,'payout_rules'=>['2'=>3,'3'=>15,'4'=>100,'5'=>1000],'max_prize_cents'=>20000000],
            ['slug'=>'timemania','name'=>'Timemania','short_name'=>'TIME','color'=>'#f05295','numbers_required'=>10,'range_max'=>80,'special_options'=>['special_type'=>'team'],'payout_rules'=>['3'=>2,'4'=>8,'5'=>40,'6'=>250,'7'=>1000],'max_prize_cents'=>10000000],
            ['slug'=>'dia-de-sorte','name'=>'Dia de Sorte','short_name'=>'DIA','color'=>'#f1b833','numbers_required'=>7,'range_max'=>31,'special_options'=>['special_type'=>'month'],'payout_rules'=>['4'=>3,'5'=>12,'6'=>75,'7'=>600],'max_prize_cents'=>5000000],
            ['slug'=>'dupla-sena','name'=>'Dupla Sena','short_name'=>'DUPLA','color'=>'#3d8de5','numbers_required'=>6,'range_max'=>50,'payout_rules'=>['3'=>3,'4'=>15,'5'=>100,'6'=>800],'max_prize_cents'=>10000000],
            ['slug'=>'lotomania','name'=>'Lotomania','short_name'=>'LOTO','color'=>'#e061b7','numbers_required'=>50,'range_max'=>99,'number_min'=>0,'payout_rules'=>['0'=>20,'15'=>10,'16'=>20,'17'=>60,'18'=>300,'19'=>2000,'20'=>10000],'max_prize_cents'=>30000000],
            ['slug'=>'super-sete','name'=>'Super Sete','short_name'=>'7','color'=>'#41a86d','numbers_required'=>7,'range_max'=>9,'number_min'=>0,'allow_repeated_numbers'=>true,'selection_mode'=>'columns','special_options'=>['columns'=>7,'special_type'=>'columns'],'payout_rules'=>['3'=>3,'4'=>15,'5'=>100,'6'=>800,'7'=>5000],'max_prize_cents'=>20000000],
        ];
        foreach ($games as $index => $payload) {
            $official = config('lottery.official_rules.'.$payload['slug'], []);
            $payload = array_merge($payload, ['price_cents'=>(int) ($official['price_table'][$payload['numbers_required']] ?? 0), 'min_numbers'=>$official['min_numbers'] ?? $payload['numbers_required'], 'max_numbers'=>$official['max_numbers'] ?? $payload['numbers_required'], 'price_table'=>$official['price_table'] ?? null, 'rules_source_url'=>$official['source_url'] ?? null, 'rules_version'=>'caixa-2026-08']);
            $allowRepeated = (bool) ($payload['allow_repeated_numbers'] ?? false);
            unset($payload['allow_repeated_numbers']);
            $game = LotteryGame::updateOrCreate(['slug'=>$payload['slug']], $payload);
            DB::table('lottery_games')->where('id', $game->id)->update(['allow_repeated_numbers'=>$boolean($allowRepeated),'active'=>$boolean(true)]);
            $draw = Draw::updateOrCreate(['lottery_game_id'=>$game->id,'contest_number'=>3000+$index], ['draw_at'=>now()->addDays(1+($index%3))->setTime(21,0),'sales_close_at'=>now()->addDays(1+($index%3))->setTime(19,30),'status'=>'open','payout_cap_cents'=>$game->max_prize_cents]);
            if ($index < 3) {
                $lines = match ($game->slug) {
                    'mega-sena' => [[4,11,19,27,42,58],[4,11,19,27,42,60],[4,11,19,27,45,58],[4,11,22,27,42,58],[4,15,19,27,42,58],[7,11,19,27,42,58],[4,11,19,33,42,58]],
                    'lotofacil' => [[1,3,5,7,8,10,12,14,16,18,20,21,22,24,25],[1,2,5,7,9,11,13,14,16,18,20,21,23,24,25],[2,3,4,8,10,12,15,17,19,20,21,22,23,24,25]],
                    default => [[7,18,29,44,73],[2,15,31,55,78],[11,24,36,49,67],[4,19,28,52,80]],
                };
                LotteryPool::updateOrCreate(['lottery_game_id'=>$game->id,'draw_id'=>$draw->id,'name'=>['Mega-Sena'=>'Milionário da Semana','Lotofácil'=>'Fácil Premiado','Quina'=>'Quina Turbo'][$game->name]], ['description'=>'Cota da plataforma com todas as linhas, números, concurso e disponibilidade visíveis antes da compra.','lines'=>$lines,'numbers_count'=>count($lines[0]),'share_price_cents'=>($index+1)*790,'total_shares'=>100+$index*50,'sold_shares'=>42+$index*18,'total_stake_cents'=>50000]);
            }
        }

        $sampleGame = LotteryGame::where('slug','mega-sena')->firstOrFail();
        $sampleBetAmount = $sampleGame->price_cents;
        $sampleDraw = Draw::updateOrCreate(['lottery_game_id'=>$sampleGame->id,'contest_number'=>2999], ['draw_at'=>now()->subDays(2)->setTime(20,0),'status'=>'settled','results'=>['numbers'=>[1,2,3,4,5,6]],'payout_cap_cents'=>$sampleGame->max_prize_cents,'synced_at'=>now()]);
        // Fixture exclusivamente visual: o prêmio demonstrativo fica abaixo do caixa seed.
        // A liquidação real usa SettlementService + RiskGuard e pode parar em manual_review.
        $sample = [['numbers'=>[1,2,3,4,5,6],'status'=>'won','payout_cents'=>250],['numbers'=>[9,10,11,12,13,14],'status'=>'lost','payout_cents'=>0],['numbers'=>[21,22,23,24,25,26],'status'=>'paid','payout_cents'=>0]];
        foreach ($sample as $index => $fixture) {
            $bet = Bet::updateOrCreate(['idempotency_key'=>'seed-bet-'.$index], ['user_id'=>$customer->id,'lottery_game_id'=>$sampleGame->id,'draw_id'=>$sampleDraw->id,'numbers'=>$fixture['numbers'],'amount_cents'=>$sampleBetAmount,'potential_prize_cents'=>500000,'payout_cents'=>$fixture['payout_cents'],'status'=>$fixture['status'],'payment_status'=>'succeeded','paid_at'=>now()->subDays(2),'settled_at'=>now()->subDay(),'won_at'=>$fixture['status']==='won'?now()->subDay():null]);
            $payment = Payment::updateOrCreate(['idempotency_key'=>'seed-payment-'.$index], ['user_id'=>$customer->id,'bet_id'=>$bet->id,'provider'=>'stripe','provider_payment_id'=>'pi_demo_'.$index,'method'=>$index===0?'pix':'card','amount_cents'=>$sampleBetAmount,'currency'=>'brl','status'=>'succeeded','paid_at'=>now()->subDays(2),'raw_payload'=>['demo'=>true]]);
            LedgerEntry::updateOrCreate(['idempotency_key'=>'seed-ledger-payment-'.$index], ['user_id'=>$customer->id,'bet_id'=>$bet->id,'payment_id'=>$payment->id,'type'=>'payment_confirmed','amount_cents'=>$sampleBetAmount,'status'=>'posted','metadata'=>['demo_fixture'=>true]]);
            if ($fixture['payout_cents'] > 0) LedgerEntry::updateOrCreate(['idempotency_key'=>'seed-ledger-payout-'.$index], ['user_id'=>$customer->id,'bet_id'=>$bet->id,'type'=>'payout_reserved','amount_cents'=>$fixture['payout_cents'],'status'=>'posted','metadata'=>['demo_fixture'=>true]]);
        }

        foreach ([['Camila R.','Junho · demonstração','O fluxo é leve e eu consigo conferir todas as minhas apostas sem perder o horário do sorteio.'],['Bruno M.','Maio · demonstração','Entrei em um bolão e gostei de ver as cotas, o valor e o status em uma tela só.'],['Lívia S.','Abril · demonstração','A experiência é simples até para escolher os números e finalizar o pedido.']] as [$name,$month,$quote]) {
            DB::table('testimonials')->updateOrInsert(['name'=>$name], ['month'=>$month,'quote'=>$quote,'is_demo'=>$boolean(true),'active'=>$boolean(true),'updated_at'=>$now,'created_at'=>$now]);
        }
    }
}
