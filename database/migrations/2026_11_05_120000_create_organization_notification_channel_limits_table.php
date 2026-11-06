<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-organization override of how many destinations each notification
     * channel (email, WhatsApp, SMS) may have. Missing row = env default.
     */
    public function up(): void
    {
        Schema::create('organization_notification_channel_limits', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->string('channel_type', 20);
            $table->unsignedInteger('max_channels');
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->unique(['organization_id', 'channel_type'], 'org_notification_channel_limits_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_notification_channel_limits');
    }
};
