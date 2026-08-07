<?php

namespace App\Console\Commands;

use App\Mail\MarketingCampaignMail;
use App\Models\Draw;
use App\Models\EmailDelivery;
use App\Models\MarketingCampaign;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

class SendLotteryMarketing extends Command
{
    protected $signature = 'marketing:send {--window=24h : Janela 2h ou 24h} {--template=draw-reminder : Template de e-mail}';
    protected $description = 'Enfileira e-mails de marketing apenas para clientes com opt-in e sem duplicidade.';

    public function handle(): int
    {
        $window = $this->option('window') === '2h' ? 2 : 24;
        $template = in_array($this->option('template'), ['draw-reminder', 'jackpot-alert', 'pool-highlight'], true) ? $this->option('template') : 'draw-reminder';
        $draws = Draw::query()->with('game')->where('status', 'open')->whereBetween('draw_at', [now(), now()->addHours($window)])->get();
        $users = User::query()->where('active', DB::raw('true'))->where('marketing_opt_in', DB::raw('true'))->whereNull('marketing_opted_out_at')->whereNotNull('email_verified_at')->get();
        $queued = 0;

        foreach ($draws as $draw) {
            $campaign = MarketingCampaign::firstOrCreate(
                ['slug' => "{$template}-draw-{$draw->id}-".now()->format('YmdH')],
                ['template' => $template, 'subject' => $this->subject($draw->game->name, $template), 'window' => $window.'h', 'active' => DB::raw('true'), 'scheduled_at' => now()],
            );
            foreach ($users as $user) {
                $key = "marketing-{$template}-{$draw->id}-{$user->id}";
                if (EmailDelivery::where('idempotency_key', $key)->exists()) continue;
                $delivery = EmailDelivery::create(['marketing_campaign_id' => $campaign->id, 'user_id' => $user->id, 'draw_id' => $draw->id, 'type' => 'marketing', 'status' => 'queued', 'idempotency_key' => $key]);
                $payload = [
                    'subject' => $campaign->subject,
                    'customer_name' => $user->name,
                    'game_name' => $draw->game->name,
                    'contest_number' => $draw->contest_number,
                    'draw_at' => $draw->draw_at->timezone(config('app.timezone'))->format('d/m/Y às H:i'),
                    'jackpot' => $this->jackpot($draw),
                    'pool_name' => 'Bolão '.$draw->game->name,
                    'share_price' => 'Cotas a partir de R$ 7,90',
                    'cta_url' => rtrim((string) env('FRONTEND_URL', 'http://127.0.0.1:5173'), '/').'/jogos/'.$draw->game->slug.'?draw='.$draw->id,
                    'unsubscribe_url' => URL::signedRoute('marketing.unsubscribe', ['user' => $user->id]),
                ];
                try {
                    Mail::to($user->email)->queue(new MarketingCampaignMail($payload, $template));
                    $delivery->update(['status' => 'queued']);
                    $queued++;
                } catch (\Throwable $exception) {
                    $delivery->update(['status' => 'failed', 'error' => substr($exception->getMessage(), 0, 500)]);
                }
            }
            $campaign->update(['sent_at' => now()]);
        }

        $this->info("{$queued} e-mails enfileirados para {$draws->count()} concurso(s).");
        return self::SUCCESS;
    }

    private function subject(string $game, string $template): string
    {
        return match ($template) {
            'jackpot-alert' => "Hoje tem {$game} · confira seu jogo",
            'pool-highlight' => "Bolão {$game} · cotas disponíveis",
            default => "Seu próximo {$game} está chegando",
        };
    }

    private function jackpot(Draw $draw): string
    {
        $value = data_get($draw->raw_payload, 'valorEstimadoProximoConcurso');
        return $value ? 'R$ '.number_format((float) $value, 2, ',', '.') : 'Prêmio estimado no portal oficial';
    }
}
