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
            $table->boolean('is_email_notified')->default(false)->after('is_notified')->index();
            $table->timestamp('email_notified_at')->nullable()->after('notified_at')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('organization_notifications', function (Blueprint $table) {
            $table->dropColumn(['is_email_notified', 'email_notified_at']);
        });
    }
};
