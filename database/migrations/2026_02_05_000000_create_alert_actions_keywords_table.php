<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Lista de palabras clave para alertas (Consulta, Apelación, etc.). Slug se usa como query param para filtrar.
     */
    public function up(): void
    {
        Schema::create('alert_actions_keywords', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->comment('Nombre para mostrar, ej. Apelación');
            $table->string('slug')->unique()->comment('Para query params, ej. apelacion');

            $table->index('slug');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('alert_actions_keywords');
    }
};
