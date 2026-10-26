<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The admin list orders every organization's digests by created_at.
     * The existing (organization_id, created_at) index cannot serve that order,
     * so MySQL filesorts the table. This index lets the id page walk created_at
     * without sorting the JSON `data` column.
     */
    public function up(): void
    {
        Schema::table('notification_digests', function (Blueprint $table) {
            $table->index('created_at', 'notification_digests_created_at_idx');
        });
    }

    public function down(): void
    {
        Schema::table('notification_digests', function (Blueprint $table) {
            $table->dropIndex('notification_digests_created_at_idx');
        });
    }
};
