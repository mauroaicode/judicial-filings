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
        Schema::create('organization_notification_channels', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->enum('channel_type', ['email', 'whatsapp', 'sms', 'internal']);
            $table->string('channel_value');
            $table->boolean('is_active')->default(true);
            $table->integer('priority')->default(1); // prioridad del canal (1, 2, 3)
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->unique(['organization_id', 'channel_type', 'priority'], 'unique_org_channel_priority');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organization_notification_channels');
    }
};
