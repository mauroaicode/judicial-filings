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
        Schema::create('history_organizations_channels_notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_notification_channel_id');
            $table->uuid('notifiable_id');
            $table->string('notifiable_type');
            $table->string('notification_type');
            $table->boolean('is_notified')->default(false);
            $table->timestamp('notified_at')->nullable();
            $table->timestamps();

            // Índices para consultas frecuentes (nombres cortos para evitar error de longitud)
            $table->index(['organization_notification_channel_id'], 'idx_history_org_channel_id');
            $table->index(['notifiable_type', 'notifiable_id'], 'idx_history_notifiable');
            $table->index(['notification_type'], 'idx_history_notif_type');
            $table->index(['is_notified'], 'idx_history_notif_status');
            $table->index(['created_at'], 'idx_history_created');

            // Clave foránea (nombre corto para evitar error de longitud)
            $table->foreign('organization_notification_channel_id', 'fk_history_org_channel_id')
                ->references('id')
                ->on('organization_notification_channels')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('history_organizations_channels_notifications');
    }
};
