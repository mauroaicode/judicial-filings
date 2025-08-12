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
        Schema::create('processes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->bigInteger('process_id')->unique()->comment('ID interno de la API');
            $table->decimal('process_number', 23, 0)->unique()->comment('Número de radicado visible al usuario');
            $table->string('court')->comment('Despacho');
            $table->string('department')->comment('Departamento');
            $table->string('process_type')->comment('Tipo de proceso');
            $table->string('process_class')->comment('Clase de proceso');
            $table->string('subclass_process')->nullable()->comment('Subclase de proceso');
            $table->text('litigants')->nullable()->comment('Sujetos procesales (resumido)');
            $table->date('process_date')->comment('Fecha del proceso');
            $table->date('last_activity_date')->nullable()->comment('Fecha de última actuación');
            $table->string('location')->nullable()->comment('Ubicación');
            $table->text('filing_content')->nullable()->comment('Contenido de radicación');
            $table->boolean('is_private')->default(false)->comment('Indica si el proceso es privado');
            $table->timestamp('last_api_update')->nullable()->comment('Última actualización desde la API');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('processes');
    }
};
