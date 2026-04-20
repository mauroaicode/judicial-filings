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
        Schema::create('ai_chats', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('organizations');
            $table->foreignUuid('process_id')->constrained('processes');
            $table->foreignUuid('app_user_id')->constrained('app_users');
            $table->string('title');
            $table->boolean('is_private')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['organization_id', 'process_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_chats');
    }
};
