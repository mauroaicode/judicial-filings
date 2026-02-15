<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Indica dónde se encontró el fragmento: anotación, actuación (título) o ambos.
     */
    public function up(): void
    {
        Schema::table('process_action_alert_highlights', function (Blueprint $table) {
            $table->string('source', 20)->default('annotation')->after('detected_text')
                ->comment('annotation = en anotación, action = en actuación, both = en ambos');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('process_action_alert_highlights', function (Blueprint $table) {
            $table->dropColumn('source');
        });
    }
};
