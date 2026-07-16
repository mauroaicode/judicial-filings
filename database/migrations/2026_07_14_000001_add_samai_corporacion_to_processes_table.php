<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('processes', function (Blueprint $table) {
            $table->string('samai_corporacion', 10)
                ->nullable()
                ->after('process_data_source_id')
                ->comment('Código de corporación SAMAI (7 dígitos). Solo para procesos con fuente SAMAI.');
        });
    }

    public function down(): void
    {
        Schema::table('processes', function (Blueprint $table) {
            $table->dropColumn('samai_corporacion');
        });
    }
};
