@extends('emails.layout')

@section('hero')
<div class="eyebrow">Prêmio em destaque</div>
<h1>Hoje tem {{ $game_name }}.</h1>
@endsection

@section('content')
<p class="muted">O prêmio estimado chamou atenção: o próximo sorteio acontece em <strong>{{ $draw_at }}</strong>. Se quiser jogar, faça sua aposta com calma e respeite seus limites.</p>
<div class="highlight"><strong>{{ $jackpot }}</strong><span class="muted">Concurso {{ $contest_number }}</span></div>
<p style="text-align:center"><a class="button" href="{{ $cta_url }}">Jogar agora</a></p>
<p class="muted" style="font-size:12px">Aposte somente o que cabe no seu orçamento. Prêmios e resultados dependem do sorteio oficial.</p>
@endsection

