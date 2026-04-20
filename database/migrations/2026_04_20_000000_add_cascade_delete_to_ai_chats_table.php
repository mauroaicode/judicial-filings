<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_chats', function (Blueprint $table) {
            $table->dropForeign(['process_id']);
            $table->dropForeign(['organization_id']);
            $table->dropForeign(['app_user_id']);

            $table->foreign('process_id')
                ->references('id')
                ->on('processes')
                ->onDelete('cascade');

            $table->foreign('organization_id')
                ->references('id')
                ->on('organizations')
                ->onDelete('cascade');

            $table->foreign('app_user_id')
                ->references('id')
                ->on('app_users')
                ->onDelete('cascade');
        });

        Schema::table('ai_chat_messages', function (Blueprint $table) {
            $table->dropForeign(['ai_chat_id']);

            $table->foreign('ai_chat_id')
                ->references('id')
                ->on('ai_chats')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('ai_chats', function (Blueprint $table) {
            $table->dropForeign(['process_id']);
            $table->dropForeign(['organization_id']);
            $table->dropForeign(['app_user_id']);

            $table->foreign('process_id')->references('id')->on('processes');
            $table->foreign('organization_id')->references('id')->on('organizations');
            $table->foreign('app_user_id')->references('id')->on('app_users');
        });

        Schema::table('ai_chat_messages', function (Blueprint $table) {
            $table->dropForeign(['ai_chat_id']);
            $table->foreign('ai_chat_id')->references('id')->on('ai_chats');
        });
    }
};
