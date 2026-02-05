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
        Schema::create('process_action_alert_highlights', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('process_action_id');
            $table->unsignedInteger('start')->comment('Start index in annotation text (0-based, characters)');
            $table->unsignedInteger('end')->comment('End index in annotation text (exclusive)');
            $table->string('detected_text')->comment('Exact fragment detected for highlight');

            $table->foreign('process_action_id')
                ->references('id')
                ->on('process_actions')
                ->onDelete('cascade');

            $table->index('process_action_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('process_action_alert_highlights');
    }
};
