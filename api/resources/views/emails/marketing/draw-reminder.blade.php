@extends('emails.layout')

@section('hero')
<div class="eyebrow">Lembrete do próximo concurso</div>
<h1>{{ $customer_name ?? 'Olá' }}, seu próximo jogo está chegando.</h1>
@endsection

@section('content')
<p class="muted">O concurso <strong>{{ $contest_number }}</strong> da <strong>{{ $game_name }}</strong> acontece em <strong>{{ $draw_at }}</strong>. Confira os números e participe dentro do prazo oficial.</p>
<div class="highlight"><strong>{{ $jackpot ?? 'Confira o prêmio' }}</strong><span class="muted">estimativa informada para o concurso</span></div>
<p style="text-align:center"><a class="button" href="{{ $cta_url }}">Escolher meus números</a></p>
<p class="muted" style="font-size:12px">A seleção aleatória é apenas uma conveniência. Todos os números válidos possuem a mesma probabilidade.</p>
@endsection

