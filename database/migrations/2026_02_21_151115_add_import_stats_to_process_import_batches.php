<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('process_import_batches', function (Blueprint $table): void {
            // Total valid radicados present in the uploaded Excel (enqueued + skipped already-registered).
            $table->unsignedInteger('excel_total_count')->default(0)->after('total_count');

            // Number of radicados that had more than one judicial instance (doble instancia).
            $table->unsignedInteger('multiple_instances_count')->default(0)->after('failed_count');
        });
    }

    public function down(): void
    {
        Schema::table('process_import_batches', function (Blueprint $table): void {
            $table->dropColumn(['excel_total_count', 'multiple_instances_count']);
        });
    }
};
