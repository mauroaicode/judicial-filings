<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per app user that opened a consolidado. The digest itself is already
     * scoped to one organization; viewed_at there means at least one user opened it.
     */
    public function up(): void
    {
        Schema::table('notification_digests', function (Blueprint $table) {
            $table->timestamp('viewed_at')->nullable()->after('sms_sent_at');
        });

        Schema::create('notification_digest_views', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('notification_digest_id');
            $table->uuid('organization_id');
            $table->uuid('app_user_id');
            $table->string('opened_via', 16);
            $table->timestamp('viewed_at');
            $table->timestamps();

            $table->foreign('notification_digest_id')->references('id')->on('notification_digests')->cascadeOnDelete();
            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('app_user_id')->references('id')->on('app_users')->cascadeOnDelete();
            $table->unique(['notification_digest_id', 'app_user_id'], 'digest_views_digest_user_unique');
            $table->index('organization_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_digest_views');

        Schema::table('notification_digests', function (Blueprint $table) {
            $table->dropColumn('viewed_at');
        });
    }
};
