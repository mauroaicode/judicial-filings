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
        Schema::create('organization_processes', function (Blueprint $table) {
            $table->uuid('organization_id');
            $table->uuid('process_id');
            $table->date('interest_date');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->primary(['organization_id', 'process_id']);
            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('process_id')->references('id')->on('processes')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organization_processes');
    }
};
