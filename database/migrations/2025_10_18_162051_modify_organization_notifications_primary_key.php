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
        // Primero, eliminar la restricción de clave foránea
        Schema::table('organization_notifications', function (Blueprint $table) {
            $table->dropForeign(['organization_id']);
        });

        // Eliminar la clave primaria existente
        Schema::table('organization_notifications', function (Blueprint $table) {
            $table->dropPrimary();
        });

        // Agregar la nueva clave primaria que incluye notification_type
        Schema::table('organization_notifications', function (Blueprint $table) {
            $table->primary([
                'organization_id',
                'notifiable_id',
                'notifiable_type',
                'notification_type',
            ], 'organization_notifications_primary');
        });

        // Restaurar la restricción de clave foránea
        Schema::table('organization_notifications', function (Blueprint $table) {
            $table->foreign('organization_id')
                ->references('id')
                ->on('organizations')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Eliminar la restricción de clave foránea
        Schema::table('organization_notifications', function (Blueprint $table) {
            $table->dropForeign(['organization_id']);
        });

        // Eliminar la nueva clave primaria
        Schema::table('organization_notifications', function (Blueprint $table) {
            $table->dropPrimary('organization_notifications_primary');
        });

        // Restaurar la clave primaria original
        Schema::table('organization_notifications', function (Blueprint $table) {
            $table->primary([
                'organization_id',
                'notifiable_id',
                'notifiable_type',
            ]);
        });

        // Restaurar la restricción de clave foránea
        Schema::table('organization_notifications', function (Blueprint $table) {
            $table->foreign('organization_id')
                ->references('id')
                ->on('organizations')
                ->onDelete('cascade');
        });
    }
};
