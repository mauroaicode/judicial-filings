<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per app user per day. Live presence stays in Reverb, not in this table.
     */
    public function up(): void
    {
        Schema::create('app_user_daily_activities', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('app_user_id');
            $table->date('activity_date');
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at');
            $table->unsignedInteger('login_count')->default(0);
            $table->string('ip_address', 45)->nullable();
            $table->string('country')->nullable();
            $table->string('region')->nullable();
            $table->string('city')->nullable();
            $table->timestamps();

            $table->foreign('app_user_id')->references('id')->on('app_users')->cascadeOnDelete();
            $table->unique(['app_user_id', 'activity_date']);
            $table->index('activity_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_user_daily_activities');
    }
};
