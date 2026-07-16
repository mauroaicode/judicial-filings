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
            $table->string('data_source', 40)
                ->default('judicial_branch')
                ->after('radicado_filter')
                ->comment('Fuente sincronizada: judicial_branch | samai | tyba');

            $table->index('data_source');
        });
    }

    public function down(): void
    {
        Schema::table('judicial_sync_runs', function (Blueprint $table): void {
            $table->dropIndex(['data_source']);
            $table->dropColumn('data_source');
        });
    }
};
