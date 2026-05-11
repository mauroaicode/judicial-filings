<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('processes', function (Blueprint $table) {
            $table->boolean('is_manual_sync')->default(false)->after('last_api_update')->comment('True cuando datos/actuaciones se cargan fuera del sync Rama');
            $table->uuid('process_data_source_id')->nullable()->after('is_manual_sync');
        });

        $judicialId = DB::table('process_data_sources')
            ->where('slug', 'judicial_branch')
            ->value('id');

        if ($judicialId !== null) {
            DB::table('processes')->update([
                'process_data_source_id' => $judicialId,
                'is_manual_sync' => false,
            ]);
        }

        Schema::table('processes', function (Blueprint $table) {
            $table->foreign('process_data_source_id')
                ->references('id')
                ->on('process_data_sources')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('processes', function (Blueprint $table) {
            $table->dropForeign(['process_data_source_id']);
            $table->dropColumn(['is_manual_sync', 'process_data_source_id']);
        });
    }
};
