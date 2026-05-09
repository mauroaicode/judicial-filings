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
        Schema::table('app_users', function (Blueprint $table) {
            $table->boolean('session_lock_enabled')->default(true)->after('profile_image');
            $table->integer('session_lock_timeout')->nullable()->default(5)->after('session_lock_enabled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('app_users', function (Blueprint $table) {
            $table->dropColumn(['session_lock_enabled', 'session_lock_timeout']);
        });
    }
};
