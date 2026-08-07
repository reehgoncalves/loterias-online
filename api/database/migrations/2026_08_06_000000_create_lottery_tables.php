<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('lottery_games', function (Blueprint $table) {
            $table->id(); $table->string('slug')->unique(); $table->string('name'); $table->string('short_name'); $table->string('color')->default('#5c2db8'); $table->unsignedInteger('price_cents'); $table->unsignedSmallInteger('numbers_required'); $table->unsignedSmallInteger('range_max'); $table->json('payout_rules'); $table->unsignedBigInteger('max_prize_cents')->default(0); $table->decimal('payout_ratio', 5, 2)->default(0.70); $table->boolean('active')->default(true); $table->timestamps();
        });
        Schema::create('draws', function (Blueprint $table) {
            $table->id(); $table->foreignId('lottery_game_id')->constrained('lottery_games'); $table->unsignedInteger('contest_number'); $table->timestamp('draw_at'); $table->string('status')->default('open'); $table->json('results')->nullable(); $table->json('raw_payload')->nullable(); $table->string('result_hash', 64)->nullable(); $table->timestamp('synced_at')->nullable(); $table->unsignedBigInteger('payout_cap_cents')->default(0); $table->timestamps(); $table->unique(['lottery_game_id', 'contest_number']); $table->index(['status', 'draw_at']);
        });
        Schema::create('bets', function (Blueprint $table) {
            $table->id(); $table->foreignId('user_id')->constrained(); $table->foreignId('lottery_game_id')->constrained('lottery_games'); $table->foreignId('draw_id')->constrained('draws'); $table->json('numbers'); $table->unsignedInteger('amount_cents'); $table->unsignedBigInteger('potential_prize_cents')->default(0); $table->unsignedBigInteger('payout_cents')->default(0); $table->string('status')->default('awaiting_payment'); $table->string('payment_status')->default('pending'); $table->string('idempotency_key')->unique(); $table->timestamp('paid_at')->nullable(); $table->timestamp('settled_at')->nullable(); $table->timestamp('won_at')->nullable(); $table->text('settlement_note')->nullable(); $table->timestamps(); $table->index(['draw_id', 'status']);
        });
        Schema::create('lottery_pools', function (Blueprint $table) {
            $table->id(); $table->foreignId('lottery_game_id')->constrained('lottery_games'); $table->foreignId('draw_id')->constrained('draws'); $table->string('name'); $table->text('description')->nullable(); $table->unsignedInteger('share_price_cents'); $table->unsignedInteger('total_shares'); $table->unsignedInteger('sold_shares')->default(0); $table->unsignedBigInteger('total_stake_cents')->default(0); $table->string('status')->default('open'); $table->timestamps();
        });
        Schema::create('pool_shares', function (Blueprint $table) {
            $table->id(); $table->foreignId('lottery_pool_id')->constrained('lottery_pools')->cascadeOnDelete(); $table->foreignId('user_id')->constrained(); $table->unsignedInteger('shares'); $table->unsignedInteger('amount_cents'); $table->string('status')->default('reserved'); $table->timestamps();
        });
        Schema::create('payments', function (Blueprint $table) {
            $table->id(); $table->foreignId('user_id')->constrained(); $table->foreignId('bet_id')->nullable()->constrained('bets'); $table->string('provider')->default('stripe'); $table->string('provider_payment_id')->nullable()->unique(); $table->string('provider_checkout_id')->nullable()->unique(); $table->string('method'); $table->unsignedInteger('amount_cents'); $table->string('currency', 3)->default('brl'); $table->string('status')->default('pending'); $table->json('raw_payload')->nullable(); $table->timestamp('paid_at')->nullable(); $table->string('idempotency_key')->unique(); $table->timestamps(); $table->index(['status', 'method']);
        });
        Schema::create('payouts', function (Blueprint $table) {
            $table->id(); $table->foreignId('user_id')->constrained(); $table->foreignId('bet_id')->constrained('bets'); $table->unsignedBigInteger('amount_cents'); $table->string('status')->default('manual_review'); $table->string('idempotency_key')->unique(); $table->timestamp('approved_at')->nullable(); $table->timestamp('paid_at')->nullable(); $table->text('review_note')->nullable(); $table->timestamps();
        });
        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->id(); $table->foreignId('user_id')->nullable()->constrained(); $table->foreignId('bet_id')->nullable()->constrained('bets'); $table->foreignId('payment_id')->nullable()->constrained('payments'); $table->string('type'); $table->bigInteger('amount_cents'); $table->string('currency', 3)->default('brl'); $table->string('status')->default('posted'); $table->string('idempotency_key')->unique(); $table->json('metadata')->nullable(); $table->timestamps();
        });
        Schema::create('testimonials', function (Blueprint $table) {
            $table->id(); $table->string('name'); $table->string('month'); $table->text('quote'); $table->string('avatar_url')->nullable(); $table->boolean('is_demo')->default(true); $table->boolean('active')->default(true); $table->timestamps();
        });
    }
    public function down(): void { foreach (['ledger_entries', 'payouts', 'payments', 'pool_shares', 'lottery_pools', 'bets', 'draws', 'lottery_games', 'testimonials'] as $table) Schema::dropIfExists($table); }
};

