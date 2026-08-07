<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('lottery_pools', function (Blueprint $table) { $table->unsignedInteger('reserved_shares')->default(0)->after('sold_shares'); });
        Schema::table('order_items', function (Blueprint $table) { $table->foreignId('lottery_pool_id')->nullable()->after('draw_id')->constrained('lottery_pools')->nullOnDelete(); $table->unsignedInteger('shares')->default(1)->after('amount_cents'); });
        Schema::table('pool_shares', function (Blueprint $table) { $table->foreignId('order_id')->nullable()->after('user_id')->constrained('orders')->nullOnDelete(); });
    }

    public function down(): void
    {
        Schema::table('pool_shares', function (Blueprint $table) { $table->dropForeign(['order_id']); $table->dropColumn('order_id'); });
        Schema::table('order_items', function (Blueprint $table) { $table->dropForeign(['lottery_pool_id']); $table->dropColumn(['lottery_pool_id', 'shares']); });
        Schema::table('lottery_pools', function (Blueprint $table) { $table->dropColumn('reserved_shares'); });
    }
};
