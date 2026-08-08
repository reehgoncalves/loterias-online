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
use App\Models\LotteryGame;
use App\Services\LotteryResultImporter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;

Route::get('v1/catalog', [CatalogController::class, 'index']);
Route::get('v1/pools', [CatalogController::class, 'pools']);
Route::get('v1/testimonials', [CatalogController::class, 'testimonials']);
Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:auth');
Route::post('auth/register', [AuthController::class, 'register'])->middleware('throttle:auth');
Route::post('stripe/webhook', StripeWebhookController::class);

// GitHub Actions is the external collector used when the CAIXA service blocks
// Vercel serverless egress. It accepts only normalized official payloads and
// never exposes a public command runner or a database write surface.
Route::post('internal/lottery-results', function (Request $request, LotteryResultImporter $importer) {
    $secret = (string) env('RESULTS_INGEST_SECRET');
    $authorization = (string) $request->header('Authorization');

    if ($secret === '' || ! hash_equals('Bearer '.$secret, $authorization)) {
        return response()->json(['message' => 'Não autorizado.'], 401);
    }

    $payload = $request->validate([
        'source' => ['required', Rule::in(['caixa'])],
        'slug' => ['required', 'string', 'max:80'],
        'contest_number' => ['required', 'integer', 'min:1'],
        'draw_at' => ['required', 'date'],
        'numbers' => ['required', 'array', 'min:1', 'max:60'],
        'numbers.*' => ['required', 'integer', 'min:0', 'max:99'],
        'special' => ['nullable', 'string', 'max:120'],
        'next_contest_number' => ['nullable', 'integer', 'min:1'],
        'next_draw_at' => ['nullable', 'date'],
        'raw' => ['required', 'array'],
    ]);

    $game = LotteryGame::query()
        ->where('slug', $payload['slug'])
        ->where('active', DB::raw('true'))
        ->first();

    if (! $game) {
        return response()->json(['message' => 'Modalidade não encontrada ou inativa.'], 422);
    }

    $draw = $importer->import($game, $payload);

    return response()->json([
        'data' => [
            'id' => $draw->id,
            'slug' => $game->slug,
            'contest_number' => $draw->contest_number,
            'status' => $draw->status,
            'synced_at' => $draw->synced_at,
        ],
    ]);
});

// Vercel invokes this endpoint from its Cron integration. Keep the scheduler
// behind a secret so it cannot be used as a public command runner.
Route::get('cron/schedule', function (Request $request) {
    $secret = (string) env('CRON_SECRET');
    $authorization = (string) $request->header('Authorization');

    if ($secret === '' || ! hash_equals('Bearer '.$secret, $authorization)) {
        return response()->json(['message' => 'Não autorizado.'], 401);
    }

    Artisan::call('schedule:run');

    return response()->json([
        'ok' => true,
        'ran_at' => now()->toIso8601String(),
        'output' => trim(Artisan::output()),
    ]);
});

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
        Route::get('results', [AdminController::class, 'results']);
        Route::post('results/sync', [AdminController::class, 'syncResults']);
        Route::get('prices', [AdminController::class, 'prices']);
        Route::put('games/{game}/prices', [AdminController::class, 'updatePrices']);
        Route::get('wallet-withdrawals', [AdminController::class, 'walletWithdrawals']);
        Route::post('wallet-withdrawals/{withdrawal}/review', [AdminController::class, 'reviewWithdrawal']);
        Route::get('payouts', [AdminController::class, 'payouts']);
        Route::post('payouts/{payout}/approve', [AdminController::class, 'approvePayout']);
        Route::post('games/{game}/pause', [AdminController::class, 'pauseGame']);
    });
});
