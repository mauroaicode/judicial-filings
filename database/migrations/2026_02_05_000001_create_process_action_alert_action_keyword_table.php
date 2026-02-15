<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Relación directa actuaciones (process_actions) <-> palabras clave (alert_actions_keywords).
     * Permite filtrar "todas las actuaciones con Apelación" o "todas las de Consulta".
     */
    public function up(): void
    {
        Schema::create('process_action_alert_action_keyword', function (Blueprint $table) {
            $table->uuid('process_action_id');
            $table->uuid('alert_action_keyword_id');

            $table->primary(['process_action_id', 'alert_action_keyword_id']);
            $table->foreign('process_action_id', 'paaak_process_action_id_fk')
                ->references('id')
                ->on('process_actions')
                ->onDelete('cascade');
            $table->foreign('alert_action_keyword_id', 'paaak_alert_action_keyword_id_fk')
                ->references('id')
                ->on('alert_actions_keywords')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('process_action_alert_action_keyword');
    }
};
