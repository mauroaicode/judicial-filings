<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('process_import_batches', function (Blueprint $table): void {
            $table->boolean('is_private_import')->default(false)->after('file_name')->comment('True when sourced from synchronous private Excel import (not Rama Judicial queue)');
        });
    }

    public function down(): void
    {
        Schema::table('process_import_batches', function (Blueprint $table): void {
            $table->dropColumn('is_private_import');
        });
    }
};
