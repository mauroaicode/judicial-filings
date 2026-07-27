<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Makes organization_id nullable so cross-organization imports (e.g. actuaciones-only
 * import where the radicado resolves processes across all organizations) can be tracked
 * without being tied to a single organization.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('process_import_batches', function (Blueprint $table): void {
            $table->dropForeign(['organization_id']);
            $table->uuid('organization_id')->nullable()->change();
            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('process_import_batches', function (Blueprint $table): void {
            $table->dropForeign(['organization_id']);
            $table->uuid('organization_id')->nullable(false)->change();
            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
        });
    }
};
