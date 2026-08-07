<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('lottery_games', function (Blueprint $table): void {
            $table->json('selling_price_table')->nullable()->after('price_table');
        });
    }

    public function down(): void
    {
        Schema::table('lottery_games', fn (Blueprint $table) => $table->dropColumn('selling_price_table'));
    }
};
