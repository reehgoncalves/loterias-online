<?php

namespace App\Console\Commands;

use App\Models\LotteryGame;
use App\Services\CaixaResultsClient;
use App\Services\LotteryResultImporter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncLotteryResults extends Command
{
    protected $signature = 'lottery:sync {--game= : Slug de uma modalidade}';
    protected $description = 'Sincroniza o último resultado publicado e agenda a liquidação idempotente.';
    public function handle(CaixaResultsClient $client, LotteryResultImporter $importer): int {
        if (! filter_var(env('LOTTERY_SYNC_ENABLED', true), FILTER_VALIDATE_BOOL)) { $this->warn('Sincronização desabilitada.'); return self::SUCCESS; }
        $games = LotteryGame::where('active',DB::raw('true'))->when($this->option('game'),fn($q)=>$q->where('slug',$this->option('game')))->get();
        foreach ($games as $game) {
            try {
                $result = $client->latest($game->slug);
                $draw = $importer->import($game, $result);
                $this->info("{$game->slug}: concurso {$result['contest_number']} sincronizado.");
            } catch (\Throwable $exception) { $this->error("{$game->slug}: {$exception->getMessage()}"); }
        }
        return self::SUCCESS;
    }
}
