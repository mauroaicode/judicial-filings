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
        Schema::create('ai_chat_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('ai_chat_id')->constrained('ai_chats')->onDelete('cascade');
            $table->enum('role', ['user', 'assistant']);
            $table->longText('content');
            $table->integer('tokens')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_chat_messages');
    }
};
