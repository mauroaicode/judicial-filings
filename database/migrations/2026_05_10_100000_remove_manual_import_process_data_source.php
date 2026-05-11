<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * manual_import was removed: "manual" vs sync is {@see \Src\Domain\Process\Models\Process::is_manual_sync};
 * process_data_sources lists consultation providers only.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('process_data_sources')) {
            return;
        }

        DB::table('process_data_sources')->where('slug', 'manual_import')->delete();
    }

    public function down(): void
    {
        // Intentionally empty: do not resurrect manual_import as a data source.
    }
};
