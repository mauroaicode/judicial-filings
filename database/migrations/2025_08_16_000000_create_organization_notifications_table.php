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
        Schema::create('organization_notifications', function (Blueprint $table) {
            $table->uuid('organization_id');
            $table->uuid('notifiable_id');
            $table->string('notifiable_type');
            $table->string('notification_type');
            $table->boolean('is_viewed')->default(false)->comment('Indica si la organización ha visto la notificación');
            $table->boolean('is_notified')->default(false)->comment('Indica si se ha enviado notificación');
            $table->timestamp('viewed_at')->nullable()->comment('Fecha y hora cuando se marcó como vista');
            $table->timestamp('notified_at')->nullable()->comment('Fecha y hora cuando se envió la notificación');
            $table->timestamps();

            $table->primary(['organization_id', 'notifiable_id', 'notifiable_type']);
            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->index(['notifiable_type', 'notifiable_id']);
            $table->index('notification_type');
            $table->index('is_notified');
            $table->index('notified_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organization_notifications');
    }
};
