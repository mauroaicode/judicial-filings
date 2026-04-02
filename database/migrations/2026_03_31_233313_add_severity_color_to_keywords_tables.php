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
        Schema::table('keywords', function (Blueprint $table) {
            $table->string('severity_color')->nullable()->after('status');
        });

        Schema::table('alert_actions_keywords', function (Blueprint $table) {
            $table->string('severity_color')->nullable()->after('slug');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('keywords', function (Blueprint $table) {
            $table->dropColumn('severity_color');
        });

        Schema::table('alert_actions_keywords', function (Blueprint $table) {
            $table->dropColumn('severity_color');
        });
    }
};
