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
        Schema::create('organization_process_action', function (Blueprint $table) {
            $table->uuid('organization_id');
            $table->uuid('process_action_id');
            $table->boolean('is_viewed')->default(false)->comment('Indica si la organización ha visto la actuación');
            $table->boolean('is_notified')->default(false)->comment('Indica si se ha enviado notificación por esta actuación');
            $table->timestamp('viewed_at')->nullable()->comment('Fecha y hora cuando se marcó como vista');
            $table->timestamp('notified_at')->nullable()->comment('Fecha y hora cuando se envió la notificación');
            $table->timestamps();

            $table->primary(['organization_id', 'process_action_id']);
            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('process_action_id')->references('id')->on('process_actions')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organization_process_action');
    }
};
