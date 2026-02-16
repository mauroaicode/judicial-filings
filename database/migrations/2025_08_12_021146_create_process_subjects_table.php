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
        Schema::create('process_subjects', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->bigInteger('subject_registration_id')->unique()->comment('Internal API subject registration ID');
            $table->string('subject_type')->comment('Type of subject (Demandante, Demandado, etc.)');
            $table->boolean('is_cited')->default(false)->comment('Whether the subject is cited/summoned');
            $table->string('identification')->nullable()->comment('Subject identification number');
            $table->text('name_or_business_name')->comment('Subject name or business name');
            $table->timestamps();

            $table->index('subject_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('process_subjects');
    }
};
