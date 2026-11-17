<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('court_directory_sync_runs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->string('trigger', 20);
            $table->string('status', 20);
            $table->unsignedInteger('rows_fetched');
            $table->unsignedInteger('rows_upserted');
            $table->unsignedInteger('rows_marked_inactive');
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index('started_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('court_directory_sync_runs');
    }
};
