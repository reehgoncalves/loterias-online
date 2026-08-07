<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BetController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\StripeWebhookController;
use App\Http\Controllers\WalletController;
use Illuminate\Support\Facades\Route;

Route::get('v1/catalog', [CatalogController::class, 'index']);
Route::get('v1/testimonials', [CatalogController::class, 'testimonials']);
Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:auth');
Route::post('auth/register', [AuthController::class, 'register'])->middleware('throttle:auth');
Route::post('stripe/webhook', StripeWebhookController::class);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('v1/me', [AuthController::class, 'me']);
    Route::get('v1/profile', [ProfileController::class, 'show']);
    Route::get('v1/profile/payment-methods', [ProfileController::class, 'paymentMethods']);
    Route::post('v1/profile/setup-intent', [ProfileController::class, 'setupIntent']);
    Route::post('v1/profile/billing-portal', [ProfileController::class, 'billingPortal']);
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::get('v1/my-bets', [BetController::class, 'mine']);
    Route::post('v1/coupons/generate', [BetController::class, 'generateCoupons']);
    Route::post('v1/bets', [BetController::class, 'store']);
    Route::post('v1/payments/checkout', [PaymentController::class, 'checkout']);
    Route::post('v1/orders/checkout', [OrderController::class, 'checkout']);
    Route::get('v1/wallet', [WalletController::class, 'show']);
    Route::post('v1/wallet/withdrawals', [WalletController::class, 'withdraw']);
    Route::middleware('can:admin')->prefix('v1/admin')->group(function () {
        Route::get('dashboard', [AdminController::class, 'dashboard']);
        Route::get('bets', [AdminController::class, 'bets']);
        Route::get('payments', [AdminController::class, 'payments']);
        Route::get('pools', [AdminController::class, 'pools']);
        Route::get('wallet-withdrawals', [AdminController::class, 'walletWithdrawals']);
        Route::post('wallet-withdrawals/{withdrawal}/review', [AdminController::class, 'reviewWithdrawal']);
        Route::get('payouts', [AdminController::class, 'payouts']);
        Route::post('payouts/{payout}/approve', [AdminController::class, 'approvePayout']);
        Route::post('games/{game}/pause', [AdminController::class, 'pauseGame']);
    });
});
