<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('judicial_sync_runs', function (Blueprint $table) {
            $table->dropColumn('trigger');
        });
    }

    public function down(): void
    {
        Schema::table('judicial_sync_runs', function (Blueprint $table) {
            $table->string('trigger', 20)->after('batch_finished_at');
        });
    }
};
