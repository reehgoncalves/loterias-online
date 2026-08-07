<?php

namespace App\Mail;

use App\Models\Bet;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class BetConfirmationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Bet $bet) {}

    public function build(): static
    {
        return $this->subject('Cupom confirmado · '.$this->bet->game->name)
            ->view('emails.bets.confirmed')
            ->with(['bet' => $this->bet->loadMissing(['game', 'draw'])]);
    }
}

