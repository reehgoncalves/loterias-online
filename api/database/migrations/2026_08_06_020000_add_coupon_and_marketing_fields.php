<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('lottery_games', function (Blueprint $table): void {
            $table->unsignedSmallInteger('number_min')->default(1)->after('range_max');
            $table->boolean('allow_repeated_numbers')->default(false)->after('number_min');
            $table->string('selection_mode')->default('distinct')->after('allow_repeated_numbers');
            $table->json('special_options')->nullable()->after('selection_mode');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('marketing_opt_in')->default(false)->after('active');
            $table->timestamp('marketing_opted_out_at')->nullable()->after('marketing_opt_in');
        });

        Schema::create('marketing_campaigns', function (Blueprint $table): void {
            $table->id();
            $table->string('slug')->unique();
            $table->string('template');
            $table->string('subject');
            $table->string('window')->default('24h');
            $table->boolean('active')->default(true);
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });

        Schema::create('email_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('marketing_campaign_id')->nullable()->constrained('marketing_campaigns')->nullOnDelete();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('draw_id')->nullable()->constrained('draws')->nullOnDelete();
            $table->string('type')->default('marketing');
            $table->string('status')->default('queued');
            $table->string('idempotency_key')->unique();
            $table->timestamp('sent_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_deliveries');
        Schema::dropIfExists('marketing_campaigns');
        Schema::table('users', function (Blueprint $table): void { $table->dropColumn(['marketing_opt_in', 'marketing_opted_out_at']); });
        Schema::table('lottery_games', function (Blueprint $table): void { $table->dropColumn(['number_min', 'allow_repeated_numbers', 'selection_mode', 'special_options']); });
    }
};

