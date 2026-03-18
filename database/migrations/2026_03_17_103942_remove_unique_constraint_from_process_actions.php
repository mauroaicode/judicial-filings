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
        Schema::table('process_actions', function (Blueprint $table) {
            $table->dropUnique(['action_registration_id']);
            $table->index('action_registration_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('process_actions', function (Blueprint $table) {
            $table->dropIndex(['action_registration_id']);
            $table->unique('action_registration_id');
        });
    }
};
