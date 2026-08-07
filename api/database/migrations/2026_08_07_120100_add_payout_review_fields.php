<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('payouts', function (Blueprint $table): void {
            $table->timestamp('credit_available_at')->nullable()->after('review_note');
            $table->foreignId('approved_by')->nullable()->after('credit_available_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payouts', function (Blueprint $table): void {
            $table->dropForeign(['approved_by']);
            $table->dropColumn(['approved_by', 'credit_available_at']);
        });
    }
};
