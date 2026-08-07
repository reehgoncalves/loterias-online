<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class MarketingCampaignMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public array $data, public string $template = 'draw-reminder') {}

    public function build(): static
    {
        $view = match ($this->template) {
            'jackpot-alert' => 'emails.marketing.jackpot-alert',
            'pool-highlight' => 'emails.marketing.pool-highlight',
            default => 'emails.marketing.draw-reminder',
        };

        return $this->subject($this->data['subject'] ?? 'Seu próximo jogo está chegando')
            ->view($view)
            ->with($this->data);
    }
}

