@extends('emails.layout')

@section('hero')
<div class="eyebrow">Bolão aberto</div>
<h1>Mais combinações, uma experiência simples.</h1>
@endsection

@section('content')
<p class="muted">O bolão <strong>{{ $pool_name ?? 'em destaque' }}</strong> está com cotas disponíveis para o concurso {{ $contest_number }} da {{ $game_name }}.</p>
<div class="highlight"><strong>{{ $share_price ?? 'Consulte as cotas' }}</strong><span class="muted">por cota · {{ $draw_at }}</span></div>
<p style="text-align:center"><a class="button" href="{{ $cta_url }}">Ver bolões disponíveis</a></p>
<p class="muted" style="font-size:12px">Cotas, regras e divisão de eventual prêmio devem ser conferidas antes da confirmação.</p>
@endsection

