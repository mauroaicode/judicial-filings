<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Pivot: processes <-> process_subjects (many-to-many).
     * Permite que varias instancias de proceso compartan los mismos sujetos por subject_registration_id.
     */
    public function up(): void
    {
        Schema::create('process_process_subject', function (Blueprint $table) {
            $table->uuid('process_id');
            $table->uuid('process_subject_id');

            $table->primary(['process_id', 'process_subject_id']);
            $table->foreign('process_id')->references('id')->on('processes')->onDelete('cascade');
            $table->foreign('process_subject_id')->references('id')->on('process_subjects')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('process_process_subject');
    }
};
