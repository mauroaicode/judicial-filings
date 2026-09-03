<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('organization_processes', function (Blueprint $table): void {
            $table->dropForeign(['organization_id']);
            $table->dropForeign(['process_id']);
        });

        Schema::table('organization_processes', function (Blueprint $table): void {
            $table->dropPrimary(['organization_id', 'process_id']);
        });

        Schema::table('organization_processes', function (Blueprint $table): void {
            $table->uuid('id')->nullable()->after('process_id');
        });

        $rows = DB::table('organization_processes')
            ->whereNull('id')
            ->get(['organization_id', 'process_id']);

        foreach ($rows as $row) {
            DB::table('organization_processes')
                ->where('organization_id', $row->organization_id)
                ->where('process_id', $row->process_id)
                ->update(['id' => (string) Str::uuid()]);
        }

        Schema::table('organization_processes', function (Blueprint $table): void {
            $table->uuid('id')->nullable(false)->change();
            $table->primary('id');
            $table->unique(['organization_id', 'process_id']);
            $table->softDeletes();
            $table->uuid('deleted_by')->nullable()->after('deleted_at');
            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('process_id')->references('id')->on('processes')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('organization_processes', function (Blueprint $table): void {
            $table->dropForeign(['organization_id']);
            $table->dropForeign(['process_id']);
            $table->dropUnique(['organization_id', 'process_id']);
            $table->dropSoftDeletes();
            $table->dropColumn(['deleted_by']);
            $table->dropPrimary(['id']);
            $table->dropColumn('id');
        });

        Schema::table('organization_processes', function (Blueprint $table): void {
            $table->primary(['organization_id', 'process_id']);
            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('process_id')->references('id')->on('processes')->cascadeOnDelete();
        });
    }
};
