<?php

use Illuminate\Support\Facades\Route;

Route::get('/', fn () => response()->json(['name' => 'Loterias Online API', 'status' => 'ok', 'environment' => app()->environment()]));

