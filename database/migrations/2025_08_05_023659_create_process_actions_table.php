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
        Schema::create('process_actions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('process_id');
            $table->bigInteger('action_registration_id')->unique()->comment('ID de registro de actuación desde la API');
            $table->date('action_date')->comment('Fecha de la actuación');
            $table->string('action')->comment('Descripción de la actuación');
            $table->text('annotation')->nullable()->comment('Anotación o detalles de la actuación');
            $table->date('start_date')->nullable()->comment('Fecha inicial del período de la actuación');
            $table->date('end_date')->nullable()->comment('Fecha final del período de la actuación');
            $table->date('registration_date')->comment('Fecha de registro de la actuación');
            $table->timestamps();

            $table->foreign('process_id')->references('id')->on('processes')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('process_actions');
    }
};
