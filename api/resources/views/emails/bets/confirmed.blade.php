@extends('emails.layout')

@section('hero')
<div class="eyebrow">Pagamento confirmado</div>
<h1>Seu cupom está registrado.</h1>
@endsection

@section('content')
<p class="muted">A aposta em <strong>{{ $bet->game->name }}</strong> foi confirmada para o concurso <strong>{{ $bet->draw->contest_number }}</strong>.</p>
<div class="numbers">{{ implode(' · ', array_map(fn ($number) => str_pad((string) $number, 2, '0', STR_PAD_LEFT), $bet->numbers)) }}</div>
<p class="muted" style="font-size:12px">Valor: {{ number_format($bet->amount_cents / 100, 2, ',', '.') }} · Guarde este e-mail para consultar o status da aposta.</p>
<p style="text-align:center"><a class="button" href="{{ $cta_url ?? config('app.frontend_url', env('FRONTEND_URL', '/')) }}">Acompanhar minhas apostas</a></p>
@endsection

