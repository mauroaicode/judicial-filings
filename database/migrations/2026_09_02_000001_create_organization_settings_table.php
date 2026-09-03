<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_settings', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')
                ->unique()
                ->constrained('organizations')
                ->cascadeOnDelete();
            $table->unsignedInteger('max_active_processes')
                ->nullable()
                ->comment('Null = unlimited (or config default when creating). Counts distinct active radicados.');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_settings');
    }
};
