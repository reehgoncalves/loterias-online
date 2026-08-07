<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use App\Models\User;

Route::get('/', fn () => response()->json(['name' => 'Loterias Online API', 'status' => 'ok', 'environment' => app()->environment()]));

Route::get('/marketing/unsubscribe/{user}', function (User $user) {
    DB::table('users')->where('id', $user->id)->update(['marketing_opt_in' => DB::raw('false'), 'marketing_opted_out_at' => now()]);
    return response('<!doctype html><meta charset="utf-8"><title>Preferências atualizadas</title><body style="font-family:Arial;padding:48px;text-align:center"><h1>Preferência atualizada</h1><p>Você não receberá mais e-mails de marketing da Loterias Online.</p></body>');
})->middleware('signed')->name('marketing.unsubscribe');
