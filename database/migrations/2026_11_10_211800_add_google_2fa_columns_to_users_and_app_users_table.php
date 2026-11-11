<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('app_users', function (Blueprint $table): void {
            $table->string('google_2fa_secret')->nullable();
            $table->boolean('google_2fa_enabled')->default(false);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->string('google_2fa_secret')->nullable();
            $table->boolean('google_2fa_enabled')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('app_users', function (Blueprint $table): void {
            $table->dropColumn(['google_2fa_secret', 'google_2fa_enabled']);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['google_2fa_secret', 'google_2fa_enabled']);
        });
    }
};
