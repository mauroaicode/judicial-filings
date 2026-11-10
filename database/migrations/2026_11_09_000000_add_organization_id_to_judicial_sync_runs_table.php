<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('judicial_sync_runs', function (Blueprint $table): void {
            $table->foreignUuid('organization_id')
                ->nullable()
                ->after('data_source')
                ->constrained('organizations')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('judicial_sync_runs', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('organization_id');
        });
    }
};
