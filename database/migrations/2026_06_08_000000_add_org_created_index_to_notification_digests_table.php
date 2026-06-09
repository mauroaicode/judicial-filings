<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add a composite index on (organization_id, created_at) to allow MySQL to satisfy
     * both the WHERE and ORDER BY clauses from the index alone, without loading the large
     * JSON `data` column into the sort buffer.
     *
     * Without this index, paginating notification_digests ordered by created_at forces
     * a full filesort that reads every row's `data` column into memory, causing
     * "Out of sort memory" errors as the table grows.
     */
    public function up(): void
    {
        Schema::table('notification_digests', function (Blueprint $table) {
            $table->index(['organization_id', 'created_at'], 'notification_digests_org_created_idx');
        });
    }

    public function down(): void
    {
        Schema::table('notification_digests', function (Blueprint $table) {
            $table->dropIndex('notification_digests_org_created_idx');
        });
    }
};
