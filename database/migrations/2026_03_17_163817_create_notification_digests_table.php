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
        Schema::create('notification_digests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id')->index();
            $table->json('data')->comment('Consolidated table data for the digest');
            $table->timestamp('email_sent_at')->nullable();
            $table->timestamp('whatsapp_sent_at')->nullable();
            $table->timestamp('sms_sent_at')->nullable();
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
        });

        Schema::table('organization_notifications', function (Blueprint $table) {
            $table->uuid('notification_digest_id')->nullable()->after('notification_type')->index();
            $table->foreign('notification_digest_id')->references('id')->on('notification_digests')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('organization_notifications', function (Blueprint $table) {
            $table->dropForeign(['notification_digest_id']);
            $table->dropColumn('notification_digest_id');
        });

        Schema::dropIfExists('notification_digests');
    }
};
