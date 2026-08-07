<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('total_cents');
            $table->string('currency', 3)->default('brl');
            $table->string('status')->default('awaiting_payment');
            $table->string('payment_status')->default('pending');
            $table->string('provider_checkout_id')->nullable()->unique();
            $table->string('idempotency_key')->unique();
            $table->json('raw_payload')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status']);
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lottery_game_id')->constrained('lottery_games');
            $table->foreignId('draw_id')->constrained('draws');
            $table->json('numbers');
            $table->unsignedInteger('amount_cents');
            $table->unsignedBigInteger('potential_prize_cents')->default(0);
            $table->timestamps();
        });

        Schema::table('bets', function (Blueprint $table) {
            $table->foreignId('order_id')->nullable()->after('user_id')->constrained('orders')->nullOnDelete();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('order_id')->nullable()->after('user_id')->constrained('orders')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) { $table->dropForeign(['order_id']); $table->dropColumn('order_id'); });
        Schema::table('bets', function (Blueprint $table) { $table->dropForeign(['order_id']); $table->dropColumn('order_id'); });
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
