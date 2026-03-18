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
        Schema::table('organization_notifications', function (Blueprint $table) {
            $table->dropForeign('organization_notifications_organization_id_foreign');
            $table->dropPrimary();
        });
        
        Schema::table('organization_notifications', function (Blueprint $table) {
            $table->primary(['organization_id', 'notifiable_id', 'notifiable_type', 'notification_type']);
            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('organization_notifications', function (Blueprint $table) {
            $table->dropForeign('organization_notifications_organization_id_foreign');
            $table->dropPrimary();
        });

        Schema::table('organization_notifications', function (Blueprint $table) {
            $table->primary(['organization_id', 'notifiable_id', 'notifiable_type']);
            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
        });
    }
};
