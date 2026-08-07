<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BetController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\StripeWebhookController;
use Illuminate\Support\Facades\Route;

Route::get('v1/catalog', [CatalogController::class, 'index']);
Route::get('v1/testimonials', [CatalogController::class, 'testimonials']);
Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:auth');
Route::post('stripe/webhook', StripeWebhookController::class);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('v1/me', [AuthController::class, 'me']);
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::get('v1/my-bets', [BetController::class, 'mine']);
    Route::post('v1/bets', [BetController::class, 'store']);
    Route::post('v1/payments/checkout', [PaymentController::class, 'checkout']);
    Route::middleware('can:admin')->prefix('v1/admin')->group(function () {
        Route::get('dashboard', [AdminController::class, 'dashboard']);
        Route::get('bets', [AdminController::class, 'bets']);
        Route::get('payments', [AdminController::class, 'payments']);
        Route::get('pools', [AdminController::class, 'pools']);
        Route::post('games/{game}/pause', [AdminController::class, 'pauseGame']);
    });
});

