<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('processes', function (Blueprint $table): void {
            $table->timestamp('became_private_at')
                ->nullable()
                ->after('is_private')
                ->comment('Fecha en que la fuente original (ej. Rama Judicial) marcó el proceso como privado. Null mientras sea público.');
        });
    }

    public function down(): void
    {
        Schema::table('processes', function (Blueprint $table): void {
            $table->dropColumn('became_private_at');
        });
    }
};
