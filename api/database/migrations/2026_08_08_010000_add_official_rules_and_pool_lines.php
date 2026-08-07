<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('lottery_games', function (Blueprint $table): void { $table->unsignedSmallInteger('min_numbers')->default(1)->after('numbers_required'); $table->unsignedSmallInteger('max_numbers')->default(1)->after('min_numbers'); $table->json('price_table')->nullable()->after('price_cents'); $table->json('rule_metadata')->nullable()->after('special_options'); $table->string('rules_source_url')->nullable()->after('rule_metadata'); $table->string('rules_version')->nullable()->after('rules_source_url'); });
        Schema::table('draws', fn (Blueprint $table) => $table->timestamp('sales_close_at')->nullable()->after('draw_at'));
        Schema::table('lottery_pools', function (Blueprint $table): void { $table->json('lines')->nullable()->after('description'); $table->unsignedSmallInteger('numbers_count')->default(1)->after('lines'); });
        Schema::table('bets', function (Blueprint $table): void { $table->string('special_value')->nullable()->after('numbers'); $table->foreignId('pool_share_id')->nullable()->after('is_pool_share')->constrained('pool_shares')->nullOnDelete(); });
        Schema::table('order_items', fn (Blueprint $table) => $table->string('special_value')->nullable()->after('numbers'));
    }

    public function down(): void
    {
        Schema::table('order_items', fn (Blueprint $table) => $table->dropColumn('special_value'));
        Schema::table('bets', function (Blueprint $table): void { $table->dropForeign(['pool_share_id']); $table->dropColumn(['special_value', 'pool_share_id']); });
        Schema::table('lottery_pools', fn (Blueprint $table) => $table->dropColumn(['lines', 'numbers_count']));
        Schema::table('draws', fn (Blueprint $table) => $table->dropColumn('sales_close_at'));
        Schema::table('lottery_games', fn (Blueprint $table) => $table->dropColumn(['min_numbers','max_numbers','price_table','rule_metadata','rules_source_url','rules_version']));
    }
};
