<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('process_data_sources', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('slug')->unique()->comment('Stable key for code and jobs');
            $table->string('name')->comment('Human-readable label');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $now = now();
        DB::table('process_data_sources')->insert([
            [
                'id' => 'a0000001-0000-4000-8000-000000000001',
                'slug' => 'judicial_branch',
                'name' => 'Rama Judicial — Consulta proceso judicial',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 'a0000001-0000-4000-8000-000000000002',
                'slug' => 'samai',
                'name' => 'Consejo de Estado (SAMAI)',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('process_data_sources');
    }
};
